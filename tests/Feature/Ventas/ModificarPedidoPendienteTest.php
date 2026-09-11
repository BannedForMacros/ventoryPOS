<?php

use App\Models\Cliente;
use App\Models\ClienteAnticipo;
use App\Models\ClienteAnticipoItem;
use App\Models\CuentaMovimiento;
use App\Models\Stock;
use App\Models\Venta;
use App\Models\VentaAbono;
use App\Models\VentaItem;
use App\Services\EntregaPendienteService;
use App\Services\VentaService;
use Illuminate\Support\Facades\DB;
use Tests\Support\TestEnv;

/**
 * Modificar pedido pendiente por entregar (días después de la venta):
 *  - la caja ORIGINAL no se mueve; la diferencia se liquida en la caja de HOY
 *  - lo que se conserva mantiene precio congelado; lo nuevo va al precio de hoy
 *  - si sobra dinero, por defecto queda como SALDO A FAVOR del cliente
 *  - el stock en vivo no se mueve y el "Recalcular" da exactamente lo mismo
 */
beforeEach(function () {
    $this->env     = TestEnv::crear();
    $this->turno   = $this->env->abrirTurno();
    $this->ventas  = app(VentaService::class);
    $this->actingAs($this->env->admin);

    $this->cliente = Cliente::create([
        'empresa_id'       => $this->env->empresa->id,
        'nombres'          => 'Manuela Yadira',
        'apellidos'        => 'Huanca',
        'tipo_documento'   => 'DNI',
        'numero_documento' => '44556677',
        'activo'           => true,
    ]);
});

/** Producto con su stock inicial registrado también como apertura (para comparar "Recalcular"). */
function productoConApertura(TestEnv $env, float $precio, float $stock): \App\Models\Producto
{
    $p = $env->crearProducto(['precio_venta' => $precio, 'stock_inicial' => $stock]);
    DB::table('stock_iniciales')->insert([
        'empresa_id'  => $env->empresa->id,
        'almacen_id'  => $env->almacen->id,
        'producto_id' => $p->id,
        'fecha'       => now()->subDays(20)->toDateString(),
        'cantidad'    => $stock,
        'costo'       => 6,
        'created_at'  => now(),
        'updated_at'  => now(),
    ]);

    return $p;
}

/** Venta de hace 10 días: 10 fierros @20 (se lleva 3, quedan 7) + 4 tubos @10 (se los lleva). Total 240. */
function ventaConPedido($test, ?Cliente $cliente = null): array
{
    $fierro  = productoConApertura($test->env, 20, 50);
    $tubo    = productoConApertura($test->env, 10, 30);
    $cemento = productoConApertura($test->env, 25, 40);

    $venta = $test->ventas->crear([
        'tipo_comprobante'  => 'ticket',
        'cliente_id'        => ($cliente ?? $test->cliente)->id,
        'entrega_pendiente' => true,
        'items' => [
            ['producto_id' => $fierro->id, 'producto_unidad_id' => $fierro->unidadBase->id, 'cantidad' => 10, 'precio_unitario' => 20, 'cantidad_pendiente' => 7],
            ['producto_id' => $tubo->id,   'producto_unidad_id' => $tubo->unidadBase->id,   'cantidad' => 4,  'precio_unitario' => 10, 'cantidad_pendiente' => 0],
        ],
        'pagos' => [['metodo_pago_id' => $test->env->metodo('efectivo')->id, 'monto' => 240]],
    ], $test->env->admin, $test->turno);

    // La venta es de hace 10 días y su turno ya se cerró; hoy la cajera tiene otro.
    $venta->update(['fecha_venta' => now()->subDays(10)]);
    $test->turno->update(['estado' => 'cerrado', 'fecha_cierre' => now()->subDays(10)]);
    $test->turnoHoy = $test->env->abrirTurno();

    $anticipo = ClienteAnticipo::where('venta_id', $venta->id)->firstOrFail();
    $itemFierro = $anticipo->items()->where('producto_id', $fierro->id)->firstOrFail();

    return [$venta->fresh(), $anticipo, $itemFierro, $fierro, $tubo, $cemento];
}

