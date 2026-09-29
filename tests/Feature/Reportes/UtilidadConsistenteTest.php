<?php

use App\Services\DevolucionService;
use App\Services\VentaService;
use Tests\Support\TestEnv;

/**
 * Reporte de utilidad, dashboard y cierre de mes usan UNA regla
 * (UtilidadService): con una devolución, los tres dan la misma utilidad.
 *
 * 5 und a S/ 10 con costo S/ 6 (bruta 20); vuelven 2 al stock con reembolso:
 * ventas netas 30, costo neto 18, utilidad 12 — como si se hubieran vendido 3.
 */
beforeEach(function () {
    $this->env = TestEnv::crear();
    $this->turno = $this->env->abrirTurno();
    $this->actingAs($this->env->admin);

    $producto = $this->env->crearProducto(['precio_venta' => 10, 'precio_costo' => 6, 'stock_inicial' => 50]);
    $venta = app(VentaService::class)->crear([
        'tipo_comprobante' => 'ticket',
        'items' => [[
            'producto_id'        => $producto->id,
            'producto_unidad_id' => $producto->unidadBase->id,
            'cantidad'           => 5,
            'precio_unitario'    => 10,
        ]],
        'pagos' => [['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => 50]],
    ], $this->env->admin, $this->turno);

    app(DevolucionService::class)->crear([
        'venta_id'        => $venta->id,
        'motivo_id'       => $this->env->motivo('producto_equivocado')->id,
        'forma_reembolso' => 'efectivo',
        'items' => [[
            'venta_item_id'   => $venta->items->first()->id,
            'cantidad'        => 2,
            'estado_producto' => 'bueno',
            'restock'         => true,
        ]],
        'pagos' => [['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => 20]],
    ], $this->env->admin, $this->turno);
});

it('el reporte de utilidad da 12', function () {
    $k = $this->get(route('reportes.utilidad'))->viewData('page')['props']['kpis'];
    expect((float) $k['utilidad_neta'])->toBe(12.0);
});

it('el dashboard da la misma utilidad y cuenta la devolución como informativa', function () {
    $k = $this->get(route('dashboard'))->viewData('page')['props']['kpis'];
    expect((float) $k['utilidad_bruta_mes'])->toBe(12.0);
    expect((float) $k['utilidad_neta_mes'])->toBe(12.0);
    expect($k['devoluciones_mes']['cant'])->toBe(1);
    expect((float) $k['devoluciones_mes']['total'])->toBe(20.0);
});

it('el cierre de mes da la misma utilidad, con ventas y costo netos', function () {
    $k = $this->get(route('reportes.cierre-mes'))->viewData('page')['props']['kpis'];
    expect((float) $k['ventas'])->toBe(50.0);         // lo facturado
    expect((float) $k['ventas_netas'])->toBe(30.0);   // 50 − 20 devueltos
    expect((float) $k['costo'])->toBe(18.0);          // 30 − 12 que volvieron al stock
    expect((float) $k['utilidad_neta'])->toBe(12.0);
    expect($k)->not->toHaveKey('devoluciones');       // ya no se resta como gasto

    // La serie diaria suma lo mismo.
    $serie = collect($this->get(route('reportes.cierre-mes'))->viewData('page')['props']['serie_diaria']);
    expect(round($serie->sum('neta'), 2))->toBe(12.0);
});

it('el PDF del cierre de mes se genera con las ventas netas', function () {
    $this->get(route('reportes.cierre-mes.imprimir'))
        ->assertOk()
        ->assertSee('Ventas netas')
        ->assertDontSee('Devoluciones <span');
});
