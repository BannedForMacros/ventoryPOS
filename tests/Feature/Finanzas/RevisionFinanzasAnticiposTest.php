<?php

use App\Http\Controllers\Finanzas\CuentasPorCobrarController;
use App\Models\ClienteAnticipo;
use App\Models\ClienteAnticipoAplicacion;
use App\Models\CuentaMovimiento;
use App\Models\Deuda;
use App\Models\Rol;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaAbono;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Tests\Support\TestEnv;

/**
 * Revisión de finanzas — anticipos de clientes. Regla: todo dinero real tiene
 * su asiento (ref_tipo/ref_id) y se revierte exacto; los cruces no mueven caja
 * y se revierten en pareja. Cada prueba fallaba antes de su arreglo.
 */

beforeEach(function () {
    $this->env = TestEnv::crear();
    $this->actingAs($this->env->admin);
    $this->efectivo = $this->env->metodo('efectivo')->id;
});

function rfaVentaCredito($test, float $total): Venta
{
    return Venta::create([
        'empresa_id'       => $test->env->empresa->id,
        'local_id'         => $test->env->local->id,
        'turno_id'         => $test->env->abrirTurno()->id,
        'caja_id'          => $test->env->caja->id,
        'user_id'          => $test->env->admin->id,
        'cliente_id'       => $test->env->clienteGeneral->id,
        'numero'           => 'V-' . random_int(10000, 99999),
        'tipo_comprobante' => 'ticket',
        'subtotal'         => $total,
        'descuento_total'  => 0,
        'igv'              => 0,
        'total'            => $total,
        'estado'           => 'completada',
        'es_credito'       => true,
        'monto_pagado'     => 0,
        'saldo_pendiente'  => $total,
        'fecha_venta'      => now(),
    ]);
}

/** Saldo a favor nacido de modificar un pedido: SIN asiento propio. */
function rfaSaldoAFavor($test, float $monto): ClienteAnticipo
{
    $venta = rfaVentaCredito($test, 200);

    return ClienteAnticipo::create([
        'empresa_id'        => $test->env->empresa->id,
        'cliente_id'        => $test->env->clienteGeneral->id,
        'user_id'           => $test->env->admin->id,
        'venta_origen_id'   => $venta->id,
        'fecha'             => now()->toDateString(),
        'monto'             => $monto,
        'saldo'             => $monto,
        'tipo_valorizacion' => 'monto',
        'estado'            => 'activo',
    ]);
}

/** Anticipo en dinero registrado por la pantalla (con su ingreso). */
function rfaAnticipo($test, float $monto, ?int $turnoId = null): ClienteAnticipo
{
    $test->post(route('finanzas.anticipos.store'), array_filter([
        'cliente_id'        => $test->env->clienteGeneral->id,
        'fecha'             => now()->toDateString(),
        'monto'             => $monto,
        'metodo_pago_id'    => $test->efectivo,
        'tipo_valorizacion' => 'monto',
        'turno_id'          => $turnoId,
    ], fn ($v) => $v !== null))->assertSessionHasNoErrors();

    return ClienteAnticipo::where('empresa_id', $test->env->empresa->id)->latest('id')->firstOrFail();
}

/** Cobra una CxC consumiendo el anticipo: crea la aplicación enlazada al abono. */
function rfaCobrarConAnticipo($test, Venta $venta, ClienteAnticipo $anticipo, float $monto): ClienteAnticipoAplicacion
{
    $test->post(route('finanzas.cxc.abonar', $venta->id), [
        'monto'               => $monto,
        'fecha'               => now()->toDateString(),
        'cliente_anticipo_id' => $anticipo->id,
    ])->assertSessionHasNoErrors();

    return ClienteAnticipoAplicacion::where('cliente_anticipo_id', $anticipo->id)->latest('id')->firstOrFail();
}

function rfaMovs(string $refTipo, int $refId)
{
    return CuentaMovimiento::where('ref_tipo', $refTipo)->where('ref_id', $refId)->get();
}

