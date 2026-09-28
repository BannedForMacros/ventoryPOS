<?php

/**
 * Corrige el costo de P.MOSCATEL SILVER 700ML X 12 (Vacanostra, código 506039)
 * de S/ 103.87 a S/ 50.00 (28/09/2026, pedido del dueño).
 *
 * El 103.87 vino del Excel de stock inicial de Vacanostra (migración de agosto)
 * y quedó mayor que el precio de venta (80.04): el POS no dejaba venderlo.
 * No tiene ventas y su stock es 0, así que no mueve la utilidad ni el balance
 * de hoy. Se corrige en el ORIGEN para que todo quede coherente:
 *   1. stock_iniciales (inventario inicial del 02/08).
 *   2. La salida del 03/08 (retorno al proveedor) que usó ese costo.
 *   3. La ficha del producto y su presentación.
 *   4. Kardex + stock del producto recalculados con el motor único.
 *
 *   php produccion/corregir_costo_moscatel_vacanostra.php            (simulación)
 *   php produccion/corregir_costo_moscatel_vacanostra.php --aplicar  (escribe)
 */

use App\Models\Producto;
use App\Services\AuditoriaService;
use App\Services\KardexService;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

const EMPRESA  = 1102;   // VACANOSTRA
const CODIGO   = '506039';
const COSTO_OK = 50.00;

$aplicar = in_array('--aplicar', $argv, true);
echo $aplicar ? "MODO APLICAR\n" : "MODO SIMULACIÓN (usa --aplicar para escribir)\n";

$producto = Producto::where('empresa_id', EMPRESA)->where('codigo', CODIGO)->firstOrFail();
echo "Producto #{$producto->id} {$producto->nombre}\n";

$foto = function () use ($producto) {
    return [
        'ficha'        => (float) DB::table('productos')->where('id', $producto->id)->value('precio_costo'),
        'presentacion' => DB::table('producto_unidades')->where('producto_id', $producto->id)->pluck('precio_costo', 'id')->map(fn ($v) => (float) $v)->all(),
        'stock_inicial'=> DB::table('stock_iniciales')->where('producto_id', $producto->id)->pluck('costo', 'id')->map(fn ($v) => (float) $v)->all(),
        'salidas'      => DB::table('salidas_detalle')->where('producto_id', $producto->id)->get(['id', 'salida_id', 'costo_unitario', 'subtotal'])->map(fn ($r) => (array) $r)->all(),
        'stock'        => DB::table('stock')->where('producto_id', $producto->id)->get(['almacen_id', 'cantidad', 'costo_promedio'])->map(fn ($r) => (array) $r)->all(),
        'kardex'       => DB::table('movimientos_inventario')->where('producto_id', $producto->id)->orderBy('id')->get(['fecha', 'tipo', 'cantidad', 'costo_unitario', 'costo_promedio', 'saldo_valorizado'])->map(fn ($r) => (array) $r)->all(),
    ];
};

$antes = $foto();
echo "ANTES:\n" . json_encode($antes, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

DB::beginTransaction();
try {
    DB::table('stock_iniciales')->where('producto_id', $producto->id)->update(['costo' => COSTO_OK, 'updated_at' => now()]);

    $salidas = DB::table('salidas_detalle')->where('producto_id', $producto->id)->get(['id', 'salida_id', 'cantidad']);
    foreach ($salidas as $sd) {
        DB::table('salidas_detalle')->where('id', $sd->id)->update([
            'costo_unitario' => COSTO_OK,
            'subtotal'       => round((float) $sd->cantidad * COSTO_OK, 2),
            'updated_at'     => now(),
        ]);
        // El total de la salida es la suma de sus líneas.
        DB::table('salidas')->where('id', $sd->salida_id)->update([
            'total' => DB::table('salidas_detalle')->where('salida_id', $sd->salida_id)->sum('subtotal'),
        ]);
    }

    DB::table('productos')->where('id', $producto->id)->update(['precio_costo' => COSTO_OK, 'updated_at' => now()]);
    DB::table('producto_unidades')->where('producto_id', $producto->id)->update(['precio_costo' => COSTO_OK, 'updated_at' => now()]);

    $kardex = app(KardexService::class);
    foreach (DB::table('stock')->where('producto_id', $producto->id)->pluck('almacen_id') as $almacenId) {
        $r = $kardex->reconstruirPar((int) $almacenId, $producto->id);
        echo "Kardex almacén {$almacenId}: " . json_encode($r) . "\n";
    }

    AuditoriaService::log('producto.costo_corregido', $producto, [
        'motivo'      => 'Costo del Excel de stock inicial de Vacanostra mayor que el precio de venta; corregido a pedido del dueño',
        'costo_antes' => $antes['ficha'],
        'costo_nuevo' => COSTO_OK,
    ]);

    $despues = $foto();
    echo "DESPUÉS:\n" . json_encode($despues, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";

    if ($aplicar) { DB::commit(); echo "APLICADO.\n"; }
    else          { DB::rollBack(); echo "SIMULACIÓN: nada se escribió.\n"; }
} catch (\Throwable $e) {
    DB::rollBack();
    echo "ERROR (nada se escribió): {$e->getMessage()}\n";
    exit(1);
}
