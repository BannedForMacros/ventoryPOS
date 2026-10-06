<?php

use App\Models\Devolucion;
use App\Services\DevolucionService;
use App\Services\VentaService;
use Tests\Support\TestEnv;

/**
 * Revisión del reporte de devoluciones — P2: el KPI sumaba aprobadas y
 * completadas, pero la serie y los motivos sumaban TODAS (también pendientes y
 * rechazadas), y el "dinero devuelto" contaba las devoluciones sin reembolso.
 * Ahora todo mira el mismo universo y el dinero sigue la regla de UtilidadService.
 */
it('el KPI, la serie y los motivos suman el mismo dinero devuelto', function () {
    $env = TestEnv::crear();
    $this->actingAs($env->admin);
    $turno = $env->abrirTurno();
    $producto = $env->crearProducto(['precio_venta' => 10, 'stock_inicial' => 50]);
    $venta = app(VentaService::class)->crear([
        'tipo_comprobante' => 'ticket',
        'items' => [['producto_id' => $producto->id, 'producto_unidad_id' => $producto->unidadBase->id, 'cantidad' => 9, 'precio_unitario' => 10]],
        'pagos' => [['metodo_pago_id' => $env->metodo('efectivo')->id, 'monto' => 90]],
    ], $env->admin, $turno);

    $devolver = fn (float $cantidad, string $forma) => app(DevolucionService::class)->crear([
        'venta_id' => $venta->id, 'motivo_id' => $env->motivo('producto_equivocado')->id, 'forma_reembolso' => $forma,
        'items' => [['venta_item_id' => $venta->items->first()->id, 'cantidad' => $cantidad, 'estado_producto' => 'bueno', 'restock' => true]],
        'pagos' => $forma === 'sin_reembolso' ? [] : [['metodo_pago_id' => $env->metodo('efectivo')->id, 'monto' => $cantidad * 10]],
    ], $env->admin, $turno);

    $devolver(2, 'efectivo');                                   // 20 devueltos de verdad
    $devolver(1, 'sin_reembolso');                              // no devolvió dinero
    Devolucion::whereKey($devolver(3, 'efectivo')->id)->update(['estado' => 'rechazada']); // no ocurrió

    $props = $this->get(route('reportes.devoluciones'))->viewData('page')['props'];

    expect((float) $props['kpis']['monto_devuelto'])->toBe(20.0);                    // antes 30
    expect((float) collect($props['serie_diaria'])->sum('monto'))->toBe(20.0);       // antes 60
    expect((float) collect($props['por_motivo'])->sum('total'))->toBe(20.0);         // antes 60
});