it('#1 editar un saldo a favor (sin asiento) no crea un ingreso fantasma y no deja cambiar su monto', function () {
    $saldo = rfaSaldoAFavor($this, 50);

    // Cambiar el monto: rechazado.
    $this->put(route('finanzas.anticipos.update', $saldo), [
        'cliente_id' => $this->env->clienteGeneral->id, 'fecha' => now()->toDateString(),
        'monto' => 80, 'metodo_pago_id' => $this->efectivo,
    ])->assertSessionHasErrors('monto');

    // Corregir solo la observación: se guarda, pero la tesorería no se toca.
    $this->put(route('finanzas.anticipos.update', $saldo), [
        'cliente_id' => $this->env->clienteGeneral->id, 'fecha' => now()->toDateString(),
        'monto' => 50, 'metodo_pago_id' => $this->efectivo, 'observacion' => 'corregida',
    ])->assertSessionHasNoErrors();

    expect(rfaMovs('cliente_anticipo', $saldo->id))->toHaveCount(0);
    expect($saldo->fresh()->observacion)->toBe('corregida');
    expect((float) $saldo->fresh()->monto)->toBe(50.0);
});

it('#2 reactivar un saldo a favor anulado no asienta un ingreso; uno normal recupera exactamente su asiento', function () {
    $saldo = rfaSaldoAFavor($this, 50);
    $this->post(route('finanzas.anticipos.anular', $saldo), ['accion' => 'anulado', 'motivo' => 'registro de prueba', 'metodo_pago_id' => $this->efectivo])
        ->assertSessionHasNoErrors();
    $this->post(route('finanzas.anticipos.reactivar', $saldo), ['motivo' => 'fue un error'])->assertSessionHasNoErrors();

    expect($saldo->fresh()->estado)->toBe('activo');
    expect(rfaMovs('cliente_anticipo', $saldo->id))->toHaveCount(0);

    // Anticipo con dinero real: vuelve su MISMO asiento.
    $normal = rfaAnticipo($this, 70);
    $original = rfaMovs('cliente_anticipo', $normal->id)->first();
    $this->post(route('finanzas.anticipos.anular', $normal), ['accion' => 'anulado', 'motivo' => 'registro de prueba', 'metodo_pago_id' => $this->efectivo]);
    expect(rfaMovs('cliente_anticipo', $normal->id))->toHaveCount(0);
    $this->post(route('finanzas.anticipos.reactivar', $normal), ['motivo' => 'fue un error'])->assertSessionHasNoErrors();

    $movs = rfaMovs('cliente_anticipo', $normal->id);
    expect($movs)->toHaveCount(1);
    expect($movs->first()->id)->toBe($original->id);
    expect((float) $movs->first()->monto)->toBe(70.0);
});

it('#3 "anular entrega" rechaza el consumo hecho desde un cobro de CxC y deja todo intacto', function () {
    $venta    = rfaVentaCredito($this, 100);
    $anticipo = rfaAnticipo($this, 60);
    $ap       = rfaCobrarConAnticipo($this, $venta, $anticipo, 60);

    $this->post(route('finanzas.anticipos.entrega.anular', $ap), ['motivo' => 'prueba de anulación'])
        ->assertSessionHasErrors('entrega');

    expect(ClienteAnticipoAplicacion::find($ap->id))->not->toBeNull();
    expect(VentaAbono::where('venta_id', $venta->id)->count())->toBe(1);
    expect((float) $anticipo->fresh()->saldo)->toBe(0.0);
    expect((float) $venta->fresh()->saldo_pendiente)->toBe(40.0);
});

it('#4 "editar entrega" rechaza el consumo hecho desde un cobro de CxC: ningún egreso fantasma', function () {
    $venta    = rfaVentaCredito($this, 100);
    $anticipo = rfaAnticipo($this, 60);
    $ap       = rfaCobrarConAnticipo($this, $venta, $anticipo, 60);

    $this->put(route('finanzas.anticipos.entrega.editar', $ap), [
        'monto' => 60, 'fecha' => now()->toDateString(), 'metodo_pago_id' => $this->efectivo,
    ])->assertSessionHasErrors('entrega');

    expect(rfaMovs('cliente_anticipo_entrega', $ap->id))->toHaveCount(0);
});

it('#5 no se anula como registro erróneo un anticipo ya consumido', function () {
    $anticipo = rfaAnticipo($this, 100);
    $this->post(route('finanzas.anticipos.aplicar', $anticipo), [
        'fecha' => now()->toDateString(), 'monto' => 30, 'metodo_pago_id' => $this->efectivo,
    ])->assertSessionHasNoErrors();

    $this->post(route('finanzas.anticipos.anular', $anticipo), ['accion' => 'anulado', 'motivo' => 'registro erróneo', 'metodo_pago_id' => $this->efectivo])
        ->assertSessionHasErrors('accion');

    expect($anticipo->fresh()->estado)->toBe('activo');
    expect(rfaMovs('cliente_anticipo', $anticipo->id))->toHaveCount(1);
});

