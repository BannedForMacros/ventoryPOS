<?php

use App\Models\Stock;
use App\Models\Venta;
use App\Services\VentaService;
use Tests\Support\TestEnv;

beforeEach(function () {
    $this->env = TestEnv::crear();
    $this->turno = $this->env->abrirTurno();
    $this->service = app(VentaService::class);
    $this->actingAs($this->env->admin); // auth() para AuditoriaService
});

/**
 * Helper local: arma el payload base que VentaService::crear espera, con un
 * producto y un pago. Los tests overridean lo que necesitan.
 */
function payloadVenta(\App\Models\Producto $producto, \App\Models\MetodoPago $metodo, array $extra = []): array
{
    return array_merge([
        'tipo_comprobante' => 'ticket',
        'items' => [[
            'producto_id'        => $producto->id,
            'producto_unidad_id' => $producto->unidadBase->id,
            'cantidad'           => 2,
            'precio_unitario'    => (float) $producto->precio_venta,
        ]],
        'pagos' => [[
            'metodo_pago_id' => $metodo->id,
            'monto'          => 2 * (float) $producto->precio_venta,
        ]],
    ], $extra);
}

it('registra una venta completa: cabecera, items, pagos y descuento de stock', function () {
    $producto = $this->env->crearProducto(['precio_venta' => 10, 'stock_inicial' => 50, 'incluye_igv' => true]);
    $efectivo = $this->env->metodo('efectivo');

    $venta = $this->service->crear(payloadVenta($producto, $efectivo), $this->env->admin, $this->turno);

    expect($venta->estado)->toBe('completada');
    expect($venta->numero)->toBe('V-0001');
    expect($venta->items)->toHaveCount(1);
    expect($venta->pagos)->toHaveCount(1);
    expect((float) $venta->total)->toBe(20.0);

    // Stock descontado: 50 - 2 = 48
    $stock = Stock::where('almacen_id', $this->env->almacen->id)->where('producto_id', $producto->id)->first();
    expect((float) $stock->cantidad)->toBe(48.0);
});

it('asigna vuelto al método que admite vuelto cuando hay sobrepago en efectivo', function () {
    $producto = $this->env->crearProducto(['precio_venta' => 10]);
    $efectivo = $this->env->metodo('efectivo');

    $payload = payloadVenta($producto, $efectivo);
    $payload['pagos'][0]['monto'] = 50; // total 20, paga 50 → vuelto 30

    $venta = $this->service->crear($payload, $this->env->admin, $this->turno);

    expect((float) $venta->pagos->first()->vuelto)->toBe(30.0);
    expect((float) $venta->total)->toBe(20.0);
});

it('en pago mixto tarjeta + efectivo asigna el vuelto al pago de efectivo', function () {
    $producto  = $this->env->crearProducto(['precio_venta' => 100]); // total = 100
    $tarjeta   = $this->env->metodo('tarjeta_debito');
    $efectivo  = $this->env->metodo('efectivo');

    $venta = $this->service->crear([
        'tipo_comprobante' => 'ticket',
        'items' => [[
            'producto_id'        => $producto->id,
            'producto_unidad_id' => $producto->unidadBase->id,
            'cantidad'           => 1,
            'precio_unitario'    => 100,
        ]],
        'pagos' => [
            ['metodo_pago_id' => $tarjeta->id,  'monto' => 80],
            ['metodo_pago_id' => $efectivo->id, 'monto' => 30], // total pagado 110, vuelto 10
        ],
    ], $this->env->admin, $this->turno);

    $pagosPorMetodo = $venta->pagos->keyBy('metodo_pago_id');
    expect((float) $pagosPorMetodo[$tarjeta->id]->vuelto)->toBe(0.0);
    expect((float) $pagosPorMetodo[$efectivo->id]->vuelto)->toBe(10.0);
});

it('idempotency_key: la segunda llamada con el mismo key devuelve la venta original sin duplicar', function () {
    $producto = $this->env->crearProducto(['precio_venta' => 10, 'stock_inicial' => 50]);
    $efectivo = $this->env->metodo('efectivo');

    $payload = payloadVenta($producto, $efectivo, ['idempotency_key' => 'idem-test-' . uniqid()]);

    $venta1 = $this->service->crear($payload, $this->env->admin, $this->turno);
    $venta2 = $this->service->crear($payload, $this->env->admin, $this->turno);

    expect($venta2->id)->toBe($venta1->id);
    expect(Venta::where('turno_id', $this->turno->id)->count())->toBe(1);

    // Stock se descontó UNA SOLA vez (50 - 2 = 48)
    $stock = Stock::where('almacen_id', $this->env->almacen->id)->where('producto_id', $producto->id)->first();
    expect((float) $stock->cantidad)->toBe(48.0);
});

it('anular venta restaura el stock y marca el estado como anulada', function () {
    $producto = $this->env->crearProducto(['precio_venta' => 10, 'stock_inicial' => 50]);
    $efectivo = $this->env->metodo('efectivo');

    $venta = $this->service->crear(payloadVenta($producto, $efectivo), $this->env->admin, $this->turno);
    expect((float) Stock::where('producto_id', $producto->id)->first()->cantidad)->toBe(48.0);

    $this->service->anular($venta, $this->env->admin);

    expect($venta->fresh()->estado)->toBe('anulada');
    expect((float) Stock::where('producto_id', $producto->id)->first()->cantidad)->toBe(50.0);

    // Auditoría debe quedar registrada
    expect(\App\Models\Auditoria::where('accion', 'venta.anulada')->where('modelo_id', $venta->id)->exists())
        ->toBeTrue();
});

it('anular una venta ya anulada lanza RuntimeException', function () {
    $producto = $this->env->crearProducto(['precio_venta' => 10]);
    $efectivo = $this->env->metodo('efectivo');
    $venta = $this->service->crear(payloadVenta($producto, $efectivo), $this->env->admin, $this->turno);
    $this->service->anular($venta, $this->env->admin);

    $this->service->anular($venta->fresh(), $this->env->admin);
})->throws(RuntimeException::class);

it('numera las ventas correlativamente por turno (V-0001, V-0002, ...)', function () {
    $producto = $this->env->crearProducto(['precio_venta' => 5, 'stock_inicial' => 100]);
    $efectivo = $this->env->metodo('efectivo');

    $v1 = $this->service->crear(payloadVenta($producto, $efectivo), $this->env->admin, $this->turno);
    $v2 = $this->service->crear(payloadVenta($producto, $efectivo), $this->env->admin, $this->turno);
    $v3 = $this->service->crear(payloadVenta($producto, $efectivo), $this->env->admin, $this->turno);

    expect([$v1->numero, $v2->numero, $v3->numero])->toBe(['V-0001', 'V-0002', 'V-0003']);
});
