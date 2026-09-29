<?php

use App\Services\DevolucionService;
use App\Services\VentaService;
use Tests\Support\TestEnv;

/**
 * Detalle de una venta: cuánto dejó, con la misma regla que el reporte de
 * utilidad (costo congelado; una devolución deshace la venta).
 */
beforeEach(function () {
    $this->env = TestEnv::crear();
    $this->turno = $this->env->abrirTurno();
    $this->actingAs($this->env->admin);

    $producto = $this->env->crearProducto(['precio_venta' => 10, 'precio_costo' => 6, 'stock_inicial' => 50]);
    // 5 und a S/ 10 con costo S/ 6 → vendido 50, costo 30, ganancia 20.
    $this->venta = app(VentaService::class)->crear([
        'tipo_comprobante' => 'ticket',
        'items' => [[
            'producto_id'        => $producto->id,
            'producto_unidad_id' => $producto->unidadBase->id,
            'cantidad'           => 5,
            'precio_unitario'    => 10,
        ]],
        'pagos' => [['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => 50]],
    ], $this->env->admin, $this->turno);

    $this->utilidad = fn () => $this->get(route('ventas.show', $this->venta->id))->viewData('page')['props']['utilidad'];
});

it('muestra cuánto dejó la venta y cada línea', function () {
    $u = ($this->utilidad)();

    expect((float) $u['total'])->toBe(50.0);
    expect((float) $u['costo'])->toBe(30.0);
    expect((float) $u['utilidad'])->toBe(20.0);
    expect((float) $u['margen'])->toBe(40.0);

    $linea = $u['items'][$this->venta->items->first()->id];
    expect((float) $linea['utilidad'])->toBe(20.0);
    expect($linea['sin_costo'])->toBeFalse();
});

it('una devolución que vuelve al stock deshace esa parte de la ganancia', function () {
    app(DevolucionService::class)->crear([
        'venta_id'        => $this->venta->id,
        'motivo_id'       => $this->env->motivo('producto_equivocado')->id,
        'forma_reembolso' => 'efectivo',
        'items' => [[
            'venta_item_id'   => $this->venta->items->first()->id,
            'cantidad'        => 2,
            'estado_producto' => 'bueno',
            'restock'         => true,
        ]],
        'pagos' => [['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => 20]],
    ], $this->env->admin, $this->turno);

    $u = ($this->utilidad)();
    expect((float) $u['devuelto'])->toBe(20.0);
    expect((float) $u['recuperado'])->toBe(12.0);
    expect((float) $u['utilidad'])->toBe(12.0); // como si se hubieran vendido 3: 3 × 4
});

it('una venta anulada no muestra utilidad', function () {
    $this->venta->update(['estado' => 'anulada']);
    expect(($this->utilidad)())->toBeNull();
});
