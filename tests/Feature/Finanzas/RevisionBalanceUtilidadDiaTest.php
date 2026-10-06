<?php

use App\Services\BalanceDiarioService;
use App\Services\DevolucionService;
use App\Services\UtilidadService;
use App\Services\VentaService;
use Tests\Support\TestEnv;

/**
 * Revisión del balance — P2: la utilidad del día sale de la regla única
 * (UtilidadService). Antes ignoraba las devoluciones: vender 5 y que devuelvan
 * 2 seguía mostrando la ganancia de las 5.
 */
it('la utilidad del día del balance descuenta las devoluciones como el reporte de utilidad', function () {
    $env = TestEnv::crear();
    $this->actingAs($env->admin);
    $turno = $env->abrirTurno();
    $producto = $env->crearProducto(['precio_venta' => 10, 'precio_costo' => 6, 'stock_inicial' => 50]);

    $venta = app(VentaService::class)->crear([
        'tipo_comprobante' => 'ticket',
        'items' => [['producto_id' => $producto->id, 'producto_unidad_id' => $producto->unidadBase->id,
                     'cantidad' => 5, 'precio_unitario' => 10]],
        'pagos' => [['metodo_pago_id' => $env->metodo('efectivo')->id, 'monto' => 50]],
    ], $env->admin, $turno);

    // Devuelven 2 en buen estado y se les reembolsa: vuelven 20 y 12 de costo.
    app(DevolucionService::class)->crear([
        'venta_id' => $venta->id, 'motivo_id' => $env->motivo('producto_equivocado')->id, 'forma_reembolso' => 'efectivo',
        'items' => [['venta_item_id' => $venta->items->first()->id, 'cantidad' => 2, 'estado_producto' => 'bueno', 'restock' => true]],
        'pagos' => [['metodo_pago_id' => $env->metodo('efectivo')->id, 'monto' => 20]],
    ], $env->admin, $turno);

    $hoy = now()->toDateString();
    $b = app(BalanceDiarioService::class)->generar($env->admin, $hoy);
    $u = app(UtilidadService::class)->resumen($env->empresa->id, $hoy, $hoy);

    expect((float) $b->ventas_dia)->toBe(30.0);    // 50 − 20 devueltos
    expect((float) $b->costo_dia)->toBe(18.0);     // 30 − 12 que volvieron al stock
    expect((float) $b->utilidad_dia)->toBe(12.0);  // antes 20
    expect((float) $b->utilidad_dia)->toBe($u['utilidad_neta']);
});
