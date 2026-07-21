<?php

use App\Models\Cliente;
use App\Models\ClienteAnticipo;
use App\Models\Stock;
use App\Services\VentaService;
use Tests\Support\TestEnv;

/**
 * Pendiente por entregar (caso Jibaja): el cliente paga la venta completa pero
 * se lleva solo parte. El POS crea el anticipo material multi-producto y el
 * stock pendiente sale del almacén recién al registrar cada entrega.
 */
beforeEach(function () {
    $this->env = TestEnv::crear();
    $this->turno = $this->env->abrirTurno();
    $this->service = app(VentaService::class);
    $this->actingAs($this->env->admin);

    $this->cliente = Cliente::create([
        'empresa_id'       => $this->env->empresa->id,
        'nombres'          => 'Constructora',
        'apellidos'        => 'Jibaja',
        'tipo_documento'   => 'DNI',
        'numero_documento' => '12345678',
        'activo'           => true,
    ]);
});

/** Venta de 10 fierros + 4 tubos: se lleva 3 fierros y 4 tubos; quedan 7 fierros. */
function ventaConPendiente($env, $service, $turno, $cliente): array
{
    $fierro = $env->crearProducto(['precio_venta' => 20, 'stock_inicial' => 50]);
    $tubo   = $env->crearProducto(['precio_venta' => 10, 'stock_inicial' => 30]);

    $venta = $service->crear([
        'tipo_comprobante'       => 'ticket',
        'cliente_id'             => $cliente->id,
        'entrega_pendiente'      => true,
        'fecha_entrega_estimada' => now()->addDays(3)->toDateString(),
        'items' => [
            [
                'producto_id'        => $fierro->id,
                'producto_unidad_id' => $fierro->unidadBase->id,
                'cantidad'           => 10,
                'precio_unitario'    => 20,
                'cantidad_pendiente' => 7, // se lleva 3
            ],
            [
                'producto_id'        => $tubo->id,
                'producto_unidad_id' => $tubo->unidadBase->id,
                'cantidad'           => 4,
                'precio_unitario'    => 10,
                'cantidad_pendiente' => 0, // se lleva todo
            ],
        ],
        'pagos' => [[
            'metodo_pago_id' => $env->metodo('efectivo')->id,
            'monto'          => 240, // paga TODO
        ]],
    ], $env->admin, $turno);

    return [$venta, $fierro, $tubo];
}

it('crea el anticipo material multi-producto y solo descuenta el stock que se lleva', function () {
    [$venta, $fierro, $tubo] = ventaConPendiente($this->env, $this->service, $this->turno, $this->cliente);

    // Stock: fierro 50 - 3 (solo lo llevado) = 47; tubo 30 - 4 = 26.
    expect((float) Stock::where('producto_id', $fierro->id)->first()->cantidad)->toBe(47.0);
    expect((float) Stock::where('producto_id', $tubo->id)->first()->cantidad)->toBe(26.0);

    // Anticipo automático vinculado a la venta, con SOLO el ítem pendiente.
    $anticipo = ClienteAnticipo::where('venta_id', $venta->id)->first();
    expect($anticipo)->not->toBeNull();
    expect($anticipo->estado)->toBe('activo');
    expect($anticipo->tipo_valorizacion)->toBe('material');
    expect($anticipo->cliente_id)->toBe($this->cliente->id);
    expect((float) $anticipo->monto)->toBe(140.0);          // 7 × 20 pagados
    expect((float) $anticipo->saldo)->toBe(140.0);
    expect($anticipo->fecha_entrega_estimada->toDateString())->toBe(now()->addDays(3)->toDateString());
    expect($anticipo->items)->toHaveCount(1);
    expect((float) $anticipo->items->first()->cantidad_pendiente)->toBe(7.0);

    // Pasivo a hoy: 7 × precio del día (20) = 140.
    expect($anticipo->valorPasivo())->toBe(140.0);
});

