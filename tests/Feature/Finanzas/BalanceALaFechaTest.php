<?php

use App\Models\Cliente;
use App\Models\Entrada;
use App\Models\VentaAbono;
use App\Services\BalanceDiarioService;
use App\Services\VentaService;
use Tests\Support\TestEnv;

/**
 * Las líneas del balance deben reflejar el estado A LA FECHA del balance:
 * cobros/pagos POSTERIORES no pueden alterar el balance de un día anterior.
 */
beforeEach(function () {
    $this->env = TestEnv::crear();
    $this->turno = $this->env->abrirTurno();
    $this->ventas = app(VentaService::class);
    $this->balances = app(BalanceDiarioService::class);
    $this->actingAs($this->env->admin);
});

it('un abono de HOY no borra la deuda por cobrar del balance de AYER', function () {
    $cliente = Cliente::create([
        'empresa_id' => $this->env->empresa->id,
        'nombres' => 'Cliente', 'apellidos' => 'Credito',
        'tipo_documento' => 'DNI', 'numero_documento' => '22222222', 'activo' => true,
    ]);
    $producto = $this->env->crearProducto(['precio_venta' => 100, 'precio_costo' => 60, 'stock_inicial' => 10]);

    // AYER: venta a crédito de S/100 sin pago inicial (backdate).
    $venta = $this->ventas->crear([
        'tipo_comprobante' => 'ticket',
        'cliente_id'       => $cliente->id,
        'es_credito'       => true,
        'fecha_venta'      => now()->subDay()->toDateString(),
        'items' => [[
            'producto_id'        => $producto->id,
            'producto_unidad_id' => $producto->unidadBase->id,
            'cantidad'           => 1,
            'precio_unitario'    => 100,
        ]],
        'pagos' => [],
    ], $this->env->admin, $this->turno);
    expect((float) $venta->saldo_pendiente)->toBe(100.0);

    // HOY: el cliente abona S/100 (queda saldada).
    VentaAbono::create([
        'venta_id' => $venta->id, 'user_id' => $this->env->admin->id,
        'fecha' => now()->toDateString(), 'monto' => 100,
    ]);
    $venta->update(['monto_pagado' => 100, 'saldo_pendiente' => 0]);

    // Balance de AYER: la deuda AÚN existía → S/100.
    $ayer = $this->balances->generar($this->env->admin, now()->subDay()->toDateString());
    expect((float) $ayer->items->firstWhere('categoria', 'cxc')->monto)->toBe(100.0);

    // Balance de HOY: ya está cobrada → 0.
    $hoyBal = $this->balances->generar($this->env->admin, now()->toDateString());
    expect((float) $hoyBal->items->firstWhere('categoria', 'cxc')->monto)->toBe(0.0);
});

it('un pago a proveedor de HOY no borra la CxP del balance de AYER', function () {
    // Compra confirmada AYER de S/200 sin pagar.
    $entrada = Entrada::create([
        'empresa_id'  => $this->env->empresa->id,
        'almacen_id'  => $this->env->almacen->id,
        'user_id'     => $this->env->admin->id,
        'proveedor'   => 'Proveedor CxP',
        'fecha'       => now()->subDay()->toDateString(),
        'total'       => 200,
        'monto_pagado'=> 0,
        'estado'      => 'confirmado',
        'estado_pago' => 'pendiente',
    ]);

    // HOY se paga completa.
    $this->post(route('finanzas.cxp.abonar', $entrada), [
        'monto'          => 200,
        'fecha'          => now()->toDateString(),
        'metodo_pago_id' => $this->env->metodo('efectivo')->id,
    ])->assertSessionHasNoErrors();
    expect($entrada->fresh()->estado_pago)->toBe('pagado');

    // Balance de AYER: la deuda con el proveedor aún existía → S/200.
    $ayer = $this->balances->generar($this->env->admin, now()->subDay()->toDateString());
    expect((float) $ayer->items->firstWhere('categoria', 'cxp')->monto)->toBe(200.0);

    // Balance de HOY: pagada → 0.
    $hoyBal = $this->balances->generar($this->env->admin, now()->toDateString());
    expect((float) $hoyBal->items->firstWhere('categoria', 'cxp')->monto)->toBe(0.0);
});

