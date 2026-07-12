<?php

use App\Models\Cliente;
use App\Models\ClienteAnticipo;
use App\Models\CuentaMovimiento;
use App\Models\Turno;
use App\Models\User;
use App\Models\Venta;
use App\Services\BalanceDiarioService;
use App\Services\VentaService;
use Tests\Support\TestEnv;

/**
 * Backdate de admin: registrar una venta olvidada en un turno REABIERTO de un
 * día anterior, con la fecha real de la operación (venta + tesorería + anticipo
 * pendiente), y reabrir el balance confirmado de ese día para que la recoja.
 */
beforeEach(function () {
    $this->env = TestEnv::crear();
    $this->service = app(VentaService::class);
    $this->actingAs($this->env->admin);
});

it('crear con fecha_venta backdated asienta venta, tesorería y anticipo en esa fecha', function () {
    $turno = $this->env->abrirTurno();
    $ayer  = now()->subDay()->toDateString();

    $cliente = Cliente::create([
        'empresa_id' => $this->env->empresa->id,
        'nombres' => 'Constructora', 'apellidos' => 'Jibaja',
        'tipo_documento' => 'DNI', 'numero_documento' => '11111111', 'activo' => true,
    ]);
    $fierro = $this->env->crearProducto(['precio_venta' => 20, 'stock_inicial' => 50]);

    $venta = $this->service->crear([
        'tipo_comprobante'  => 'ticket',
        'cliente_id'        => $cliente->id,
        'fecha_venta'       => $ayer,
        'entrega_pendiente' => true,
        'items' => [[
            'producto_id'        => $fierro->id,
            'producto_unidad_id' => $fierro->unidadBase->id,
            'cantidad'           => 10,
            'precio_unitario'    => 20,
            'cantidad_pendiente' => 7,
        ]],
        'pagos' => [['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => 200]],
    ], $this->env->admin, $turno);

    // La venta quedó asentada AYER.
    expect($venta->fecha_venta->toDateString())->toBe($ayer);

    // El ingreso a tesorería también es de AYER.
    $mov = CuentaMovimiento::where('ref_tipo', 'venta')->where('ref_id', $venta->id)->first();
    expect($mov)->not->toBeNull();
    expect(substr((string) $mov->fecha, 0, 10))->toBe($ayer);

    // Y el anticipo "pendiente por entregar" nace con fecha de AYER.
    $anticipo = ClienteAnticipo::where('venta_id', $venta->id)->first();
    expect($anticipo->fecha->toDateString())->toBe($ayer);
});

it('sin fecha_venta la venta se asienta hoy (flujo normal intacto)', function () {
    $turno    = $this->env->abrirTurno();
    $producto = $this->env->crearProducto(['precio_venta' => 10]);

    $venta = $this->service->crear([
        'tipo_comprobante' => 'ticket',
        'items' => [[
            'producto_id'        => $producto->id,
            'producto_unidad_id' => $producto->unidadBase->id,
            'cantidad'           => 1,
            'precio_unitario'    => 10,
        ]],
        'pagos' => [['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => 10]],
    ], $this->env->admin, $turno);

    expect($venta->fecha_venta->toDateString())->toBe(now()->toDateString());
});

it('el admin puede registrar por HTTP en el turno abierto de otra usuaria con la fecha del turno', function () {
    // Turno de una cajera, abierto AYER (simula un turno reabierto).
    $cajera = User::create([
        'empresa_id' => $this->env->empresa->id,
        'local_id'   => $this->env->local->id,
        'rol_id'     => $this->env->rolAdmin->id,
        'name'       => 'Cajera Uno',
        'email'      => 'cajera-backdate@test.com',
        'password'   => bcrypt('12345678'),
        'activo'     => true,
    ]);
    $turnoAyer = $this->env->abrirTurno($cajera);
    $turnoAyer->update(['fecha_apertura' => now()->subDay()]);

    $producto = $this->env->crearProducto(['precio_venta' => 15, 'stock_inicial' => 20]);

    // El ADMIN (sin turno propio) postea al store con turno_id + fecha de ayer.
    $this->post(route('ventas.store'), [
        'tipo_comprobante' => 'ticket',
        'turno_id'         => $turnoAyer->id,
        'fecha_venta'      => now()->subDay()->toDateString(),
        'items' => [[
            'producto_id'        => $producto->id,
            'producto_unidad_id' => $producto->unidadBase->id,
            'cantidad'           => 2,
            'precio_unitario'    => 15,
        ]],
        'pagos' => [['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => 30]],
    ])->assertSessionHasNoErrors();

    $venta = Venta::where('turno_id', $turnoAyer->id)->first();
    expect($venta)->not->toBeNull();
    expect($venta->fecha_venta->toDateString())->toBe(now()->subDay()->toDateString());
    expect($venta->user_id)->toBe($this->env->admin->id); // quién la registró queda auditado
});

it('rechaza fecha_venta fuera del rango [apertura del turno, hoy]', function () {
    $turno = $this->env->abrirTurno(); // abierto hoy
    $producto = $this->env->crearProducto(['precio_venta' => 10]);

    $this->post(route('ventas.store'), [
        'tipo_comprobante' => 'ticket',
        'turno_id'         => $turno->id,
        'fecha_venta'      => now()->subDays(3)->toDateString(), // antes de la apertura
        'items' => [[
            'producto_id'        => $producto->id,
            'producto_unidad_id' => $producto->unidadBase->id,
            'cantidad'           => 1,
            'precio_unitario'    => 10,
        ]],
        'pagos' => [['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => 10]],
    ])->assertSessionHasErrors(['fecha_venta']);

    expect(Venta::where('turno_id', $turno->id)->count())->toBe(0);
});

it('reabrir balance confirmado lo vuelve borrador y se regenera con los datos nuevos', function () {
    $balanceService = app(BalanceDiarioService::class);
    $ayer = now()->subDay()->toDateString();

    // Balance de AYER confirmado (sin la venta olvidada).
    $balance = $balanceService->generar($this->env->admin, $ayer);
    $balanceService->confirmar($balance, $this->env->admin);
    expect($balance->fresh()->estado)->toBe('confirmado');

    // Reabrir (admin, con motivo).
    $this->post(route('finanzas.balance.reabrir', $balance->id), [
        'motivo' => 'Venta del día registrada tarde en turno reabierto',
    ])->assertSessionHasNoErrors();

    expect($balance->fresh()->estado)->toBe('borrador');
});

it('no permite reabrir un balance que no es el último confirmado', function () {
    $balanceService = app(BalanceDiarioService::class);

    $b1 = $balanceService->generar($this->env->admin, now()->subDays(2)->toDateString());
    $balanceService->confirmar($b1, $this->env->admin);
    $b2 = $balanceService->generar($this->env->admin, now()->subDay()->toDateString());
    $balanceService->confirmar($b2, $this->env->admin);

    $this->post(route('finanzas.balance.reabrir', $b1->id), [
        'motivo' => 'Intento de reabrir un balance antiguo',
    ])->assertSessionHasErrors(['balance']);

    expect($b1->fresh()->estado)->toBe('confirmado');
});
