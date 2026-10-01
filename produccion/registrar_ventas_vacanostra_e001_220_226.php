<?php

/**
 * VACANOSTRA → AGROVISION PERU S.A.C. (pedido del dueño, 30/09/2026):
 *
 *   1. E001-217 (ya registrada al crédito, V-0007 del 23/09): se marca PAGADA
 *      con un abono por transferencia a la Cuenta BCP, fecha 23/09.
 *   2. E001-220 del 25/09 (turno de ese día), pagada por transferencia BCP:
 *        Entraña fina CAB    11.30 kg × 187.00 = 2,113.10
 *        Lomo fino           19.90 kg × 205.00 = 4,079.50
 *        Picaña CAB           6.59 kg × 138.00 =   909.42
 *                                        Total   7,102.02
 *   3. E001-226 del 30/09 (turno de ese día), pagada por transferencia BCP:
 *        Asado de tira con hueso USA CG BL Angus High Choice
 *                             4.00 kg × 157.00 =   628.00
 *
 * Precios = valor unitario de la factura + IGV 18 %. El asado se crea por kilo
 * y gravado (los asados que había son otras presentaciones, por paquete).
 * Idempotente: lo ya registrado/pagado no se repite.
 *
 *   php produccion/registrar_ventas_vacanostra_e001_220_226.php            (simulación)
 *   php produccion/registrar_ventas_vacanostra_e001_220_226.php --aplicar  (escribe)
 */

use App\Http\Controllers\Finanzas\CuentasPorCobrarController;
use App\Models\Cliente;
use App\Models\Producto;
use App\Models\Turno;
use App\Models\User;
use App\Models\Venta;
use App\Services\VentaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

const EMPRESA = 1102; // VACANOSTRA

$aplicar = in_array('--aplicar', $argv, true);
echo $aplicar ? "MODO APLICAR\n" : "MODO SIMULACIÓN (usa --aplicar para escribir)\n";

$admin = User::where('empresa_id', EMPRESA)->where('activo', true)
    ->whereHas('rol', fn ($q) => $q->where('es_admin', true))->orderBy('id')->firstOrFail();
auth()->setUser($admin);

// Transferencia → Cuenta BCP (el método tiene BCP e Interbank).
$transferencia = DB::table('metodos_pago as m')->join('tipos_metodo_pago as t', 't.id', '=', 'm.tipo_id')
    ->where('m.empresa_id', EMPRESA)->where('t.slug', 'transferencia')->where('m.activo', true)->value('m.id');
$bcp = DB::table('cuenta_metodo_pago as cmp')->join('cuentas as c', 'c.id', '=', 'cmp.cuenta_id')
    ->where('cmp.metodo_pago_id', $transferencia)->where('c.nombre', 'ilike', '%BCP%')
    ->first(['cmp.id as pivot_id', 'c.id as cuenta_id', 'c.nombre']);
if (!$transferencia || !$bcp) throw new RuntimeException('No se encontró Transferencia → Cuenta BCP.');
echo "Pago: método #{$transferencia} Transferencia → {$bcp->nombre} (cuenta #{$bcp->cuenta_id})\n";

$cliente = Cliente::where('empresa_id', EMPRESA)->where('numero_documento', '20554556192')->firstOrFail();
$kg = DB::table('unidades_medida')->where('empresa_id', EMPRESA)->whereRaw("LOWER(abreviatura) = 'kg'")->where('activo', true)->value('id');
$categoria = DB::table('productos')->where('empresa_id', EMPRESA)->where('codigo', 'CAB-ENT')->value('categoria_id');

/** Producto por código; si no existe lo crea por kilo y gravado. */
$producto = function (string $codigo, string $nombre, float $precio) use ($kg, $categoria) {
    $p = Producto::where('empresa_id', EMPRESA)->where('codigo', $codigo)->first();
    if (!$p) {
        $p = Producto::create([
            'empresa_id' => EMPRESA, 'categoria_id' => $categoria, 'codigo' => $codigo, 'nombre' => $nombre,
            'tipo' => 'producto', 'tipo_precio' => 'fijo', 'precio_venta' => 0, 'precio_costo' => 0,
            'incluye_igv' => true, 'controla_stock' => null, 'es_retornable' => null, 'activo' => true,
        ]);
        $p->unidades()->create([
            'unidad_medida_id' => $kg, 'es_base' => true, 'factor_conversion' => 1,
            'tipo_precio' => 'fijo', 'precio_venta' => $precio, 'precio_costo' => 0, 'activo' => true,
        ]);
        echo "  Producto creado #{$p->id} {$nombre} (kg, S/ {$precio} con IGV)\n";
    }
    return $p;
};

