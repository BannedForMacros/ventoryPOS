<?php

use App\Models\Stock;
use App\Services\BalanceDiarioService;
use App\Services\VentaService;
use Illuminate\Support\Facades\DB;
use Tests\Support\TestEnv;

/**
 * Bloque 1 — costo de venta congelado con una sola regla.
 *
 * El margen de una venta no puede cambiar con el tiempo: se congela el costo
 * promedio del kardex al vender, editar no lo recongela, un costo desconocido
 * queda como tal, y los reportes nunca lo reemplazan por el costo vivo de hoy
 * (así nació la utilidad de −S/ 2.26 millones del 10/09 en HYC).
 */
beforeEach(function () {
    $this->env = TestEnv::crear();
    $this->actingAs($this->env->admin);
    $this->ventas = app(VentaService::class);
    $this->turno  = $this->env->abrirTurno();
    $this->alm    = $this->env->almacen->id;
});

function ccVender($test, $producto, float $cantidad): \App\Models\Venta
{
    return $test->ventas->crear([
        'tipo_comprobante' => 'ticket',
        'items' => [[
            'producto_id'        => $producto->id,
            'producto_unidad_id' => $producto->unidadBase->id,
            'cantidad'           => $cantidad,
            'precio_unitario'    => 50,
        ]],
        'pagos' => [['metodo_pago_id' => $test->env->metodo('efectivo')->id, 'monto' => $cantidad * 50]],
    ], $test->env->admin, $test->turno);
}

it('al vender congela el costo promedio del kardex, no el de catálogo desactualizado', function () {
    $p = $this->env->crearProducto(['precio_costo' => 10, 'stock_inicial' => 0]);
    Stock::where('producto_id', $p->id)->update(['costo_promedio' => 0]);
    Stock::ajustar($this->alm, $p->id, 100, 6, contexto: ['tipo' => 'entrada', 'fecha' => now()->subHour()]);

    $venta = ccVender($this, $p, 2);

    expect((float) $venta->items->first()->costo_unitario_base)->toBe(6.0);
});

it('mercadería sin ningún costo queda como desconocida, y un servicio cuesta 0', function () {
    $sinCosto = $this->env->crearProducto(['precio_costo' => 0, 'stock_inicial' => 10]);
    $flete    = $this->env->crearProducto(['precio_costo' => 0, 'tipo' => 'servicio']);

    $venta = $this->ventas->crear([
        'tipo_comprobante' => 'ticket',
        'items' => [
            ['producto_id' => $sinCosto->id, 'producto_unidad_id' => $sinCosto->unidadBase->id, 'cantidad' => 1, 'precio_unitario' => 50],
            ['producto_id' => $flete->id, 'producto_unidad_id' => $flete->unidadBase->id, 'cantidad' => 1, 'precio_unitario' => 20],
        ],
        'pagos' => [['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => 70]],
    ], $this->env->admin, $this->turno);

    $items = $venta->items->keyBy('producto_id');
    expect($items[$sinCosto->id]->costo_unitario_base)->toBeNull();
    expect((float) $items[$flete->id]->costo_unitario_base)->toBe(0.0);
});

it('editar una venta conserva su costo original aunque el costo promedio haya cambiado', function () {
    $p = $this->env->crearProducto(['precio_costo' => 0, 'stock_inicial' => 0]);
    Stock::ajustar($this->alm, $p->id, 100, 6, contexto: ['tipo' => 'entrada', 'fecha' => now()->subHour()]);
    $venta = ccVender($this, $p, 2);

    // Después llega mercadería más cara: el costo promedio sube.
    Stock::ajustar($this->alm, $p->id, 100, 20, contexto: ['tipo' => 'entrada', 'fecha' => now()]);

    $this->ventas->actualizar($venta->fresh(), [
        'tipo_comprobante' => 'ticket',
        'items' => [['producto_id' => $p->id, 'producto_unidad_id' => $p->unidadBase->id, 'cantidad' => 5, 'precio_unitario' => 50]],
        'pagos' => [['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => 250]],
    ], $this->env->admin);

    expect((float) $venta->fresh()->items->first()->costo_unitario_base)->toBe(6.0);
});

it('los reportes completan un costo faltante con el del kardex de esa venta, nunca con el costo vivo de hoy', function () {
    $p = $this->env->crearProducto(['precio_costo' => 0, 'stock_inicial' => 0]);
    Stock::ajustar($this->alm, $p->id, 10, 5, contexto: ['tipo' => 'entrada', 'fecha' => now()->subHours(2)]);
    $venta = ccVender($this, $p, 4);

    // Línea histórica sin costo congelado + costo vivo explotado (el caso del ladrillo).
    DB::table('venta_items')->where('venta_id', $venta->id)->update(['costo_unitario_base' => 0]);
    Stock::where('almacen_id', $this->alm)->where('producto_id', $p->id)->update(['costo_promedio' => 2268.09]);

    $metricas = app(BalanceDiarioService::class)->calcularMetricas($this->env->empresa->id, now()->toDateString());

    expect($metricas['costo_dia'])->toBe(20.0);   // 4 × S/ 5, no 4 × S/ 2,268.09
});

it('el comando recupera costos históricos del kardex y marca los desconocidos', function () {
    $conKardex = $this->env->crearProducto(['precio_costo' => 0, 'stock_inicial' => 0]);
    Stock::ajustar($this->alm, $conKardex->id, 10, 7, contexto: ['tipo' => 'entrada', 'fecha' => now()->subHours(2)]);
    $v1 = ccVender($this, $conKardex, 3);

    $sinKardex = $this->env->crearProducto(['precio_costo' => 0, 'stock_inicial' => 10]);
    $v2 = ccVender($this, $sinKardex, 1);

    DB::table('venta_items')->whereIn('venta_id', [$v1->id, $v2->id])->update(['costo_unitario_base' => 0]);

    $this->artisan('ventas:recuperar-costos', ['--empresa' => $this->env->empresa->id, '--simular' => true])->assertSuccessful();
    expect((float) DB::table('venta_items')->where('venta_id', $v1->id)->value('costo_unitario_base'))->toBe(0.0);

    $this->artisan('ventas:recuperar-costos', ['--empresa' => $this->env->empresa->id])->assertSuccessful();
    expect((float) DB::table('venta_items')->where('venta_id', $v1->id)->value('costo_unitario_base'))->toBe(7.0);
    expect(DB::table('venta_items')->where('venta_id', $v2->id)->value('costo_unitario_base'))->toBeNull();
});