it('entrega parcial con fecha: baja el pendiente, descuenta stock y deja el resto para después', function () {
    [$venta, $fierro] = ventaConPendiente($this->env, $this->service, $this->turno, $this->cliente);
    $anticipo = ClienteAnticipo::where('venta_id', $venta->id)->with('items')->first();
    $item     = $anticipo->items->first();

    // Primera entrega: "solo te doy 4, lo demás lo dejamos", con fecha propia.
    $this->post(route('finanzas.anticipos.aplicar', $anticipo), [
        'fecha' => now()->addDay()->toDateString(),
        'items' => [['id' => $item->id, 'cantidad' => 4]],
    ])->assertSessionHasNoErrors();

    $anticipo->refresh();
    expect($anticipo->estado)->toBe('activo');                                   // aún quedan 3
    expect((float) $anticipo->items()->first()->cantidad_pendiente)->toBe(3.0);
    expect((float) $anticipo->saldo)->toBe(60.0);                                // 140 - 4×20
    expect((float) Stock::where('producto_id', $fierro->id)->first()->cantidad)->toBe(43.0); // 47 - 4

    $aplicacion = $anticipo->aplicaciones()->with('items')->first();
    expect($aplicacion->fecha->toDateString())->toBe(now()->addDay()->toDateString());
    expect($aplicacion->items)->toHaveCount(1);

    // Segunda entrega: los 3 restantes → anticipo saldado.
    $this->post(route('finanzas.anticipos.aplicar', $anticipo), [
        'fecha' => now()->addDays(5)->toDateString(),
        'items' => [['id' => $item->id, 'cantidad' => 3]],
    ])->assertSessionHasNoErrors();

    $anticipo->refresh();
    expect($anticipo->estado)->toBe('aplicado');
    expect((float) $anticipo->saldo)->toBe(0.0);
    expect((float) Stock::where('producto_id', $fierro->id)->first()->cantidad)->toBe(40.0); // entregado todo
});

it('rechaza entregar más de lo pendiente', function () {
    [$venta] = ventaConPendiente($this->env, $this->service, $this->turno, $this->cliente);
    $anticipo = ClienteAnticipo::where('venta_id', $venta->id)->with('items')->first();

    $this->post(route('finanzas.anticipos.aplicar', $anticipo), [
        'fecha' => now()->toDateString(),
        'items' => [['id' => $anticipo->items->first()->id, 'cantidad' => 8]], // pendiente: 7
    ])->assertSessionHasErrors();

    expect((float) $anticipo->fresh()->saldo)->toBe(140.0); // nada cambió
});

it('anular la venta restaura solo el stock entregado y anula el anticipo', function () {
    [$venta, $fierro, $tubo] = ventaConPendiente($this->env, $this->service, $this->turno, $this->cliente);

    $this->service->anular($venta, $this->env->admin);

    // Fierro: salieron 3 (llevados), los 7 pendientes nunca salieron → vuelve a 50 sin duplicar.
    expect((float) Stock::where('producto_id', $fierro->id)->first()->cantidad)->toBe(50.0);
    expect((float) Stock::where('producto_id', $tubo->id)->first()->cantidad)->toBe(30.0);

    $anticipo = ClienteAnticipo::where('venta_id', $venta->id)->first();
    expect($anticipo->estado)->toBe('anulado');
});