$facturas = [
    ['E001-220', '2026-09-25', 7102.02, [
        ['CAB-ENT',  'ENTRAÑA FINA CAB ANGUS HIGH CHOICE PAB',          11.30, 187.00],
        ['CAB-LOMO', 'LOMO FINO DE RES BLACK ANGUS',                     19.90, 205.00],
        ['CAB-PICA', 'PICAÑA CAB ANGUS HIGH CHOICE STERLING SILVER USA',  6.59, 138.00],
    ]],
    ['E001-226', '2026-09-30', 628.00, [
        ['CAB-ASADO', 'ASADO DE TIRA CON HUESO USA CG BL ANGUS HIGH CHOICE', 4.00, 157.00],
    ]],
];

DB::beginTransaction();
try {
    // ── 1) E001-217: abono por transferencia (mismo camino que "Abonar" en CxC) ──
    $v217 = Venta::where('empresa_id', EMPRESA)->where('numero_comprobante', 'E001-217')->where('estado', 'completada')->firstOrFail();
    if ((float) $v217->saldo_pendiente > 0.009) {
        $req = Request::create('/finanzas/cuentas-por-cobrar/' . $v217->id . '/abonar', 'POST', [
            'monto'          => (float) $v217->saldo_pendiente,
            'fecha'          => '2026-09-23',
            'metodo_pago_id' => $transferencia,
            'cuenta_id'      => $bcp->cuenta_id,
            'turno_id'       => null,   // transferencia directa al banco: no pasa por la caja de un turno
            'observacion'    => 'Factura E001-217 pagada por transferencia (Cuenta BCP).',
        ]);
        $req->setUserResolver(fn () => $admin);
        app()->instance('request', $req);
        app(CuentasPorCobrarController::class)->abonar($req, $v217);
        $v217->refresh();
        echo "E001-217 ({$v217->numero}): abono S/ " . number_format((float) $v217->monto_pagado, 2) . " → saldo {$v217->saldo_pendiente}\n";
    } else {
        echo "E001-217 ya estaba pagada.\n";
    }

    // ── 2 y 3) Ventas nuevas, al contado, pagadas por transferencia BCP ──
    foreach ($facturas as [$numero, $fecha, $totalFactura, $lineas]) {
        if (Venta::where('empresa_id', EMPRESA)->where('numero_comprobante', $numero)->where('estado', '!=', 'anulada')->exists()) {
            echo "{$numero} ya estaba registrada.\n";
            continue;
        }
        $turno = Turno::where('empresa_id', EMPRESA)->whereDate('fecha_apertura', $fecha)->orderBy('id')->firstOrFail();
        $items = [];
        foreach ($lineas as [$codigo, $nombre, $cantidad, $precio]) {
            $p = $producto($codigo, $nombre, $precio);
            $items[] = [
                'producto_id'        => $p->id,
                'producto_unidad_id' => $p->unidades()->where('es_base', true)->value('id'),
                'cantidad'           => $cantidad,
                'precio_unitario'    => $precio,
            ];
        }
        $venta = app(VentaService::class)->crear([
            'cliente_id'         => $cliente->id,
            'tipo_comprobante'   => 'factura_externa',
            'numero_comprobante' => $numero,
            'es_credito'         => false,
            'fecha_venta'        => $fecha,
            'observacion'        => "Factura electrónica {$numero} (facturador externo), pagada por transferencia a la Cuenta BCP.",
            'items'              => $items,
            'pagos'              => [[
                'metodo_pago_id'        => $transferencia,
                'cuenta_metodo_pago_id' => $bcp->pivot_id,
                'monto'                 => $totalFactura,
                'referencia'            => "Transferencia factura {$numero}",
            ]],
        ], $admin, $turno);
        $venta->refresh();
        echo "{$numero}: {$venta->numero} · {$venta->fecha_venta} · turno #{$turno->id} · total {$venta->total} · igv {$venta->igv} · pagado {$venta->monto_pagado} · saldo {$venta->saldo_pendiente}\n";
        if (abs((float) $venta->total - $totalFactura) > 0.009) {
            throw new RuntimeException("{$numero}: el total ({$venta->total}) no cuadra con la factura ({$totalFactura}).");
        }
    }

    if ($aplicar) { DB::commit(); echo "APLICADO.\n"; }
    else          { DB::rollBack(); echo "SIMULACIÓN: nada se escribió.\n"; }
} catch (ValidationException $e) {
    DB::rollBack();
    echo "ERROR de validación (nada se escribió): " . json_encode($e->errors(), JSON_UNESCAPED_UNICODE) . "\n";
    exit(1);
} catch (\Throwable $e) {
    DB::rollBack();
    echo "ERROR (nada se escribió): {$e->getMessage()} @ {$e->getFile()}:{$e->getLine()}\n";
    exit(1);
}
