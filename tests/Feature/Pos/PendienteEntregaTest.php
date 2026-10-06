<?php

use App\Models\Cliente;
use App\Models\ClienteAnticipo;
use App\Models\ClienteAnticipoCancelacion;
use App\Models\CuentaMovimiento;
use App\Models\Stock;
use App\Models\Venta;
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
        'fecha' => now()->subDays(2)->toDateString(),
        'items' => [['id' => $item->id, 'cantidad' => 4]],
    ])->assertSessionHasNoErrors();

    $anticipo->refresh();
    expect($anticipo->estado)->toBe('activo');                                   // aún quedan 3
    expect((float) $anticipo->items()->first()->cantidad_pendiente)->toBe(3.0);
    expect((float) $anticipo->saldo)->toBe(60.0);                                // 140 - 4×20
    expect((float) Stock::where('producto_id', $fierro->id)->first()->cantidad)->toBe(43.0); // 47 - 4

    $aplicacion = $anticipo->aplicaciones()->with('items')->first();
    expect($aplicacion->fecha->toDateString())->toBe(now()->subDays(2)->toDateString());
    expect($aplicacion->items)->toHaveCount(1);

    // Segunda entrega: los 3 restantes → anticipo saldado.
    $this->post(route('finanzas.anticipos.aplicar', $anticipo), [
        'fecha' => now()->subDay()->toDateString(),
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

it('una venta a crédito SIN saldar sí puede quedar pendiente por entregar', function () {
    $fierro = $this->env->crearProducto(['precio_venta' => 20, 'stock_inicial' => 50]);

    // Venta a crédito: total 200, pago inicial 50 → saldo 150.
    $venta = $this->service->crear([
        'tipo_comprobante' => 'ticket',
        'cliente_id'       => $this->cliente->id,
        'es_credito'       => true,
        'items' => [[
            'producto_id'        => $fierro->id,
            'producto_unidad_id' => $fierro->unidadBase->id,
            'cantidad'           => 10,
            'precio_unitario'    => 20,
        ]],
        'pagos' => [['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => 50]],
    ], $this->env->admin, $this->turno);

    expect((float) $venta->saldo_pendiente)->toBe(150.0);

    // Editar y marcar pendiente por entregar: ahora SÍ se permite (27/09/2026,
    // pedido del negocio: vender al crédito y entregar después).
    $response = $this->from(route('pos.index', ['venta_id' => $venta->id]))
        ->put(route('ventas.update', $venta->id), [
            'tipo_comprobante'       => 'ticket',
            'cliente_id'             => $this->cliente->id,
            'es_credito'             => true,
            'entrega_pendiente'      => true,
            'fecha_entrega_estimada' => now()->addDays(3)->toDateString(),
            'items' => [[
                'producto_id'        => $fierro->id,
                'producto_unidad_id' => $fierro->unidadBase->id,
                'cantidad'           => 10,
                'precio_unitario'    => 20,
                'cantidad_pendiente' => 7,
            ]],
            'pagos' => [['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => 50]],
        ]);

    $response->assertSessionHasNoErrors();

    // El pedido registra el valor COMPLETO de lo que falta entregar (7 × 20),
    // igual que al contado: es la obligación de entregar, cuadra el balance
    // (la mercadería sigue en stock y su valor está en Cuentas por cobrar).
    $anticipo = ClienteAnticipo::where('venta_id', $venta->id)->where('estado', 'activo')->with('items')->firstOrFail();
    expect((float) $anticipo->items->sum('cantidad_pendiente'))->toBe(7.0)
        ->and((float) $anticipo->saldo)->toBe(140.0);
});

it('una venta a crédito SALDADA sí puede marcarse como pendiente por entregar', function () {
    $fierro = $this->env->crearProducto(['precio_venta' => 20, 'stock_inicial' => 50]);

    // Venta a crédito: total 200, pago inicial 100 → saldo 100.
    $venta = $this->service->crear([
        'tipo_comprobante' => 'ticket',
        'cliente_id'       => $this->cliente->id,
        'es_credito'       => true,
        'items' => [[
            'producto_id'        => $fierro->id,
            'producto_unidad_id' => $fierro->unidadBase->id,
            'cantidad'           => 10,
            'precio_unitario'    => 20,
        ]],
        'pagos' => [['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => 100]],
    ], $this->env->admin, $this->turno);

    // Abonar los 100 restantes para saldarla.
    $this->post(route('finanzas.cxc.abonar', $venta->id), [
        'monto'          => 100,
        'fecha'          => now()->toDateString(),
        'metodo_pago_id' => $this->env->metodo('efectivo')->id,
        'turno_id'       => $this->turno->id,
    ])->assertSessionHasNoErrors();

    $venta->refresh();
    expect((float) $venta->saldo_pendiente)->toBe(0.0);

    // Editar y marcar pendiente por entregar → debe permitir.
    $response = $this->from(route('pos.index', ['venta_id' => $venta->id]))
        ->put(route('ventas.update', $venta->id), [
            'tipo_comprobante'       => 'ticket',
            'cliente_id'             => $this->cliente->id,
            'es_credito'             => true,
            'entrega_pendiente'      => true,
            'fecha_entrega_estimada' => now()->addDays(3)->toDateString(),
            'items' => [[
                'producto_id'        => $fierro->id,
                'producto_unidad_id' => $fierro->unidadBase->id,
                'cantidad'           => 10,
                'precio_unitario'    => 20,
                'cantidad_pendiente' => 7,
            ]],
            'pagos' => [['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => 100]],
        ]);

    $response->assertSessionHasNoErrors();
    $response->assertRedirect();

    // Se creó el anticipo material y solo salieron 3 unidades del stock.
    $anticipo = ClienteAnticipo::where('venta_id', $venta->id)->where('estado', 'activo')->first();
    expect($anticipo)->not->toBeNull();
    expect((float) $anticipo->monto)->toBe(140.0); // 7 × 20
    expect((float) Stock::where('producto_id', $fierro->id)->first()->cantidad)->toBe(47.0); // 50 - 3
});

/** Diferencia entre el stock vivo y el que reconstruye el kardex (debe mantenerse al editar). */
function desfaseKardex($env, int $productoId): float
{
    $r = app(App\Services\KardexService::class)->reconstruirPar($env->almacen->id, $productoId, true);

    return round($r['cantidad_despues'] - $r['cantidad_antes'], 4);
}

it('edita una venta con entregas registradas: conserva lo entregado y ajusta precio y pendiente', function () {
    [$venta, $fierro, $tubo] = ventaConPendiente($this->env, $this->service, $this->turno, $this->cliente);
    $anticipo = ClienteAnticipo::where('venta_id', $venta->id)->with('items')->first();

    // Se entregan 2 de los 7 pendientes → stock fierro 50 - 3 - 2 = 45.
    $this->post(route('finanzas.anticipos.aplicar', $anticipo), [
        'fecha' => now()->toDateString(),
        'items' => [['id' => $anticipo->items->first()->id, 'cantidad' => 2]],
    ])->assertSessionHasNoErrors();
    expect((float) Stock::where('producto_id', $fierro->id)->first()->cantidad)->toBe(45.0);
    $desfase = desfaseKardex($this->env, $fierro->id);

    // Edición: el fierro sube a S/ 25 y queda pendiente lo mismo (5).
    $this->service->actualizar($venta, [
        'tipo_comprobante'  => 'ticket',
        'cliente_id'        => $this->cliente->id,
        'entrega_pendiente' => true,
        'items' => [
            ['producto_id' => $fierro->id, 'producto_unidad_id' => $fierro->unidadBase->id,
             'cantidad' => 10, 'precio_unitario' => 25, 'cantidad_pendiente' => 5],
            ['producto_id' => $tubo->id, 'producto_unidad_id' => $tubo->unidadBase->id,
             'cantidad' => 4, 'precio_unitario' => 10, 'cantidad_pendiente' => 0],
        ],
        'pagos' => [['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => 290]],
    ], $this->env->admin);

    // El mismo anticipo sigue vivo con su entrega; la línea re-vinculada.
    expect(ClienteAnticipo::where('venta_id', $venta->id)->count())->toBe(1);
    $anticipo->refresh();
    $linea = $anticipo->items()->first();
    expect($anticipo->estado)->toBe('activo');
    expect($anticipo->aplicaciones()->count())->toBe(1);
    expect((float) $linea->cantidad)->toBe(7.0);            // 2 entregados + 5 pendientes
    expect((float) $linea->cantidad_pendiente)->toBe(5.0);
    expect((float) $linea->precio_unitario)->toBe(25.0);
    expect((float) $anticipo->saldo)->toBe(125.0);          // 5 × 25
    expect($linea->venta_item_id)->toBe($venta->fresh()->items->firstWhere('producto_id', $fierro->id)->id);

    // Stock intacto y el kardex lo reconstruye igual que antes de editar.
    expect((float) Stock::where('producto_id', $fierro->id)->first()->cantidad)->toBe(45.0);
    expect((float) Stock::where('producto_id', $tubo->id)->first()->cantidad)->toBe(26.0);
    expect(desfaseKardex($this->env, $fierro->id))->toBe($desfase);
    expect((float) $venta->fresh()->total)->toBe(290.0);
});

it('edita una venta con el pendiente ya entregado completo (anticipo aplicado) y la cantidad extra sale al vender', function () {
    [$venta, $fierro, $tubo] = ventaConPendiente($this->env, $this->service, $this->turno, $this->cliente);
    $anticipo = ClienteAnticipo::where('venta_id', $venta->id)->with('items')->first();
    $this->post(route('finanzas.anticipos.aplicar', $anticipo), [
        'fecha' => now()->toDateString(),
        'items' => [['id' => $anticipo->items->first()->id, 'cantidad' => 7]],
    ])->assertSessionHasNoErrors();
    expect($anticipo->fresh()->estado)->toBe('aplicado');
    $desfase = desfaseKardex($this->env, $fierro->id);

    // Sin pendiente: 12 fierros (2 más, se los lleva ahora).
    $this->service->actualizar($venta, [
        'tipo_comprobante' => 'ticket',
        'cliente_id'       => $this->cliente->id,
        'items' => [
            ['producto_id' => $fierro->id, 'producto_unidad_id' => $fierro->unidadBase->id, 'cantidad' => 12, 'precio_unitario' => 20],
            ['producto_id' => $tubo->id, 'producto_unidad_id' => $tubo->unidadBase->id, 'cantidad' => 4, 'precio_unitario' => 10],
        ],
        'pagos' => [['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => 280]],
    ], $this->env->admin);

    $anticipo->refresh();
    expect($anticipo->estado)->toBe('aplicado');
    expect((float) $anticipo->saldo)->toBe(0.0);
    expect((float) $anticipo->items()->first()->cantidad)->toBe(7.0);
    // 50 - 3 (llevado) - 7 (entregado) - 2 (extra) = 38.
    expect((float) Stock::where('producto_id', $fierro->id)->first()->cantidad)->toBe(38.0);
    expect(desfaseKardex($this->env, $fierro->id))->toBe($desfase);
});

it('no deja editar una venta por debajo de lo ya entregado', function () {
    [$venta, $fierro, $tubo] = ventaConPendiente($this->env, $this->service, $this->turno, $this->cliente);
    $anticipo = ClienteAnticipo::where('venta_id', $venta->id)->with('items')->first();
    $this->post(route('finanzas.anticipos.aplicar', $anticipo), [
        'fecha' => now()->toDateString(),
        'items' => [['id' => $anticipo->items->first()->id, 'cantidad' => 2]],
    ])->assertSessionHasNoErrors();

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
})->throws(Illuminate\Validation\ValidationException::class);

it('solo un administrador edita una venta con entregas registradas', function () {
    [$venta, $fierro, $tubo] = ventaConPendiente($this->env, $this->service, $this->turno, $this->cliente);
    $anticipo = ClienteAnticipo::where('venta_id', $venta->id)->with('items')->first();
    $this->post(route('finanzas.anticipos.aplicar', $anticipo), [
        'fecha' => now()->toDateString(),
        'items' => [['id' => $anticipo->items->first()->id, 'cantidad' => 2]],
    ])->assertSessionHasNoErrors();

    $cajera = $this->env->admin->replicate();
    $cajera->setRelation('rol', tap($this->env->admin->rol->replicate(), fn ($r) => $r->es_admin = false));

    expect(fn () => $this->service->actualizar($venta, [
        'tipo_comprobante'  => 'ticket',
        'cliente_id'        => $this->cliente->id,
        'entrega_pendiente' => true,
        'items' => [
            ['producto_id' => $fierro->id, 'producto_unidad_id' => $fierro->unidadBase->id,
             'cantidad' => 10, 'precio_unitario' => 25, 'cantidad_pendiente' => 5],
            ['producto_id' => $tubo->id, 'producto_unidad_id' => $tubo->unidadBase->id,
             'cantidad' => 4, 'precio_unitario' => 10],
        ],
        'pagos' => [['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => 290]],
    ], $cajera))->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class, 'solo un administrador');

    expect((float) $venta->fresh()->total)->toBe(240.0); // nada cambió
});

it('un rechazo del servicio al editar vuelve como aviso, no como pantalla de error', function () {
    [$venta, $fierro] = ventaConPendiente($this->env, $this->service, $this->turno, $this->cliente);
    $venta->update(['estado' => 'completada']);
    $anticipo = ClienteAnticipo::where('venta_id', $venta->id)->with('items')->first();
    $this->post(route('finanzas.anticipos.aplicar', $anticipo), [
        'fecha' => now()->toDateString(),
        'items' => [['id' => $anticipo->items->first()->id, 'cantidad' => 2]],
    ])->assertSessionHasNoErrors();
    // Caso que la edición no resuelve: hubo una cancelación de pendiente.
    ClienteAnticipoCancelacion::create([
        'cliente_anticipo_id'      => $anticipo->id,
        'cliente_anticipo_item_id' => $anticipo->items->first()->id,
        'empresa_id'               => $this->env->empresa->id,
        'user_id'                  => $this->env->admin->id,
        'fecha'                    => now()->toDateString(),
        'cantidad'                 => 1,
        'monto'                    => 20,
        'motivo'                   => 'Cliente ya no lo quiere',
    ]);

    $this->put(route('ventas.update', $venta), [
        'tipo_comprobante' => 'ticket',
        'cliente_id'       => $this->cliente->id,
        'items' => [[
            'producto_id'        => $fierro->id,
            'producto_unidad_id' => $fierro->unidadBase->id,
            'cantidad'           => 10,
            'precio_unitario'    => 20,
        ]],
        'pagos' => [['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => 200]],
    ])->assertSessionHasErrors('venta');
});

it('puede cambiar el producto de una línea pendiente sin tocar lo ya entregado', function () {
    [$venta, $fierro] = ventaConPendiente($this->env, $this->service, $this->turno, $this->cliente);
    $ladrillo = $this->env->crearProducto(['precio_venta' => 25, 'stock_inicial' => 100]);

    $anticipo = ClienteAnticipo::where('venta_id', $venta->id)->with('items')->first();
    $itemFierro = $anticipo->items->first();

    // Cambiar 3 de los 7 fierros pendientes por 3 ladrillos.
    $this->post(route('finanzas.anticipos.items.cambiar-producto', [$anticipo, $itemFierro]), [
        'nuevo_producto_id' => $ladrillo->id,
        'cantidad'          => 3,
        'motivo'            => 'El cliente pidió cambiar marca',
    ])->assertSessionHasNoErrors();

    $anticipo->refresh()->load('items');

    // El ítem original se reduce y aparece el nuevo con el mismo precio congelado.
    $itemOriginal = $anticipo->items->firstWhere('id', $itemFierro->id);
    $itemNuevo    = $anticipo->items->firstWhere('producto_id', $ladrillo->id);

    expect((float) $itemOriginal->cantidad_pendiente)->toBe(4.0);
    expect((float) $itemOriginal->cantidad)->toBe(4.0);
    expect($itemNuevo)->not->toBeNull();
    expect((float) $itemNuevo->cantidad_pendiente)->toBe(3.0);
    expect((float) $itemNuevo->precio_unitario)->toBe(20.0); // respeta precio pagado

    // El pasivo total no cambia: 7 unidades × 20.
    expect((float) $anticipo->saldo)->toBe(140.0);
    expect($anticipo->estado)->toBe('activo');

    // Se puede entregar la nueva línea y saldrá stock de ladrillo.
    $this->post(route('finanzas.anticipos.aplicar', $anticipo), [
        'fecha' => now()->toDateString(),
        'items' => [['id' => $itemNuevo->id, 'cantidad' => 3]],
    ])->assertSessionHasNoErrors();

    expect((float) Stock::where('producto_id', $ladrillo->id)->first()->cantidad)->toBe(97.0);
    expect($anticipo->fresh()->items->firstWhere('id', $itemNuevo->id)->cantidad_pendiente)->toBe('0.0000');
});

it('puede anular una entrega de anticipo material: recupera stock y pendiente', function () {
    [$venta, $fierro, $tubo] = ventaConPendiente($this->env, $this->service, $this->turno, $this->cliente);
    $anticipo = ClienteAnticipo::where('venta_id', $venta->id)->with('items')->first();
    $item = $anticipo->items->first();

    // Primera entrega de 4 fierros.
    $this->post(route('finanzas.anticipos.aplicar', $anticipo), [
        'fecha' => now()->toDateString(),
        'items' => [['id' => $item->id, 'cantidad' => 4]],
    ])->assertSessionHasNoErrors();

    $stockAntes = (float) Stock::where('producto_id', $fierro->id)->first()->cantidad;
    $entrega = $anticipo->fresh()->aplicaciones()->with('items')->first();

    // Anular la entrega.
    $this->actingAs($this->env->admin)
        ->post(route('finanzas.anticipos.entrega.anular', $entrega), ['motivo' => 'Me equivoqué de cantidad'])
        ->assertSessionHasNoErrors();

    // Stock recuperado.
    expect((float) Stock::where('producto_id', $fierro->id)->first()->cantidad)->toBe($stockAntes + 4);

    // Pendiente recuperado.
    $item->refresh();
    expect((float) $item->cantidad_pendiente)->toBe(7.0);

    // Anticipo activo de nuevo.
    expect($anticipo->fresh()->estado)->toBe('activo');
});

it('puede editar una entrega de anticipo material ajustando cantidades', function () {
    [$venta, $fierro] = ventaConPendiente($this->env, $this->service, $this->turno, $this->cliente);
    $anticipo = ClienteAnticipo::where('venta_id', $venta->id)->with('items')->first();
    $item = $anticipo->items->first();

    // Entrega inicial de 4 fierros.
    $this->post(route('finanzas.anticipos.aplicar', $anticipo), [
        'fecha' => now()->toDateString(),
        'items' => [['id' => $item->id, 'cantidad' => 4]],
    ])->assertSessionHasNoErrors();

    $stockAntes = (float) Stock::where('producto_id', $fierro->id)->first()->cantidad;
    $entrega = $anticipo->fresh()->aplicaciones()->with('items')->first();

    // Editar: bajar de 4 a 1 fierro entregado.
    $this->actingAs($this->env->admin)
        ->put(route('finanzas.anticipos.entrega.editar', $entrega), [
            'fecha' => now()->toDateString(),
            'observacion' => 'Corrección: solo se llevó 1',
            'items' => [['id' => $entrega->items->first()->id, 'cantidad' => 1]],
        ])
        ->assertSessionHasNoErrors();

    // Stock: vuelven 3 fierros.
    expect((float) Stock::where('producto_id', $fierro->id)->first()->cantidad)->toBe($stockAntes + 3);

    // Pendiente pasa de 3 a 6.
    $item->refresh();
    expect((float) $item->cantidad_pendiente)->toBe(6.0);

    // La entrega queda con cantidad 1.
    expect((float) $entrega->fresh()->items->first()->cantidad)->toBe(1.0);
});

it('editar una entrega a 0 elimina el detalle y, si queda vacía, anula la entrega', function () {
    [$venta, $fierro] = ventaConPendiente($this->env, $this->service, $this->turno, $this->cliente);
    $anticipo = ClienteAnticipo::where('venta_id', $venta->id)->with('items')->first();
    $itemFierro = $anticipo->items->first();

    // Entrega inicial de 4 fierros.
    $this->post(route('finanzas.anticipos.aplicar', $anticipo), [
        'fecha' => now()->toDateString(),
        'items' => [['id' => $itemFierro->id, 'cantidad' => 4]],
    ])->assertSessionHasNoErrors();

    $entrega = $anticipo->fresh()->aplicaciones()->with('items')->first();

    // Editar a 0: frontend no envía el ítem.
    $this->actingAs($this->env->admin)
        ->put(route('finanzas.anticipos.entrega.editar', $entrega), [
            'fecha' => now()->toDateString(),
            'observacion' => 'No se llevó nada finalmente',
            'items' => [],
        ])
        ->assertSessionHasNoErrors();

    // La entrega se eliminó.
    expect(\App\Models\ClienteAnticipoAplicacion::where('id', $entrega->id)->exists())->toBeFalse();

    // Stock recuperado: inicial 50 - llevado en venta 3 = 47.
    expect((float) Stock::where('producto_id', $fierro->id)->first()->cantidad)->toBe(47.0);

    // Pendiente recuperado.
    $itemFierro->refresh();
    expect((float) $itemFierro->cantidad_pendiente)->toBe(7.0);
});

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

it('cancela todo el pendiente de un ítem en contado: ajusta venta, anticipo y genera egreso', function () {
    [$venta, $fierro] = ventaConPendiente($this->env, $this->service, $this->turno, $this->cliente);
    $anticipo = ClienteAnticipo::where('venta_id', $venta->id)->with('items')->first();
    $item = $anticipo->items->first();

    $this->post(route('finanzas.anticipos.items.cancelar-pendiente', [$anticipo, $item]), [
        'cantidad' => 7,
        'motivo'   => 'Cliente no requiere el material pendiente',
        'fecha'    => now()->toDateString(),
        'metodo_pago_id' => $this->env->metodo('efectivo')->id,
    ])->assertSessionHasNoErrors();

    $venta->refresh();
    expect((float) $venta->total)->toBe(100.0);          // 240 - 140
    expect((float) $venta->monto_pagado)->toBe(100.0);
    expect((float) $venta->saldo_pendiente)->toBe(0.0);

    $item->refresh();
    expect((float) $item->cantidad_pendiente)->toBe(0.0);
    expect((float) $item->cantidad)->toBe(0.0);            // el ítem del anticipo representa solo lo pendiente

    $anticipo->refresh();
    expect((float) $anticipo->saldo)->toBe(0.0);
    expect($anticipo->estado)->toBe('devuelto');

    $cancelacion = ClienteAnticipoCancelacion::where('cliente_anticipo_item_id', $item->id)->first();
    expect($cancelacion)->not->toBeNull();
    expect((float) $cancelacion->monto)->toBe(140.0);

    $mov = CuentaMovimiento::where('ref_tipo', 'anticipo_cancelacion')->where('ref_id', $cancelacion->id)->first();
    expect($mov)->not->toBeNull();
    expect((float) $mov->monto)->toBe(140.0);
    expect($mov->tipo)->toBe('egreso');

    // Stock no se mueve: lo cancelado nunca salió del almacén.
    expect((float) Stock::where('producto_id', $fierro->id)->first()->cantidad)->toBe(47.0);
});

it('cancela parcialmente el pendiente y deja el resto activo', function () {
    [$venta, $fierro] = ventaConPendiente($this->env, $this->service, $this->turno, $this->cliente);
    $anticipo = ClienteAnticipo::where('venta_id', $venta->id)->with('items')->first();
    $item = $anticipo->items->first();

    $this->post(route('finanzas.anticipos.items.cancelar-pendiente', [$anticipo, $item]), [
        'cantidad' => 3,
        'motivo'   => 'Solo cancelo 3 unidades',
        'fecha'    => now()->toDateString(),
        'metodo_pago_id' => $this->env->metodo('efectivo')->id,
    ])->assertSessionHasNoErrors();

    $venta->refresh();
    expect((float) $venta->total)->toBe(180.0); // 240 - 60

    $item->refresh();
    expect((float) $item->cantidad_pendiente)->toBe(4.0);

    $anticipo->refresh();
    expect((float) $anticipo->saldo)->toBe(80.0);
    expect($anticipo->estado)->toBe('activo');

    $cancelacion = ClienteAnticipoCancelacion::first();
    expect((float) $cancelacion->monto)->toBe(60.0);
});

it('en venta a crédito reduce la deuda sin generar egreso de caja', function () {
    $fierro = $this->env->crearProducto(['precio_venta' => 20, 'stock_inicial' => 50]);

    $venta = $this->service->crear([
        'tipo_comprobante'  => 'ticket',
        'cliente_id'        => $this->cliente->id,
        'es_credito'        => true,
        'entrega_pendiente' => true,
        'items' => [[
            'producto_id'        => $fierro->id,
            'producto_unidad_id' => $fierro->unidadBase->id,
            'cantidad'           => 10,
            'precio_unitario'    => 20,
            'cantidad_pendiente' => 7,
        ]],
        'pagos' => [],
    ], $this->env->admin, $this->turno);

    $anticipo = ClienteAnticipo::where('venta_id', $venta->id)->with('items')->first();
    $item = $anticipo->items->first();

    // El método de pago es obligatorio al cancelar un pendiente. La pantalla lo
    // manda; esta prueba no lo hacía y por eso fallaba.
    $this->post(route('finanzas.anticipos.items.cancelar-pendiente', [$anticipo, $item]), [
        'cantidad'       => 7,
        'motivo'         => 'Cliente no quiere el pendiente',
        'fecha'          => now()->toDateString(),
        'metodo_pago_id' => $this->env->metodo('efectivo')->id,
    ])->assertSessionHasNoErrors();

    $venta->refresh();
    expect((float) $venta->total)->toBe(60.0); // 10-7=3 × 20
    expect((float) $venta->saldo_pendiente)->toBe(60.0);
    expect((float) $venta->monto_pagado)->toBe(0.0);

    $anticipo->refresh();
    expect($anticipo->estado)->toBe('aplicado');

    expect(CuentaMovimiento::where('ref_tipo', 'anticipo_cancelacion')->exists())->toBeFalse();
});

it('guarda el turno y caja cuando se marca afecta caja', function () {
    [$venta] = ventaConPendiente($this->env, $this->service, $this->turno, $this->cliente);
    $anticipo = ClienteAnticipo::where('venta_id', $venta->id)->with('items')->first();
    $item = $anticipo->items->first();

    $this->post(route('finanzas.anticipos.items.cancelar-pendiente', [$anticipo, $item]), [
        'cantidad' => 1,
        'motivo'   => 'Con turno',
        'fecha'    => now()->toDateString(),
        'metodo_pago_id' => $this->env->metodo('efectivo')->id,
        'turno_id' => $this->turno->id,
    ])->assertSessionHasNoErrors();

    $cancelacion = ClienteAnticipoCancelacion::first();
    expect($cancelacion->turno_id)->toBe($this->turno->id);
    expect($cancelacion->caja_id)->toBe($this->turno->caja_id);
});

it('permite cancelar sin afectar caja', function () {
    [$venta] = ventaConPendiente($this->env, $this->service, $this->turno, $this->cliente);
    $anticipo = ClienteAnticipo::where('venta_id', $venta->id)->with('items')->first();
    $item = $anticipo->items->first();

    $this->post(route('finanzas.anticipos.items.cancelar-pendiente', [$anticipo, $item]), [
        'cantidad' => 1,
        'motivo'   => 'Sin turno',
        'fecha'    => now()->toDateString(),
        'metodo_pago_id' => $this->env->metodo('efectivo')->id,
        'turno_id' => '',
    ])->assertSessionHasNoErrors();

    $cancelacion = ClienteAnticipoCancelacion::first();
    expect($cancelacion->turno_id)->toBeNull();
    expect($cancelacion->caja_id)->toBeNull();
});

/**
 * Esta prueba esperaba el error en `cuenta_id`, pero quien corta primero es
 * `metodo_pago_id`: sin método, la regla de la cuenta ni llega a evaluarse. Se
 * comprueban los dos casos por separado, que es lo que de verdad protege:
 * que no se pueda cancelar un pendiente sin decir por dónde sale el dinero.
 */
it('rechaza cancelar sin indicar el método de pago', function () {
    [$venta] = ventaConPendiente($this->env, $this->service, $this->turno, $this->cliente);
    $anticipo = ClienteAnticipo::where('venta_id', $venta->id)->with('items')->first();
    $item = $anticipo->items->first();

    $this->post(route('finanzas.anticipos.items.cancelar-pendiente', [$anticipo, $item]), [
        'cantidad' => 1,
        'motivo'   => 'Sin método',
        'fecha'    => now()->toDateString(),
    ])->assertSessionHasErrors(['metodo_pago_id']);
});

it('rechaza cancelar más de lo pendiente', function () {
    [$venta] = ventaConPendiente($this->env, $this->service, $this->turno, $this->cliente);
    $anticipo = ClienteAnticipo::where('venta_id', $venta->id)->with('items')->first();
    $item = $anticipo->items->first();

    $this->post(route('finanzas.anticipos.items.cancelar-pendiente', [$anticipo, $item]), [
        'cantidad' => 8,
        'motivo'   => 'Exceso',
        'fecha'    => now()->toDateString(),
        'metodo_pago_id' => $this->env->metodo('efectivo')->id,
    ])->assertSessionHasErrors(['cantidad']);
});