/** El "Recalcular" debe dar exactamente el stock en vivo (sin mercadería fantasma). */
function assertRecalculoCuadra($test, \App\Models\Producto ...$productos): void
{
    foreach ($productos as $p) {
        $vivo = (float) Stock::where('almacen_id', $test->env->almacen->id)->where('producto_id', $p->id)->value('cantidad');
        $recalculado = (float) Stock::reconstruir($test->env->almacen->id, $p->id)->cantidad;
        expect($recalculado)->toBe($vivo, "Recalcular de «{$p->nombre}» no cuadra con el stock en vivo");
    }
}

it('reduce lo pendiente, agrega un producto nuevo y cobra la diferencia HOY sin tocar la caja original', function () {
    [$venta, $anticipo, $itemFierro, $fierro, $tubo, $cemento] = ventaConPedido($this);
    $esperadoOriginalAntes = $this->turno->fresh()->calcularMontoEsperado();

    $this->post(route('ventas.modificar-pedido', $venta), [
        'motivo'           => 'El cliente cambió su pedido',
        'items'            => [['id' => $itemFierro->id, 'cantidad_pendiente' => 5]],        // −2 × 20 = −40
        'nuevos'           => [['producto_id' => $cemento->id, 'producto_unidad_id' => $cemento->unidadBase->id, 'cantidad' => 3, 'precio_unitario' => 25]], // +75
        'cobro_monto_pago' => 35,
        'metodo_pago_id'   => $this->env->metodo('efectivo')->id,
    ])->assertSessionHasNoErrors();

    $venta->refresh();
    expect((float) $venta->total)->toBe(275.0);
    expect((float) $venta->monto_pagado)->toBe(275.0);
    expect((float) $venta->saldo_pendiente)->toBe(0.0);
    expect($venta->es_credito)->toBeFalse();

    // Línea de fierro: 3 llevados + 5 pendientes. Cemento: línea nueva al precio indicado.
    expect((float) VentaItem::find($itemFierro->venta_item_id)->cantidad)->toBe(8.0);
    $viCemento = VentaItem::where('venta_id', $venta->id)->where('producto_id', $cemento->id)->firstOrFail();
    expect((float) $viCemento->cantidad)->toBe(3.0);
    expect((float) $viCemento->precio_unitario)->toBe(25.0);

    // Anticipo: 5×20 + 3×25 = 175 pendiente.
    $anticipo->refresh();
    expect((float) $anticipo->saldo)->toBe(175.0);
    expect($anticipo->estado)->toBe('activo');

    // El cobro entra HOY a la caja de HOY; la caja original sigue igual.
    $abono = VentaAbono::where('venta_id', $venta->id)->firstOrFail();
    expect((float) $abono->monto)->toBe(35.0);
    expect($abono->turno_id)->toBe($this->turnoHoy->id);
    expect($abono->fecha->toDateString())->toBe(now()->toDateString());
    expect(CuentaMovimiento::where('ref_tipo', 'venta_abono')->where('ref_id', $abono->id)->where('tipo', 'ingreso')->sum('monto'))->toEqual(35);
    expect($this->turno->fresh()->calcularMontoEsperado())->toBe($esperadoOriginalAntes);
    expect($this->turnoHoy->fresh()->calcularMontoEsperado())->toBe(100.0 + 35.0);

    // Stock en vivo intacto (lo pendiente nunca salió) y el recálculo cuadra.
    expect((float) Stock::where('producto_id', $fierro->id)->value('cantidad'))->toBe(47.0);
    expect((float) Stock::where('producto_id', $cemento->id)->value('cantidad'))->toBe(40.0);
    assertRecalculoCuadra($this, $fierro, $tubo, $cemento);
});