it('una venta a crédito creada HOY no aparece en el balance de AYER', function () {
    $cliente = Cliente::create([
        'empresa_id' => $this->env->empresa->id,
        'nombres' => 'Cliente', 'apellidos' => 'Nuevo',
        'tipo_documento' => 'DNI', 'numero_documento' => '33333333', 'activo' => true,
    ]);
    $producto = $this->env->crearProducto(['precio_venta' => 50, 'stock_inicial' => 10]);

    // HOY: venta a crédito de S/50.
    $this->ventas->crear([
        'tipo_comprobante' => 'ticket',
        'cliente_id'       => $cliente->id,
        'es_credito'       => true,
        'items' => [[
            'producto_id'        => $producto->id,
            'producto_unidad_id' => $producto->unidadBase->id,
            'cantidad'           => 1,
            'precio_unitario'    => 50,
        ]],
        'pagos' => [],
    ], $this->env->admin, $this->turno);

    // El balance de AYER no la conoce.
    $ayer = $this->balances->generar($this->env->admin, now()->subDay()->toDateString());
    expect((float) $ayer->items->firstWhere('categoria', 'cxc')->monto)->toBe(0.0);

    // El de HOY sí.
    $hoyBal = $this->balances->generar($this->env->admin, now()->toDateString());
    expect((float) $hoyBal->items->firstWhere('categoria', 'cxc')->monto)->toBe(50.0);
});

it('una entrega de pendiente hecha HOY no baja los anticipos del balance de AYER', function () {
    $cliente = Cliente::create([
        'empresa_id' => $this->env->empresa->id,
        'nombres' => 'Jibaja', 'apellidos' => 'Test',
        'tipo_documento' => 'DNI', 'numero_documento' => '44444444', 'activo' => true,
    ]);
    $producto = $this->env->crearProducto(['precio_venta' => 20, 'stock_inicial' => 50]);

    // AYER: venta pagada con 5 und pendientes por entregar (5 × 20 = 100).
    $venta = $this->ventas->crear([
        'tipo_comprobante'  => 'ticket',
        'cliente_id'        => $cliente->id,
        'fecha_venta'       => now()->subDay()->toDateString(),
        'entrega_pendiente' => true,
        'items' => [[
            'producto_id'        => $producto->id,
            'producto_unidad_id' => $producto->unidadBase->id,
            'cantidad'           => 5,
            'precio_unitario'    => 20,
            'cantidad_pendiente' => 5,
        ]],
        'pagos' => [['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => 100]],
    ], $this->env->admin, $this->turno);

    $anticipo = \App\Models\ClienteAnticipo::where('venta_id', $venta->id)->with('items')->first();

    // HOY: se entregan las 5 (anticipo saldado).
    $this->post(route('finanzas.anticipos.aplicar', $anticipo), [
        'fecha' => now()->toDateString(),
        'items' => [['id' => $anticipo->items->first()->id, 'cantidad' => 5]],
    ])->assertSessionHasNoErrors();
    expect($anticipo->fresh()->estado)->toBe('aplicado');

    // Balance de AYER: el pasivo aún existía → S/100.
    $ayer = $this->balances->generar($this->env->admin, now()->subDay()->toDateString());
    expect((float) $ayer->items->firstWhere('categoria', 'anticipo_cliente')->monto)->toBe(100.0);

    // Balance de HOY: entregado → 0.
    $hoyBal = $this->balances->generar($this->env->admin, now()->toDateString());
    expect((float) $hoyBal->items->firstWhere('categoria', 'anticipo_cliente')->monto)->toBe(0.0);
});