it('editar la venta permite cambiar el pendiente: anula el anticipo anterior, crea el nuevo y ajusta stock', function () {
    [$venta, $fierro] = ventaConPendiente($this->env, $this->service, $this->turno, $this->cliente);
    $anticipoOriginal = ClienteAnticipo::where('venta_id', $venta->id)->first();

    // Al vender: fierro 50 - 3 llevados = 47 (7 pendientes retenidos).
    expect((float) Stock::where('producto_id', $fierro->id)->first()->cantidad)->toBe(47.0);

    // Edición: ahora se lleva 8 y deja solo 2 pendientes.
    $this->service->actualizar($venta, [
        'tipo_comprobante'  => 'ticket',
        'cliente_id'        => $this->cliente->id,
        'entrega_pendiente' => true,
        'items' => [[
            'producto_id'        => $fierro->id,
            'producto_unidad_id' => $fierro->unidadBase->id,
            'cantidad'           => 10,
            'precio_unitario'    => 20,
            'cantidad_pendiente' => 2,
        ]],
        'pagos' => [['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => 200]],
    ], $this->env->admin);

    // Stock: salieron 8 → 50 - 8 = 42.
    expect((float) Stock::where('producto_id', $fierro->id)->first()->cantidad)->toBe(42.0);

    // El anticipo viejo quedó anulado y hay uno nuevo activo con 2 pendientes.
    expect($anticipoOriginal->fresh()->estado)->toBe('anulado');
    $nuevo = ClienteAnticipo::where('venta_id', $venta->id)->where('estado', 'activo')->first();
    expect($nuevo)->not->toBeNull();
    expect($nuevo->items)->toHaveCount(1);
    expect((float) $nuevo->items->first()->cantidad_pendiente)->toBe(2.0);
    expect((float) $nuevo->monto)->toBe(40.0); // 2 × 20
});

it('editar la venta puede QUITAR el pendiente por completo (todo entregado) devolviendo consistencia al stock', function () {
    [$venta, $fierro] = ventaConPendiente($this->env, $this->service, $this->turno, $this->cliente);

    // Edición sin pendiente: se lo llevó todo.
    $this->service->actualizar($venta, [
        'tipo_comprobante' => 'ticket',
        'cliente_id'       => $this->cliente->id,
        'items' => [[
            'producto_id'        => $fierro->id,
            'producto_unidad_id' => $fierro->unidadBase->id,
            'cantidad'           => 10,
            'precio_unitario'    => 20,
        ]],
        'pagos' => [['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => 200]],
    ], $this->env->admin);

    // Stock: salieron los 10 → 50 - 10 = 40. Sin anticipos activos.
    expect((float) Stock::where('producto_id', $fierro->id)->first()->cantidad)->toBe(40.0);
    expect(ClienteAnticipo::where('venta_id', $venta->id)->where('estado', 'activo')->exists())->toBeFalse();
});

it('bloquea editar una venta cuyo pendiente ya tiene entregas registradas', function () {
    [$venta, $fierro] = ventaConPendiente($this->env, $this->service, $this->turno, $this->cliente);
    $anticipo = ClienteAnticipo::where('venta_id', $venta->id)->with('items')->first();

    // Se registra una entrega parcial (2 de 7).
    $this->post(route('finanzas.anticipos.aplicar', $anticipo), [
        'fecha' => now()->toDateString(),
        'items' => [['id' => $anticipo->items->first()->id, 'cantidad' => 2]],
    ])->assertSessionHasNoErrors();

    // Ahora la edición se bloquea: el histórico de despachos quedaría desalineado.
    $this->service->actualizar($venta, [
        'tipo_comprobante' => 'ticket',
        'cliente_id'       => $this->cliente->id,
        'items' => [[
            'producto_id'        => $fierro->id,
            'producto_unidad_id' => $fierro->unidadBase->id,
            'cantidad'           => 1,
            'precio_unitario'    => 20,
        ]],
        'pagos' => [['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => 20]],
    ], $this->env->admin);
})->throws(Symfony\Component\HttpKernel\Exception\HttpException::class);

it('la venta sin flag entrega_pendiente ignora cantidad_pendiente y no crea anticipo', function () {
    $fierro = $this->env->crearProducto(['precio_venta' => 20, 'stock_inicial' => 50]);

    $venta = $this->service->crear([
        'tipo_comprobante' => 'ticket',
        'cliente_id'       => $this->cliente->id,
        'items' => [[
            'producto_id'        => $fierro->id,
            'producto_unidad_id' => $fierro->unidadBase->id,
            'cantidad'           => 5,
            'precio_unitario'    => 20,
            'cantidad_pendiente' => 3, // sin flag → se ignora
        ]],
        'pagos' => [['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => 100]],
    ], $this->env->admin, $this->turno);

    expect((float) Stock::where('producto_id', $fierro->id)->first()->cantidad)->toBe(45.0);
    expect(ClienteAnticipo::where('venta_id', $venta->id)->exists())->toBeFalse();
});
