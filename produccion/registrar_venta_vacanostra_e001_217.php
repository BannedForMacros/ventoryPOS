<?php

/**
 * Registra en VACANOSTRA la factura electrónica externa E001-217 (emitida por
 * su facturador externo) a AGROVISION PERU S.A.C., al crédito, con fecha
 * 23/09/2026 (pedido del dueño, 28/09/2026).
 *
 *   Entraña fina CAB Angus High Choice PAB   9.34 kg × 187.00 = 1,746.58
 *   Lomo fino de res Black Angus             6.38 kg × 205.00 = 1,307.90
 *   Picaña CAB Angus High Choice Sterling    5.86 kg × 138.00 =   808.68
 *                                                       Total   3,863.16
 * (Valor unitario de la factura + IGV 18 % = precio por kg con IGV.)
 *
 * Crea el cliente y los 3 productos (por kilo, gravados) si no existen: los
 * cortes que había eran otros y estaban marcados sin IGV. Idempotente: si la
 * E001-217 ya está registrada no hace nada.
 *
 *   php produccion/registrar_venta_vacanostra_e001_217.php            (simulación)
 *   php produccion/registrar_venta_vacanostra_e001_217.php --aplicar  (escribe)
 */

use App\Models\Cliente;
use App\Models\Producto;
use App\Models\Turno;
use App\Models\User;
use App\Services\VentaService;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

const EMPRESA = 1102;             // VACANOSTRA
const FECHA   = '2026-09-23';     // miércoles
const NUMERO  = 'E001-217';

$aplicar = in_array('--aplicar', $argv, true);
echo $aplicar ? "MODO APLICAR\n" : "MODO SIMULACIÓN (usa --aplicar para escribir)\n";

if (DB::table('ventas')->where('empresa_id', EMPRESA)->where('numero_comprobante', NUMERO)->where('estado', '!=', 'anulada')->exists()) {
    echo "La venta " . NUMERO . " ya está registrada: no se hace nada.\n";
    exit(0);
}

$admin = User::where('empresa_id', EMPRESA)->where('activo', true)
    ->whereHas('rol', fn ($q) => $q->where('es_admin', true))->orderBy('id')->firstOrFail();
$turno = Turno::where('empresa_id', EMPRESA)->whereDate('fecha_apertura', FECHA)->orderBy('id')->firstOrFail();
$kg    = DB::table('unidades_medida')->where('empresa_id', EMPRESA)->whereRaw("LOWER(abreviatura) = 'kg'")->where('activo', true)->value('id')
    ?? throw new RuntimeException('Vacanostra no tiene la unidad kg.');
// Categoría de las carnes ya existentes (la de "ENTRAÑA 1.7KG").
$categoria = DB::table('productos')->where('empresa_id', EMPRESA)->where('nombre', 'ilike', 'ENTRAÑA%')->whereNotNull('categoria_id')->value('categoria_id');

echo "Usuario: {$admin->name} · Turno #{$turno->id} ({$turno->estado}) del " . FECHA . "\n";

$lineas = [
    ['CAB-ENT',  'ENTRAÑA FINA CAB ANGUS HIGH CHOICE PAB',             9.34, 187.00],
    ['CAB-LOMO', 'LOMO FINO DE RES BLACK ANGUS',                        6.38, 205.00],
    ['CAB-PICA', 'PICAÑA CAB ANGUS HIGH CHOICE STERLING SILVER USA',    5.86, 138.00],
];

DB::beginTransaction();
try {
    $cliente = Cliente::firstOrCreate(
        ['empresa_id' => EMPRESA, 'numero_documento' => '20554556192'],
        [
            'tipo_documento' => 'RUC',
            'razon_social'   => 'AGROVISION PERU S.A.C.',
            'direccion'      => 'AV. FELIPE PARDO Y ALIAGA 652 INT. 1201 LIMA LIMA SAN ISIDRO',
            'activo'         => true,
        ],
    );
    echo "Cliente #{$cliente->id} {$cliente->razon_social}\n";

    $items = [];
    foreach ($lineas as [$codigo, $nombre, $cantidad, $precio]) {
        $p = Producto::where('empresa_id', EMPRESA)->where('codigo', $codigo)->first();
        if (!$p) {
            $p = Producto::create([
                'empresa_id'     => EMPRESA,
                'categoria_id'   => $categoria,
                'codigo'         => $codigo,
                'nombre'         => $nombre,
                'tipo'           => 'producto',
                'tipo_precio'    => 'fijo',
                'precio_venta'   => 0,
                'precio_costo'   => 0,
                'incluye_igv'    => true,
                'controla_stock' => null,
                'es_retornable'  => null,
                'activo'         => true,
            ]);
            $p->unidades()->create([
                'unidad_medida_id'  => $kg,
                'es_base'           => true,
                'factor_conversion' => 1,
                'tipo_precio'       => 'fijo',
                'precio_venta'      => $precio,
                'precio_costo'      => 0,
                'activo'            => true,
            ]);
            echo "Producto creado #{$p->id} {$nombre} (kg, S/ {$precio} con IGV)\n";
        }
        $unidad = $p->unidades()->where('es_base', true)->firstOrFail();
        $items[] = [
            'producto_id'        => $p->id,
            'producto_unidad_id' => $unidad->id,
            'cantidad'           => $cantidad,
            'precio_unitario'    => $precio,
        ];
    }

    $venta = app(VentaService::class)->crear([
        'cliente_id'         => $cliente->id,
        'tipo_comprobante'   => 'factura_externa',
        'numero_comprobante' => NUMERO,
        'es_credito'         => true,
        'fecha_vencimiento'  => FECHA,
        'fecha_venta'        => FECHA,
        'observacion'        => 'Factura electrónica ' . NUMERO . ' (emitida en el facturador externo). Detracción 4 %: S/ 154.53.',
        'items'              => $items,
        'pagos'              => [],
    ], $admin, $turno);

    $venta->refresh();
    echo "Venta {$venta->numero} · fecha {$venta->fecha_venta} · comprobante {$venta->tipo_comprobante} {$venta->numero_comprobante}\n";
    echo "  subtotal {$venta->subtotal} · igv {$venta->igv} · total {$venta->total} · crédito " . ($venta->es_credito ? 'sí' : 'no')
        . " · saldo por cobrar {$venta->saldo_pendiente} · estado {$venta->estado}\n";

    if (abs((float) $venta->total - 3863.16) > 0.009) {
        throw new RuntimeException("El total ({$venta->total}) no cuadra con la factura (3,863.16).");
    }

    if ($aplicar) { DB::commit(); echo "APLICADO.\n"; }
    else          { DB::rollBack(); echo "SIMULACIÓN: nada se escribió.\n"; }
} catch (\Throwable $e) {
    DB::rollBack();
    echo "ERROR (nada se escribió): {$e->getMessage()}\n";
    exit(1);
}