it('si sobra dinero, por defecto queda como SALDO A FAVOR del cliente sin mover caja', function () {
    [$venta, $anticipo, $itemFierro, $fierro] = ventaConPedido($this);

    $this->post(route('ventas.modificar-pedido', $venta), [
        'motivo' => 'Solo quiere 2 fierros',
        'items'  => [['id' => $itemFierro->id, 'cantidad_pendiente' => 2]], // −5 × 20 = −100
    ])->assertSessionHasNoErrors();

    $venta->refresh();
    expect((float) $venta->total)->toBe(140.0);
    expect((float) $venta->monto_pagado)->toBe(140.0);

    $saldoFavor = ClienteAnticipo::where('venta_origen_id', $venta->id)->firstOrFail();
    expect($saldoFavor->tipo_valorizacion)->toBe('monto');
    expect($saldoFavor->estado)->toBe('activo');
    expect($saldoFavor->cliente_id)->toBe($this->cliente->id);
    expect((float) $saldoFavor->saldo)->toBe(100.0);

    // Sin tesorería y sin afectar ninguna caja.
    expect(CuentaMovimiento::where('ref_tipo', 'cliente_anticipo')->where('ref_id', $saldoFavor->id)->exists())->toBeFalse();
    expect($this->turnoHoy->fresh()->calcularMontoEsperado())->toBe(100.0);
    assertRecalculoCuadra($this, $fierro);
});

it('si sobra dinero y se elige DEVOLVER, sale de la caja de hoy', function () {
    [$venta, $anticipo, $itemFierro, $fierro] = ventaConPedido($this);

    $this->post(route('ventas.modificar-pedido', $venta), [
        'motivo'            => 'Devolver lo que sobra',
        'items'             => [['id' => $itemFierro->id, 'cantidad_pendiente' => 4]], // −60
        'excedente_destino' => 'devolver',
        'metodo_pago_id'    => $this->env->metodo('efectivo')->id,
    ])->assertSessionHasNoErrors();

    $devuelto = ClienteAnticipo::where('venta_origen_id', $venta->id)->firstOrFail();
    expect($devuelto->estado)->toBe('devuelto');
    expect($devuelto->turno_devolucion_id)->toBe($this->turnoHoy->id);
    expect((float) CuentaMovimiento::where('ref_tipo', 'cliente_anticipo_devolucion')->where('ref_id', $devuelto->id)->sum('monto'))->toBe(60.0);
    expect($this->turnoHoy->fresh()->calcularMontoEsperado())->toBe(100.0 - 60.0);
    expect((float) $venta->fresh()->total)->toBe(180.0);
});

it('aumentar el mismo producto al MISMO precio suma en la línea; a otro precio crea línea aparte', function () {
    [$venta, $anticipo, $itemFierro, $fierro] = ventaConPedido($this);

    $this->post(route('ventas.modificar-pedido', $venta), [
        'motivo'           => 'Quiere 2 fierros más',
        'nuevos'           => [['producto_id' => $fierro->id, 'producto_unidad_id' => $fierro->unidadBase->id, 'cantidad' => 2, 'precio_unitario' => 20]],
        'cobro_monto_pago' => 40,
        'metodo_pago_id'   => $this->env->metodo('efectivo')->id,
    ])->assertSessionHasNoErrors();

    expect(VentaItem::where('venta_id', $venta->id)->where('producto_id', $fierro->id)->count())->toBe(1);
    expect((float) $itemFierro->fresh()->cantidad_pendiente)->toBe(9.0);

    // Ahora el fierro subió a 22: lo nuevo va aparte, lo anterior sigue a 20.
    $this->post(route('ventas.modificar-pedido', $venta), [
        'motivo'           => 'Quiere 1 fierro más al precio de hoy',
        'nuevos'           => [['producto_id' => $fierro->id, 'producto_unidad_id' => $fierro->unidadBase->id, 'cantidad' => 1, 'precio_unitario' => 22]],
        'cobro_monto_pago' => 22,
        'metodo_pago_id'   => $this->env->metodo('efectivo')->id,
    ])->assertSessionHasNoErrors();

    expect(VentaItem::where('venta_id', $venta->id)->where('producto_id', $fierro->id)->count())->toBe(2);
    expect((float) $venta->fresh()->total)->toBe(302.0); // 240 + 40 + 22
    expect((float) $anticipo->fresh()->saldo)->toBe(202.0); // 9×20 + 1×22
    assertRecalculoCuadra($this, $fierro);
});

