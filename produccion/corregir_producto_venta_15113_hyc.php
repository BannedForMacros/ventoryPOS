<?php

/**
 * HYC (empresa 1097) — venta V-0006 (id 15113, 29/10/2026 08:08, turno #968):
 *
 * Se vendieron 12 "Alambre Puas 200m C&A" (6548) pero lo que salió fue
 * "ALAMBRE PUAS X 200MT PRODAC" (6531). La venta tiene comprobante informado a
 * SUNAT y no se puede editar desde la app. Corrección única pedida por el dueño:
 *
 *  - La línea pasa a Prodac (misma unidad base, mismo precio y cantidad).
 *  - El costo congelado se recalcula con la regla única (CPP de Prodac en el
 *    kardex al momento de la venta).
 *  - El kardex y la tabla stock de AMBOS productos se rearman con el motor
 *    único (KardexService::reconstruirPar), así todo lo posterior al 29/10
 *    queda encadenado.
 *  - El resumen del cierre del turno #968 apunta a Prodac.
 *  - Queda auditado. El anticipo aplicado (S/ 310) es de dinero: no se toca.
 *
 *   php produccion/corregir_producto_venta_15113_hyc.php            (simulación)
 *   php produccion/corregir_producto_venta_15113_hyc.php --aplicar  (escribe)
 */

use App\Models\User;
use App\Models\Venta;
use App\Services\AuditoriaService;
use App\Services\CostoVentaService;
use App\Services\KardexService;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

const EMPRESA = 1097, VENTA = 15113, ITEM = 49074, ALMACEN = 1110;
const ORIGEN = 6548, DESTINO = 6531, UNIDAD_DESTINO = 6531;

$aplicar = in_array('--aplicar', $argv, true);
echo $aplicar ? "MODO APLICAR\n" : "MODO SIMULACIÓN (usa --aplicar para escribir)\n";

$venta = Venta::where('empresa_id', EMPRESA)->findOrFail(VENTA);
$item  = DB::table('venta_items')->where('id', ITEM)->where('venta_id', VENTA)->first();
$prod  = DB::table('productos')->where('empresa_id', EMPRESA)->where('id', DESTINO)->first();
$uni   = DB::table('producto_unidades')->where('id', UNIDAD_DESTINO)->where('producto_id', DESTINO)->first();

if (!$item || (int) $item->producto_id === DESTINO) { echo "La línea ya está en Prodac: nada que hacer.\n"; exit(0); }
if ($venta->numero !== 'V-0006' || $venta->estado !== 'completada' || (int) $item->producto_id !== ORIGEN
    || (float) $item->factor_conversion !== 1.0 || !$prod || !$uni || (float) $uni->factor_conversion !== 1.0) {
    echo "Los datos no son los esperados: no se toca nada.\n"; exit(1);
}

$stockDe = fn () => DB::table('stock')->where('almacen_id', ALMACEN)->whereIn('producto_id', [ORIGEN, DESTINO])
    ->pluck('cantidad', 'producto_id')->map(fn ($c) => (float) $c)->all();
$antes = $stockDe();

$admin = User::where('empresa_id', EMPRESA)->whereHas('rol', fn ($q) => $q->where('es_admin', true))->orderBy('id')->firstOrFail();

DB::beginTransaction();

DB::table('venta_items')->where('id', ITEM)->update([
    'producto_id'        => DESTINO,
    'producto_unidad_id' => UNIDAD_DESTINO,
    'producto_nombre'    => $prod->nombre,
    'updated_at'         => now(),
]);

$kardex = app(KardexService::class);
$res = [
    ORIGEN  => $kardex->reconstruirPar(ALMACEN, ORIGEN),
    DESTINO => $kardex->reconstruirPar(ALMACEN, DESTINO),
];

// Costo congelado con la regla única, leído del kardex ya rearmado.
$costo = app(CostoVentaService::class)->paraVender(App\Models\Producto::find(DESTINO), ALMACEN, $venta->fecha_venta);
DB::table('venta_items')->where('id', ITEM)->update(['costo_unitario_base' => $costo]);

// Resumen del cierre del turno: lo vendido y el stock con que cerró, ya en Prodac.
$stockCierre = DB::table('movimientos_inventario')->where('almacen_id', ALMACEN)->where('producto_id', DESTINO)
    ->where('fecha', '<=', '2026-10-29 19:35:02')->orderByDesc('fecha')->orderByDesc('id')->value('saldo_cantidad');
$tcp = DB::table('turno_cierre_productos')->where('turno_id', $venta->turno_id)->where('producto_id', ORIGEN)->first();
$yaHayDestino = DB::table('turno_cierre_productos')->where('turno_id', $venta->turno_id)->where('producto_id', DESTINO)->exists();
if ($tcp && !$yaHayDestino && (float) $tcp->cantidad_vendida === (float) $item->cantidad) {
    DB::table('turno_cierre_productos')->where('id', $tcp->id)->update([
        'producto_id'     => DESTINO,
        'producto_nombre' => $prod->nombre,
        'stock_final'     => $stockCierre,
        'updated_at'      => now(),
    ]);
}

AuditoriaService::log('venta.producto_corregido', $venta, [
    'motivo'  => 'Se registró C&A pero salió Prodac (corrección única pedida por el dueño; comprobante ya en SUNAT)',
    'item_id' => ITEM,
    'antes'   => ['producto_id' => ORIGEN, 'producto' => $item->producto_nombre, 'costo_unitario_base' => (float) $item->costo_unitario_base],
    'despues' => ['producto_id' => DESTINO, 'producto' => $prod->nombre, 'costo_unitario_base' => $costo],
    'cantidad'=> (float) $item->cantidad,
], $admin);

$despues = $stockDe();
printf("Costo congelado: %.4f → %.4f  (costo línea %.2f → %.2f, ganancia %.2f → %.2f)\n",
    $item->costo_unitario_base, $costo,
    $item->costo_unitario_base * $item->cantidad_base, $costo * $item->cantidad_base,
    $item->subtotal - $item->costo_unitario_base * $item->cantidad_base, $item->subtotal - $costo * $item->cantidad_base);
foreach ([ORIGEN => 'C&A   ', DESTINO => 'Prodac'] as $id => $n) {
    printf("Stock %s: %8.4f → %8.4f  (kardex cambia: %s)\n", $n, $antes[$id] ?? 0, $despues[$id] ?? 0,
        $res[$id]['kardex_cambia'] ? 'sí' : 'no');
}
echo "Turno #{$venta->turno_id} cierre: " . ($tcp ? "Prodac, stock final {$stockCierre}" : 'sin fila') . "\n";
foreach (DB::table('movimientos_inventario')->where('almacen_id', ALMACEN)->whereIn('producto_id', [ORIGEN, DESTINO])
    ->where('fecha', '>=', '2026-10-28')->orderBy('producto_id')->orderBy('fecha')->orderBy('id')->get() as $m) {
    printf("  kardex %d %s %-6s ref %d  %9.4f  cpp %.4f  saldo %9.4f\n",
        $m->producto_id, $m->fecha, $m->tipo, $m->referencia_id, $m->cantidad, $m->costo_promedio, $m->saldo_cantidad);
}

if ($aplicar) { DB::commit(); echo "APLICADO.\n"; }
else          { DB::rollBack(); echo "SIMULACIÓN: nada se escribió.\n"; }
