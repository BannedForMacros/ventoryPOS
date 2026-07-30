<?php

/**
 * Migración del catálogo de MacSoft: de ferretería a software.
 *
 * ─── QUÉ HACE ────────────────────────────────────────────────────────────────
 *
 * 1. DESACTIVA (no borra) los 12 productos `FER-*` de MacSoft. Son ladrillos,
 *    cemento y fierro: catálogo de una ferretería, que no es a lo que se dedica
 *    MacSoft. Tienen 33 ventas registradas, así que BORRARLOS rompería el
 *    historial y los reportes; desactivarlos los saca del POS y deja las ventas
 *    pasadas intactas.
 *
 * 2. Crea las unidades de medida que faltan (MES, HORA) y la de servicios con la
 *    misma convención que usa la aplicación: `UnidadMedida::firstOrCreate` con
 *    nombre 'Servicio' y abreviatura 'srv', tal como hace
 *    ProductoController::crearPresentacionDefaultServicio().
 *
 * 3. Crea los 8 productos de MacSoft que hoy viven en FacturaMac, con la misma
 *    estructura que produciría el formulario del catálogo: para los físicos el
 *    precio va en `producto_unidades` y el del producto queda a 0; para los
 *    servicios el precio va en ambos.
 *
 * ─── DECISIONES ──────────────────────────────────────────────────────────────
 *
 * · `incluye_igv = true`: los precios son BRUTOS, según el criterio del dueño.
 *   Cobrar 200 declara base 169.49 + IGV 30.51 = 200.00 exacto.
 *
 * · `precio_costo = 0`: hay un piso de precio en StoreVentaRequest que impide
 *   vender por debajo del costo. Con costo 0 no hay piso, y se puede rebajar
 *   cualquier línea en la venta (que es lo que se pidió: cobrar 100 donde el
 *   catálogo dice 200).
 *
 * · `controla_stock = false` incluso en los dos físicos (SSD y RAM): MacSoft no
 *   mantiene inventario, y con stock 0 la primera venta lo dejaría en negativo.
 *   Se activa desde la interfaz el día que sí se almacene mercadería.
 *
 * · SV001 queda `referencial` a S/1.00: no es un precio real sino un marcador, y
 *   `referencial` permite teclear el importe en cada venta.
 *
 * ─── USO ─────────────────────────────────────────────────────────────────────
 *
 *   php produccion/migrar_productos_macsoft.php            (simulación, no escribe)
 *   php produccion/migrar_productos_macsoft.php --aplicar  (escribe)
 *
 * Es idempotente: los productos se buscan por (empresa, código) y se actualizan
 * en vez de duplicarse.
 */

use App\Models\Producto;
use App\Models\UnidadMedida;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

const EMPRESA_ID = 1; // MacSoft E.I.R.L.

$aplicar = in_array('--aplicar', $argv, true);

/** Los 8 productos tal como están en FacturaMac, con el precio interpretado BRUTO. */
$catalogo = [
    // código,  nombre,                                        tipo,       unidad,  precio, tipo_precio
    ['HW001', 'Disco Duro SSD 1TB',                          'producto', 'UND',   250.00, 'fijo'],
    ['HW002', 'Memoria RAM 16GB DDR4',                       'producto', 'UND',   180.00, 'fijo'],
    ['MT001', 'Soporte Técnico Mensual',                     'servicio', 'MES',   200.00, 'fijo'],
    ['MT002', 'Mantenimiento Preventivo de Equipos',         'servicio', 'srv',   120.00, 'fijo'],
    ['SV001', 'Servicio de Implementación y Configuración',  'servicio', 'HORA',    1.00, 'referencial'],
    ['SV002', 'Servicio de Capacitación (por hora)',         'servicio', 'HORA',   60.00, 'fijo'],
    ['SW001', 'Licencia Software ERP - Anual',               'servicio', 'srv',  1500.00, 'fijo'],
    ['SW002', 'Licencia Software Contabilidad - Mensual',    'servicio', 'srv',   150.00, 'fijo'],
];

/** Unidades necesarias: abreviatura => nombre. 'UND' ya existe. */
$unidades = ['MES' => 'Mes', 'HORA' => 'Hora', 'srv' => 'Servicio'];

echo $aplicar ? "MODO APLICAR — se van a escribir cambios\n" : "MODO SIMULACIÓN — no se escribe nada (usa --aplicar)\n";
echo str_repeat('─', 78) . "\n";