it('si falta dinero y no se cobra, exige marcar crédito; con crédito queda en cuentas por cobrar', function () {
    [$venta, $anticipo, $itemFierro, $fierro, $tubo, $cemento] = ventaConPedido($this);
    $payload = [
        'motivo' => 'Agrega cemento y paga luego',
        'nuevos' => [['producto_id' => $cemento->id, 'producto_unidad_id' => $cemento->unidadBase->id, 'cantidad' => 2, 'precio_unitario' => 25]],
    ];

    $this->post(route('ventas.modificar-pedido', $venta), $payload)->assertSessionHasErrors('dejar_credito');
    expect((float) $venta->fresh()->total)->toBe(240.0); // nada se aplicó

    $this->post(route('ventas.modificar-pedido', $venta), $payload + ['dejar_credito' => true])->assertSessionHasNoErrors();
    $venta->refresh();
    expect($venta->es_credito)->toBeTrue();
    expect((float) $venta->saldo_pendiente)->toBe(50.0);
    expect((float) $venta->monto_pagado)->toBe(240.0);
});

it('puede cobrar la diferencia consumiendo el anticipo de dinero del cliente', function () {
    [$venta, $anticipo, $itemFierro, $fierro, $tubo, $cemento] = ventaConPedido($this);
    $anticipoDinero = ClienteAnticipo::create([
        'empresa_id' => $this->env->empresa->id, 'cliente_id' => $this->cliente->id, 'user_id' => $this->env->admin->id,
        'fecha' => now()->toDateString(), 'monto' => 100, 'saldo' => 100, 'tipo_valorizacion' => 'monto', 'estado' => 'activo',
    ]);

    $this->post(route('ventas.modificar-pedido', $venta), [
        'motivo'               => 'Agrega cemento pagado con su anticipo',
        'nuevos'               => [['producto_id' => $cemento->id, 'producto_unidad_id' => $cemento->unidadBase->id, 'cantidad' => 2, 'precio_unitario' => 25]],
        'cobro_anticipo_id'    => $anticipoDinero->id,
        'cobro_monto_anticipo' => 50,
    ])->assertSessionHasNoErrors();

    expect((float) $anticipoDinero->fresh()->saldo)->toBe(50.0);
    $abono = VentaAbono::where('venta_id', $venta->id)->firstOrFail();
    expect($abono->cliente_anticipo_id)->toBe($anticipoDinero->id);
    expect(CuentaMovimiento::where('ref_tipo', 'venta_abono')->where('ref_id', $abono->id)->exists())->toBeFalse();
    expect((float) $venta->fresh()->saldo_pendiente)->toBe(0.0);
});

it('con entregas parciales previas solo modifica lo aún pendiente', function () {
    [$venta, $anticipo, $itemFierro, $fierro] = ventaConPedido($this);

    app(EntregaPendienteService::class)->aplicarEntregaMaterial($anticipo->fresh('items'), [
        'fecha' => now()->toDateString(),
        'items' => [['id' => $itemFierro->id, 'cantidad' => 3]],
    ], $this->env->admin);
    expect((float) Stock::where('producto_id', $fierro->id)->value('cantidad'))->toBe(44.0);

    // No se puede subir por encima de lo pendiente (4) desde la línea existente.
    $this->post(route('ventas.modificar-pedido', $venta), [
        'motivo' => 'Intento inválido', 'items' => [['id' => $itemFierro->id, 'cantidad_pendiente' => 6]],
    ])->assertSessionHasErrors('items.0.cantidad_pendiente');

    // Bajar lo pendiente de 4 a 1 (−60 a favor del cliente).
    $this->post(route('ventas.modificar-pedido', $venta), [
        'motivo' => 'Ya no quiere el resto', 'items' => [['id' => $itemFierro->id, 'cantidad_pendiente' => 1]],
    ])->assertSessionHasNoErrors();

    $itemFierro->refresh();
    expect((float) $itemFierro->cantidad_pendiente)->toBe(1.0);
    expect((float) VentaItem::find($itemFierro->venta_item_id)->cantidad)->toBe(7.0); // 3 llevados + 3 entregados + 1 pendiente
    expect((float) Stock::where('producto_id', $fierro->id)->value('cantidad'))->toBe(44.0);
    assertRecalculoCuadra($this, $fierro);
});

