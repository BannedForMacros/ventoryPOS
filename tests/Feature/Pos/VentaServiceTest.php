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

it('puede pagar una venta con un anticipo de efectivo del cliente', function () {
    $producto = $this->env->crearProducto(['precio_venta' => 50, 'stock_inicial' => 50]);

    $cliente = \App\Models\Cliente::create([
        'empresa_id'       => $this->env->empresa->id,
        'tipo_documento'   => 'DNI',
        'numero_documento' => '12345678',
        'nombres'          => 'Juan',
        'apellidos'        => 'Pérez',
        'activo'           => true,
    ]);

    $anticipo = \App\Models\ClienteAnticipo::create([
        'empresa_id'        => $this->env->empresa->id,
        'cliente_id'        => $cliente->id,
        'user_id'           => $this->env->admin->id,
        'fecha'             => now()->toDateString(),
        'monto'             => 100.00,
        'saldo'             => 100.00,
        'tipo_valorizacion' => 'monto',
        'estado'            => 'activo',
    ]);

    $venta = $this->service->crear([
        'tipo_comprobante' => 'ticket',
        'cliente_id'       => $cliente->id,
        'anticipo_id'      => $anticipo->id,
        'items' => [[
            'producto_id'        => $producto->id,
            'producto_unidad_id' => $producto->unidadBase->id,
            'cantidad'           => 1,
            'precio_unitario'    => 50,
        ]],
        'pagos' => [],
    ], $this->env->admin, $this->turno);

    expect($venta->estado)->toBe('completada');
    expect((float) $venta->total)->toBe(50.0);
    expect((float) $venta->monto_pagado)->toBe(50.0);

    $anticipo->refresh();
    expect((float) $anticipo->saldo)->toBe(50.0);
    expect($anticipo->estado)->toBe('activo');

    $aplicacion = \App\Models\ClienteAnticipoAplicacion::where('venta_id', $venta->id)->first();
    expect($aplicacion)->not->toBeNull();
    expect((float) $aplicacion->monto)->toBe(50.0);
});

it('restaura el saldo del anticipo al anular una venta pagada con anticipo', function () {
    $producto = $this->env->crearProducto(['precio_venta' => 50, 'stock_inicial' => 50]);

    $cliente = \App\Models\Cliente::create([
        'empresa_id'       => $this->env->empresa->id,
        'tipo_documento'   => 'DNI',
        'numero_documento' => '12345678',
        'nombres'          => 'Juan',
        'apellidos'        => 'Pérez',
        'activo'           => true,
    ]);

    $anticipo = \App\Models\ClienteAnticipo::create([
        'empresa_id'        => $this->env->empresa->id,
        'cliente_id'        => $cliente->id,
        'user_id'           => $this->env->admin->id,
        'fecha'             => now()->toDateString(),
        'monto'             => 100.00,
        'saldo'             => 100.00,
        'tipo_valorizacion' => 'monto',
        'estado'            => 'activo',
    ]);

    $venta = $this->service->crear([
        'tipo_comprobante' => 'ticket',
        'cliente_id'       => $cliente->id,
        'anticipo_id'      => $anticipo->id,
        'items' => [[
            'producto_id'        => $producto->id,
            'producto_unidad_id' => $producto->unidadBase->id,
            'cantidad'           => 1,
            'precio_unitario'    => 50,
        ]],
        'pagos' => [],
    ], $this->env->admin, $this->turno);

    $this->service->anular($venta, $this->env->admin);

    $anticipo->refresh();
    expect((float) $anticipo->saldo)->toBe(100.0);
    expect($anticipo->estado)->toBe('activo');
    expect(\App\Models\ClienteAnticipoAplicacion::where('venta_id', $venta->id)->count())->toBe(0);
});