DB::beginTransaction();

try {
    // ── 1) Desactivar el catálogo de ferretería ──────────────────────────────
    $fer = Producto::where('empresa_id', EMPRESA_ID)
        ->where('codigo', 'LIKE', 'FER-%')
        ->where('activo', true)
        ->get();

    echo "\n1) DESACTIVAR ferretería — " . $fer->count() . " producto(s) activos:\n";
    foreach ($fer as $p) {
        echo sprintf("   %-10s %s\n", $p->codigo, $p->nombre);
        $p->update(['activo' => false]);
    }
    if ($fer->isEmpty()) {
        echo "   (ninguno activo: ya estaban desactivados)\n";
    }

    // ── 2) Unidades de medida ────────────────────────────────────────────────
    echo "\n2) UNIDADES DE MEDIDA:\n";
    $porAbrev = [];
    foreach ($unidades as $abrev => $nombre) {
        $um = UnidadMedida::where('empresa_id', EMPRESA_ID)
            ->whereRaw('LOWER(abreviatura) = ?', [mb_strtolower($abrev)])
            ->first();

        if ($um) {
            echo sprintf("   %-6s %-12s ya existe (id %d)\n", $abrev, $nombre, $um->id);
        } else {
            $um = UnidadMedida::create([
                'empresa_id'  => EMPRESA_ID,
                'nombre'      => $nombre,
                'abreviatura' => $abrev,
                'activo'      => true,
            ]);
            echo sprintf("   %-6s %-12s CREADA\n", $abrev, $nombre);
        }
        $porAbrev[$abrev] = $um->id;
    }

    // 'UND' ya está: la resolvemos igual para los dos productos físicos.
    $und = UnidadMedida::where('empresa_id', EMPRESA_ID)
        ->whereRaw("LOWER(abreviatura) = 'und'")->firstOrFail();
    $porAbrev['UND'] = $und->id;
    echo sprintf("   %-6s %-12s ya existe (id %d)\n", 'UND', $und->nombre, $und->id);

    // ── 3) Los 8 productos ───────────────────────────────────────────────────
    echo "\n3) PRODUCTOS DE MACSOFT:\n";
    foreach ($catalogo as [$codigo, $nombre, $tipo, $abrev, $precio, $tipoPrecio]) {
        $esProducto = $tipo === 'producto';

        $p = Producto::where('empresa_id', EMPRESA_ID)->where('codigo', $codigo)->first();
        $accion = $p ? 'ACTUALIZADO' : 'CREADO';

        $atributos = [
            'empresa_id'     => EMPRESA_ID,
            'codigo'         => $codigo,
            'nombre'         => $nombre,
            'tipo'           => $tipo,
            // Para los físicos el precio real vive en la presentación; el del
            // producto queda a 0, igual que hace el formulario del catálogo.
            'tipo_precio'    => $esProducto ? 'fijo' : $tipoPrecio,
            'precio_venta'   => $esProducto ? 0 : $precio,
            'precio_costo'   => 0,
            'incluye_igv'    => true,
            'controla_stock' => false,
            'es_retornable'  => false,
            'activo'         => true,
        ];

        $p = $p ? tap($p)->update($atributos) : Producto::create($atributos);

        // Presentación base: una sola, con el precio de venta real.
        $p->unidades()->updateOrCreate(
            ['unidad_medida_id' => $porAbrev[$abrev]],
            [
                'es_base'           => true,
                'factor_conversion' => 1,
                'tipo_precio'       => $tipoPrecio,
                'precio_venta'      => $precio,
                'precio_costo'      => 0,
                'activo'            => true,
            ],
        );

        echo sprintf(
            "   %-7s %-46s %-9s %-5s %8.2f  %s  %s\n",
            $codigo, mb_substr($nombre, 0, 44), $tipo, $abrev, $precio,
            str_pad($tipoPrecio, 11), $accion,
        );
    }

    echo "\n" . str_repeat('─', 78) . "\n";

    if ($aplicar) {
        DB::commit();
        echo "APLICADO.\n";
    } else {
        DB::rollBack();
        echo "SIMULACIÓN: nada se ha escrito. Repite con --aplicar para hacerlo efectivo.\n";
    }
} catch (\Throwable $e) {
    DB::rollBack();
    echo "\nERROR (nada se ha escrito): " . $e->getMessage() . "\n";
    echo $e->getFile() . ':' . $e->getLine() . "\n";
    exit(1);
}