it('#6 anular un abono cobrado con un anticipo ya DEVUELTO no le resucita el saldo', function () {
    $venta    = rfaVentaCredito($this, 100);
    $anticipo = rfaAnticipo($this, 100);
    rfaCobrarConAnticipo($this, $venta, $anticipo, 60);

    // Se le devuelve al cliente lo que quedaba (40).
    $this->post(route('finanzas.anticipos.anular', $anticipo), ['accion' => 'devuelto', 'motivo' => 'se le devolvió', 'metodo_pago_id' => $this->efectivo])
        ->assertSessionHasNoErrors();

    $abono = VentaAbono::where('venta_id', $venta->id)->firstOrFail();
    $this->delete(route('finanzas.cxc.abonos.destroy', $abono), ['motivo' => 'anular el cobro'])->assertSessionHasErrors();

    $anticipo->refresh();
    expect($anticipo->estado)->toBe('devuelto');
    expect((float) $anticipo->saldo)->toBe(40.0);
    expect(VentaAbono::find($abono->id))->not->toBeNull();
});

it('#9 una entrega en dinero con exceso a CxC asienta el egreso del excedente y vincula la deuda al cliente', function () {
    $anticipo = rfaAnticipo($this, 50);

    $this->post(route('finanzas.anticipos.aplicar', $anticipo), [
        'fecha' => now()->toDateString(), 'monto' => 80, 'metodo_pago_id' => $this->efectivo, 'exceso_a_cxc' => true,
    ])->assertSessionHasNoErrors();

    $deuda = Deuda::where('empresa_id', $this->env->empresa->id)->latest('id')->firstOrFail();
    expect((float) $deuda->saldo)->toBe(30.0);
    expect($deuda->cliente_id)->toBe($this->env->clienteGeneral->id);

    // Salieron 80 de la caja: 50 de la entrega + 30 del excedente (desembolso de la deuda).
    $exceso = rfaMovs('deuda', $deuda->id);
    expect($exceso)->toHaveCount(1);
    expect($exceso->first()->tipo)->toBe('egreso');
    expect((float) $exceso->first()->monto)->toBe(30.0);
});

it('#11 un cobro de CxC revalida el saldo con la venta bloqueada (no paga de más si otro cobró antes)', function () {
    $venta = rfaVentaCredito($this, 100);
    $vieja = Venta::findOrFail($venta->id); // lo que vio la pantalla: saldo 100

    // Mientras tanto otro usuario cobró 70.
    $venta->update(['monto_pagado' => 70, 'saldo_pendiente' => 30]);

    $req = Request::create('/cxc', 'POST', ['monto' => 80, 'fecha' => now()->toDateString(), 'metodo_pago_id' => $this->efectivo]);
    $req->setUserResolver(fn () => $this->env->admin);

    expect(fn () => app(CuentasPorCobrarController::class)->abonar($req, $vieja))->toThrow(ValidationException::class);
    expect(VentaAbono::where('venta_id', $venta->id)->count())->toBe(0);
    expect((float) $venta->fresh()->saldo_pendiente)->toBe(30.0);
});

it('#16 la devolución que hace un admin sin turno no se descuenta del cajón de la cajera que recibió el anticipo', function () {
    $rol = Rol::create(['empresa_id' => $this->env->empresa->id, 'nombre' => 'Cajera', 'es_admin' => false, 'activo' => true]);
    $cajera = User::create([
        'empresa_id' => $this->env->empresa->id, 'local_id' => $this->env->local->id, 'rol_id' => $rol->id,
        'name' => 'Cajera', 'email' => 'cajera+' . uniqid() . '@test.com', 'password' => bcrypt('x'), 'activo' => true,
    ]);
    $turnoCajera = $this->env->abrirTurno($cajera);

    $anticipo = rfaAnticipo($this, 40, $turnoCajera->id);
    expect($anticipo->turno_id)->toBe($turnoCajera->id);

    // El admin (sin turno abierto) devuelve el dinero.
    $this->post(route('finanzas.anticipos.anular', $anticipo), ['accion' => 'devuelto', 'motivo' => 'devuelto en oficina', 'metodo_pago_id' => $this->efectivo])
        ->assertSessionHasNoErrors();

    expect($anticipo->fresh()->turno_devolucion_id)->toBeNull();
    // La caja de la cajera conserva los 40 que sí recibió.
    expect(round($turnoCajera->fresh()->calcularMontoEsperado(), 2))->toBe(140.0);
});