it('paga una venta con varios anticipos: agota los antiguos y el último conserva el sobrante', function () {
    $producto = $this->env->crearProducto(['precio_venta' => 50, 'stock_inicial' => 50]);
    $cliente = \App\Models\Cliente::create([
        'empresa_id' => $this->env->empresa->id, 'tipo_documento' => 'DNI', 'numero_documento' => '12345679',
        'nombres' => 'Ana', 'apellidos' => 'Ruiz', 'activo' => true,
    ]);
    $nuevo = fn (float $monto, string $fecha) => \App\Models\ClienteAnticipo::create([
        'empresa_id' => $this->env->empresa->id, 'cliente_id' => $cliente->id, 'user_id' => $this->env->admin->id,
        'fecha' => $fecha, 'monto' => $monto, 'saldo' => $monto, 'tipo_valorizacion' => 'monto', 'estado' => 'activo',
    ]);
    // Se mandan desordenados a propósito: el orden lo pone la fecha.
    $reciente = $nuevo(100, now()->toDateString());
    $antiguo  = $nuevo(30, now()->subDays(5)->toDateString());
    $medio    = $nuevo(40, now()->subDays(2)->toDateString());

    $venta = $this->service->crear([
        'tipo_comprobante' => 'ticket',
        'cliente_id'       => $cliente->id,
        'anticipo_ids'     => [$reciente->id, $medio->id, $antiguo->id],
        'items' => [[
            'producto_id' => $producto->id, 'producto_unidad_id' => $producto->unidadBase->id,
            'cantidad' => 1, 'precio_unitario' => 50,
        ]],
        'pagos' => [],
    ], $this->env->admin, $this->turno);

    expect((float) $venta->monto_pagado)->toBe(50.0);
    expect([(float) $antiguo->fresh()->saldo, $antiguo->fresh()->estado])->toBe([0.0, 'aplicado']);
    expect([(float) $medio->fresh()->saldo, $medio->fresh()->estado])->toBe([20.0, 'activo']);
    // No hacía falta: queda intacto y sin aplicación.
    expect((float) $reciente->fresh()->saldo)->toBe(100.0);
    expect(\App\Models\ClienteAnticipoAplicacion::where('venta_id', $venta->id)->orderBy('cliente_anticipo_id')->pluck('monto', 'cliente_anticipo_id')->map(fn ($m) => (float) $m)->all())
        ->toBe([$antiguo->id => 30.0, $medio->id => 20.0]);

    // Anular devuelve el saldo a cada uno.
    $this->service->anular($venta, $this->env->admin);
    expect([(float) $antiguo->fresh()->saldo, $antiguo->fresh()->estado])->toBe([30.0, 'activo']);
    expect((float) $medio->fresh()->saldo)->toBe(40.0);
});

it('la venta HTTP acepta varios anticipos que juntos cubren el total sin otro pago', function () {
    $producto = $this->env->crearProducto(['precio_venta' => 50, 'stock_inicial' => 50]);
    $cliente = \App\Models\Cliente::create([
        'empresa_id' => $this->env->empresa->id, 'tipo_documento' => 'DNI', 'numero_documento' => '12345670',
        'nombres' => 'Luis', 'apellidos' => 'Soto', 'activo' => true,
    ]);
    $ids = collect([20, 35])->map(fn ($m) => \App\Models\ClienteAnticipo::create([
        'empresa_id' => $this->env->empresa->id, 'cliente_id' => $cliente->id, 'user_id' => $this->env->admin->id,
        'fecha' => now()->toDateString(), 'monto' => $m, 'saldo' => $m, 'tipo_valorizacion' => 'monto', 'estado' => 'activo',
    ])->id)->all();

    $this->post(route('ventas.store'), [
        'tipo_comprobante' => 'ticket',
        'cliente_id'       => $cliente->id,
        'anticipo_ids'     => $ids,
        'idempotency_key'  => 'multi-anticipo-' . uniqid(),
        'items' => [[
            'producto_id' => $producto->id, 'producto_unidad_id' => $producto->unidadBase->id,
            'cantidad' => 1, 'precio_unitario' => 50,
        ]],
        'pagos' => [],
    ])->assertSessionHasNoErrors();

    expect(\App\Models\ClienteAnticipo::whereIn('id', $ids)->orderBy('id')->pluck('saldo')->map(fn ($s) => (float) $s)->all())
        ->toBe([0.0, 5.0]);
});