it('quitar TODO lo pendiente elimina la línea vacía y cierra el pedido', function () {
    [$venta, $anticipo, $itemFierro, $fierro, $tubo, $cemento] = ventaConPedido($this);

    // Primero agrega cemento (línea solo pendiente) y luego lo quita del todo.
    $this->post(route('ventas.modificar-pedido', $venta), [
        'motivo' => 'Agrega cemento', 'dejar_credito' => true,
        'nuevos' => [['producto_id' => $cemento->id, 'producto_unidad_id' => $cemento->unidadBase->id, 'cantidad' => 2, 'precio_unitario' => 25]],
    ])->assertSessionHasNoErrors();
    $itemCemento = ClienteAnticipoItem::where('cliente_anticipo_id', $anticipo->id)->where('producto_id', $cemento->id)->firstOrFail();

    $this->post(route('ventas.modificar-pedido', $venta), [
        'motivo' => 'Cancela todo lo pendiente',
        'items'  => [
            ['id' => $itemFierro->id,  'cantidad_pendiente' => 0],
            ['id' => $itemCemento->id, 'cantidad_pendiente' => 0],
        ],
    ])->assertSessionHasNoErrors();

    expect(VentaItem::where('venta_id', $venta->id)->where('producto_id', $cemento->id)->exists())->toBeFalse();
    expect((float) VentaItem::find($itemFierro->venta_item_id)->cantidad)->toBe(3.0); // quedan solo los llevados
    expect($anticipo->fresh()->estado)->toBe('aplicado');
    expect((float) $venta->fresh()->total)->toBe(100.0); // 3×20 + 4×10
    assertRecalculoCuadra($this, $fierro, $cemento);
});

it('anular la venta revierte el saldo a favor; si ya se usó, bloquea la anulación', function () {
    [$venta, $anticipo, $itemFierro] = ventaConPedido($this);

    $this->post(route('ventas.modificar-pedido', $venta), [
        'motivo' => 'Reduce', 'items' => [['id' => $itemFierro->id, 'cantidad_pendiente' => 5]],
    ])->assertSessionHasNoErrors();
    $saldoFavor = ClienteAnticipo::where('venta_origen_id', $venta->id)->firstOrFail();

    // Usado en parte → no se puede anular la venta.
    $saldoFavor->aplicaciones()->create([
        'empresa_id' => $this->env->empresa->id, 'numero' => 'E-9999', 'user_id' => $this->env->admin->id,
        'fecha' => now()->toDateString(), 'monto' => 10,
    ]);
    expect(fn () => $this->ventas->anular($venta->fresh(), $this->env->admin, 'prueba'))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
    expect($venta->fresh()->estado)->toBe('completada');

    // Sin uso → la anulación lo anula también.
    $saldoFavor->aplicaciones()->delete();
    $this->ventas->anular($venta->fresh(), $this->env->admin, 'prueba');
    expect($venta->fresh()->estado)->toBe('anulada');
    expect($saldoFavor->fresh()->estado)->toBe('anulado');
});

it('anular la venta revierte también el egreso de una devolución hecha al modificar', function () {
    [$venta, $anticipo, $itemFierro] = ventaConPedido($this);

    $this->post(route('ventas.modificar-pedido', $venta), [
        'motivo' => 'Devuelve', 'items' => [['id' => $itemFierro->id, 'cantidad_pendiente' => 5]],
        'excedente_destino' => 'devolver', 'metodo_pago_id' => $this->env->metodo('efectivo')->id,
    ])->assertSessionHasNoErrors();
    $devuelto = ClienteAnticipo::where('venta_origen_id', $venta->id)->firstOrFail();
    expect(CuentaMovimiento::where('ref_tipo', 'cliente_anticipo_devolucion')->where('ref_id', $devuelto->id)->exists())->toBeTrue();

    $this->ventas->anular($venta->fresh(), $this->env->admin, 'prueba');

    expect($devuelto->fresh()->estado)->toBe('anulado');
    expect(CuentaMovimiento::where('ref_tipo', 'cliente_anticipo_devolucion')->where('ref_id', $devuelto->id)->exists())->toBeFalse();
});

