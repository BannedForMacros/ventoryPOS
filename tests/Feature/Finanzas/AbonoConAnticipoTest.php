<?php

use App\Models\ClienteAnticipo;
use App\Models\ClienteAnticipoAplicacion;
use App\Models\Venta;
use App\Models\VentaAbono;
use Illuminate\Support\Facades\DB;
use Tests\Support\TestEnv;

/**
 * Cobrar una cuenta por cobrar consumiendo el ANTICIPO del cliente: baja la
 * deuda y el anticipo a la vez, SIN mover tesorería (el dinero ya entró al
 * crear el anticipo). Espejo del "pagar con adelanto" de CxP.
 */

beforeEach(function () {
    $this->env   = TestEnv::crear();
    $this->turno = $this->env->abrirTurno();
    $this->actingAs($this->env->admin);
});

function crearVentaCreditoAnt($test, float $total): Venta
{
    return Venta::create([
        'empresa_id'       => $test->env->empresa->id,
        'local_id'         => $test->env->local->id,
        'turno_id'         => $test->turno->id,
        'caja_id'          => $test->env->caja->id,
        'user_id'          => $test->env->admin->id,
        'cliente_id'       => $test->env->clienteGeneral->id,
        'numero'           => 'V-' . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT),
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

function crearAnticipoDinero($test, float $monto, ?int $clienteId = null): ClienteAnticipo
{
    return ClienteAnticipo::create([
        'empresa_id'        => $test->env->empresa->id,
        'cliente_id'        => $clienteId ?? $test->env->clienteGeneral->id,
        'user_id'           => $test->env->admin->id,
        'fecha'             => now()->toDateString(),
        'monto'             => $monto,
        'saldo'             => $monto,
        'tipo_valorizacion' => 'monto',
        'estado'            => 'activo',
    ]);
}

it('cobra un abono consumiendo el anticipo: bajan deuda y anticipo, sin tesorería', function () {
    $venta    = crearVentaCreditoAnt($this, 100);
    $anticipo = crearAnticipoDinero($this, 60);

    $this->post(route('finanzas.cxc.abonar', $venta->id), [
        'monto'               => 60,
        'fecha'               => now()->toDateString(),
        'cliente_anticipo_id' => $anticipo->id,
    ])->assertSessionHasNoErrors();

    $venta->refresh();
    $anticipo->refresh();

    expect((float) $venta->saldo_pendiente)->toBe(40.0);
    expect((float) $anticipo->saldo)->toBe(0.0);
    expect($anticipo->estado)->toBe('aplicado');

    $abono = VentaAbono::where('venta_id', $venta->id)->firstOrFail();
    expect($abono->cliente_anticipo_id)->toBe($anticipo->id);
    expect($abono->metodo_pago_id)->toBeNull();

    // Aplicación trazada y enlazada al abono.
    $apl = ClienteAnticipoAplicacion::where('cliente_anticipo_id', $anticipo->id)->firstOrFail();
    expect($apl->venta_abono_id)->toBe($abono->id);
    expect((float) $apl->monto)->toBe(60.0);

    // CERO tesorería: el dinero entró cuando se creó el anticipo.
    expect(DB::table('cuenta_movimientos')->where('ref_tipo', 'venta_abono')->where('ref_id', $abono->id)->count())->toBe(0);
});

it('rechaza cobrar más que el saldo del anticipo', function () {
    $venta    = crearVentaCreditoAnt($this, 100);
    $anticipo = crearAnticipoDinero($this, 30);

    $this->post(route('finanzas.cxc.abonar', $venta->id), [
        'monto'               => 50,
        'fecha'               => now()->toDateString(),
        'cliente_anticipo_id' => $anticipo->id,
    ])->assertSessionHasErrors('monto');

    expect((float) $venta->fresh()->saldo_pendiente)->toBe(100.0);
    expect((float) $anticipo->fresh()->saldo)->toBe(30.0);
});

it('rechaza usar el anticipo de OTRO cliente', function () {
    $venta = crearVentaCreditoAnt($this, 100);

    $otroCliente = \App\Models\Cliente::create([
        'empresa_id'       => $this->env->empresa->id,
        'tipo_documento'   => 'DNI',
        'numero_documento' => '11223344',
        'nombres'          => 'Otro',
        'apellidos'        => 'Cliente',
        'activo'           => true,
    ]);
    $anticipoAjeno = crearAnticipoDinero($this, 60, $otroCliente->id);

    $this->post(route('finanzas.cxc.abonar', $venta->id), [
        'monto'               => 50,
        'fecha'               => now()->toDateString(),
        'cliente_anticipo_id' => $anticipoAjeno->id,
    ])->assertStatus(422);

    expect((float) $venta->fresh()->saldo_pendiente)->toBe(100.0);
    expect((float) $anticipoAjeno->fresh()->saldo)->toBe(60.0);
});

it('anular el abono devuelve el saldo al anticipo y reactiva su estado', function () {
    $venta    = crearVentaCreditoAnt($this, 100);
    $anticipo = crearAnticipoDinero($this, 60);

    $this->post(route('finanzas.cxc.abonar', $venta->id), [
        'monto' => 60, 'fecha' => now()->toDateString(), 'cliente_anticipo_id' => $anticipo->id,
    ])->assertSessionHasNoErrors();

    $abono = VentaAbono::where('venta_id', $venta->id)->firstOrFail();

    $this->delete(route('finanzas.cxc.abonos.destroy', $abono->id), [
        'motivo' => 'Cobro registrado por error',
    ])->assertSessionHasNoErrors();

    expect((float) $venta->fresh()->saldo_pendiente)->toBe(100.0);
    $anticipo->refresh();
    expect((float) $anticipo->saldo)->toBe(60.0);
    expect($anticipo->estado)->toBe('activo');
    expect(ClienteAnticipoAplicacion::where('cliente_anticipo_id', $anticipo->id)->count())->toBe(0);
});

it('un abono cobrado del anticipo no se puede editar', function () {
    $venta    = crearVentaCreditoAnt($this, 100);
    $anticipo = crearAnticipoDinero($this, 60);

    $this->post(route('finanzas.cxc.abonar', $venta->id), [
        'monto' => 40, 'fecha' => now()->toDateString(), 'cliente_anticipo_id' => $anticipo->id,
    ])->assertSessionHasNoErrors();

    $abono = VentaAbono::where('venta_id', $venta->id)->firstOrFail();

    $this->put(route('finanzas.cxc.abonos.update', $abono->id), [
        'monto' => 50, 'fecha' => now()->toDateString(), 'metodo_pago_id' => $this->env->metodo('efectivo')->id,
    ])->assertStatus(422);
});