it('bloquea: venta sin pendiente, saldo a favor para Cliente General, y edición completa tras un excedente', function () {
    // Venta sin pendiente por entregar.
    $p = productoConApertura($this->env, 10, 20);
    $sinPendiente = $this->ventas->crear([
        'tipo_comprobante' => 'ticket',
        'items' => [['producto_id' => $p->id, 'producto_unidad_id' => $p->unidadBase->id, 'cantidad' => 1, 'precio_unitario' => 10]],
        'pagos' => [['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => 10]],
    ], $this->env->admin, $this->turno);
    $this->post(route('ventas.modificar-pedido', $sinPendiente), ['motivo' => 'Nada que cambiar'])->assertStatus(422);

    // Cliente General no puede quedarse con saldo a favor.
    [$venta, $anticipo, $itemFierro] = ventaConPedido($this, $this->env->clienteGeneral);
    $this->post(route('ventas.modificar-pedido', $venta), [
        'motivo' => 'Reduce', 'items' => [['id' => $itemFierro->id, 'cantidad_pendiente' => 5]],
    ])->assertSessionHasErrors('excedente_destino');

    // Con devolución sí; y luego la edición completa queda bloqueada.
    $this->post(route('ventas.modificar-pedido', $venta), [
        'motivo' => 'Reduce y devuelve', 'items' => [['id' => $itemFierro->id, 'cantidad_pendiente' => 5]],
        'excedente_destino' => 'devolver', 'metodo_pago_id' => $this->env->metodo('efectivo')->id,
    ])->assertSessionHasNoErrors();
    expect(fn () => $this->ventas->actualizar($venta->fresh(), ['items' => [], 'pagos' => []], $this->env->admin))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});

it('los datos del modal traen precio congelado y precio de hoy', function () {
    [$venta, $anticipo, $itemFierro, $fierro] = ventaConPedido($this);
    $fierro->unidadBase->update(['precio_venta' => 23]);

    $r = $this->getJson(route('ventas.pedido-pendiente', $venta))->assertOk()->json();

    expect($r['bloqueo'])->toBeNull();
    expect($r['pendientes'])->toHaveCount(1);
    expect((float) $r['pendientes'][0]['precio_unitario'])->toBe(20.0);
    expect((float) $r['pendientes'][0]['precio_hoy'])->toBe(23.0);
    expect((float) $r['venta']['pagado'])->toBe(240.0);
    expect($r['turno_activo_id'])->toBe($this->turnoHoy->id);
});

it('«Cambiar producto» de Anticipos ya no crea stock fantasma al Recalcular', function () {
    [$venta, $anticipo, $itemFierro, $fierro, $tubo, $cemento] = ventaConPedido($this);

    $this->post(route('finanzas.anticipos.items.cambiar-producto', [$anticipo->id, $itemFierro->id]), [
        'cantidad' => 2, 'nuevo_producto_id' => $cemento->id, 'motivo' => 'Cambia 2 fierros por cemento',
    ])->assertSessionHasNoErrors();

    // Precio congelado: el cemento entra a 20 (lo pagado por el fierro), total igual.
    $viCemento = VentaItem::where('venta_id', $venta->id)->where('producto_id', $cemento->id)->firstOrFail();
    expect((float) $viCemento->precio_unitario)->toBe(20.0);
    expect((float) $viCemento->cantidad)->toBe(2.0);
    expect((float) VentaItem::find($itemFierro->venta_item_id)->cantidad)->toBe(8.0);
    expect((float) $venta->fresh()->total)->toBe(240.0);
    expect((float) $anticipo->fresh()->saldo)->toBe(140.0);

    assertRecalculoCuadra($this, $fierro, $cemento);
});

it('cambia el precio de una línea 100% pendiente sin partirla', function () {
    [$venta, $anticipo, $itemFierro, $fierro, $tubo, $cemento] = ventaConPedido($this);

    // Línea solo pendiente: 2 cemento a 25 (queda al crédito).
    $this->post(route('ventas.modificar-pedido', $venta), [
        'motivo' => 'Agrega cemento', 'dejar_credito' => true,
        'nuevos' => [['producto_id' => $cemento->id, 'producto_unidad_id' => $cemento->unidadBase->id, 'cantidad' => 2, 'precio_unitario' => 25]],
    ])->assertSessionHasNoErrors();
    $itemCemento = ClienteAnticipoItem::where('cliente_anticipo_id', $anticipo->id)->where('producto_id', $cemento->id)->firstOrFail();
    $viCementoId = $itemCemento->venta_item_id;

    // Sube el precio del pendiente de 25 a 30 y cobra los 10 de diferencia.
    $this->post(route('ventas.modificar-pedido', $venta), [
        'motivo'           => 'Corrige el precio del cemento',
        'items'            => [['id' => $itemCemento->id, 'cantidad_pendiente' => 2, 'precio_unitario' => 30]],
        'cobro_monto_pago' => 10,
        'metodo_pago_id'   => $this->env->metodo('efectivo')->id,
    ])->assertSessionHasNoErrors();

    $itemCemento->refresh();
    expect((float) $itemCemento->precio_unitario)->toBe(30.0);
    expect($itemCemento->venta_item_id)->toBe($viCementoId);                       // misma línea, no se partió
    expect((float) VentaItem::find($viCementoId)->precio_unitario)->toBe(30.0);
    expect(VentaItem::where('venta_id', $venta->id)->where('producto_id', $cemento->id)->count())->toBe(1);
    expect((float) $venta->fresh()->total)->toBe(300.0);                           // 240 + 2×30
    expect((float) $anticipo->fresh()->saldo)->toBe(200.0);                        // 7×20 + 2×30
    assertRecalculoCuadra($this, $cemento);
});

it('al cambiar el precio, lo ya entregado conserva el suyo y el pendiente va aparte', function () {
    [$venta, $anticipo, $itemFierro, $fierro] = ventaConPedido($this);

    // Se entregan 3 de los 7 pendientes a 20.
    app(EntregaPendienteService::class)->aplicarEntregaMaterial($anticipo->fresh('items'), [
        'fecha' => now()->toDateString(),
        'items' => [['id' => $itemFierro->id, 'cantidad' => 3]],
    ], $this->env->admin);

    // Los 4 que quedan suben a 25 (+20) y se cobran.
    $this->post(route('ventas.modificar-pedido', $venta), [
        'motivo'           => 'El fierro subió de precio',
        'items'            => [['id' => $itemFierro->id, 'cantidad_pendiente' => 4, 'precio_unitario' => 25]],
        'cobro_monto_pago' => 20,
        'metodo_pago_id'   => $this->env->metodo('efectivo')->id,
    ])->assertSessionHasNoErrors();

    // La línea vieja se queda con 3 llevados + 3 entregados a 20; el pendiente va en una nueva a 25.
    $viejo = VentaItem::find($itemFierro->venta_item_id);
    expect((float) $viejo->cantidad)->toBe(6.0);
    expect((float) $viejo->precio_unitario)->toBe(20.0);
    $itemFierro->refresh();
    expect((float) $itemFierro->cantidad_pendiente)->toBe(0.0);
    expect((float) $itemFierro->cantidad)->toBe(3.0);                               // conserva su historia de entrega

    $nuevoItem = ClienteAnticipoItem::where('cliente_anticipo_id', $anticipo->id)
        ->where('producto_id', $fierro->id)->where('id', '!=', $itemFierro->id)->firstOrFail();
    expect((float) $nuevoItem->cantidad_pendiente)->toBe(4.0);
    expect((float) $nuevoItem->precio_unitario)->toBe(25.0);
    expect((float) VentaItem::find($nuevoItem->venta_item_id)->precio_unitario)->toBe(25.0);

    expect((float) $venta->fresh()->total)->toBe(260.0);                            // 6×20 + 4×25 + 4×10
    expect((float) $anticipo->fresh()->saldo)->toBe(100.0);                         // 4×25
    expect((float) Stock::where('producto_id', $fierro->id)->value('cantidad'))->toBe(44.0);
    assertRecalculoCuadra($this, $fierro);
});

it('bajar el precio del pendiente deja la diferencia a favor del cliente', function () {
    [$venta, $anticipo, $itemFierro, $fierro] = ventaConPedido($this);

    // Los 7 pendientes bajan de 20 a 18 (−14 a favor del cliente).
    $this->post(route('ventas.modificar-pedido', $venta), [
        'motivo' => 'Le respetamos la oferta',
        'items'  => [['id' => $itemFierro->id, 'cantidad_pendiente' => 7, 'precio_unitario' => 18]],
    ])->assertSessionHasNoErrors();

    expect((float) $venta->fresh()->total)->toBe(226.0);                            // 3×20 + 7×18 + 4×10
    $saldoFavor = ClienteAnticipo::where('venta_origen_id', $venta->id)->firstOrFail();
    expect((float) $saldoFavor->saldo)->toBe(14.0);
    assertRecalculoCuadra($this, $fierro);
});
