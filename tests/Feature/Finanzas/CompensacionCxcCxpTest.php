<?php

use App\Models\Entrada;
use App\Models\EntradaPago;
use App\Models\Venta;
use App\Models\VentaAbono;
use Illuminate\Support\Facades\DB;
use Tests\Support\TestEnv;

/**
 * Compensación CxC↔CxP: una venta al crédito se cancela contra una compra
 * con saldo SIN mover dinero de caja. Anular cualquiera de los dos lados
 * revierte también al hermano.
 */

beforeEach(function () {
    $this->env   = TestEnv::crear();
    $this->turno = $this->env->abrirTurno();
    $this->actingAs($this->env->admin);
});

function crearVentaCredito($test, float $total): Venta
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

function crearCompraConSaldo($test, float $total): Entrada
{
    return Entrada::create([
        'empresa_id'   => $test->env->empresa->id,
        'almacen_id'   => $test->env->almacen->id,
        'user_id'      => $test->env->admin->id,
        'proveedor'    => 'Proveedor Compensable SAC',
        'tipo'         => 'compra',
        'fecha'        => now()->toDateString(),
        'estado'       => 'confirmado',
        'total'        => $total,
        'monto_pagado' => 0,
        'estado_pago'  => 'pendiente',
    ]);
}

it('compensa una CxC contra una CxP: ambos saldos bajan sin tocar tesorería', function () {
    $venta   = crearVentaCredito($this, 100);
    $entrada = crearCompraConSaldo($this, 80);

    $this->post(route('finanzas.compensaciones.cxc-cxp'), [
        'venta_id'   => $venta->id,
        'entrada_id' => $entrada->id,
        'monto'      => 80,
        'fecha'      => now()->toDateString(),
    ])->assertSessionHasNoErrors();

    $venta->refresh();
    $entrada->refresh();

    expect((float) $venta->saldo_pendiente)->toBe(20.0);
    expect((float) $venta->monto_pagado)->toBe(80.0);
    expect($entrada->estado_pago)->toBe('pagado');
    expect((float) $entrada->monto_pagado)->toBe(80.0);

    // Ambos registros enlazados por el mismo grupo, sin método ni cuenta.
    $abono = VentaAbono::where('venta_id', $venta->id)->firstOrFail();
    $pago  = EntradaPago::where('entrada_id', $entrada->id)->firstOrFail();
    expect($abono->compensacion_grupo_id)->not->toBeNull();
    expect($abono->compensacion_grupo_id)->toBe($pago->compensacion_grupo_id);
    expect($abono->metodo_pago_id)->toBeNull();
    expect($pago->metodo_pago_id)->toBeNull();

    // CERO movimientos de tesorería: no entró ni salió dinero real.
    expect(DB::table('cuenta_movimientos')->where('ref_tipo', 'venta_abono')->where('ref_id', $abono->id)->count())->toBe(0);
    expect(DB::table('cuenta_movimientos')->where('ref_tipo', 'entrada_pago')->where('ref_id', $pago->id)->count())->toBe(0);
});

it('rechaza compensar más que el menor de los dos saldos', function () {
    $venta   = crearVentaCredito($this, 100);
    $entrada = crearCompraConSaldo($this, 80);

    $this->post(route('finanzas.compensaciones.cxc-cxp'), [
        'venta_id'   => $venta->id,
        'entrada_id' => $entrada->id,
        'monto'      => 90, // > saldo de la compra (80)
        'fecha'      => now()->toDateString(),
    ])->assertSessionHasErrors('monto');

    expect((float) $venta->fresh()->saldo_pendiente)->toBe(100.0);
    expect((float) $entrada->fresh()->monto_pagado)->toBe(0.0);
});

it('anular el pago compensado (lado CxP) revierte también el abono de la venta', function () {
    $venta   = crearVentaCredito($this, 100);
    $entrada = crearCompraConSaldo($this, 80);

    $this->post(route('finanzas.compensaciones.cxc-cxp'), [
        'venta_id' => $venta->id, 'entrada_id' => $entrada->id,
        'monto' => 50, 'fecha' => now()->toDateString(),
    ])->assertSessionHasNoErrors();

    $pago = EntradaPago::where('entrada_id', $entrada->id)->firstOrFail();

    $this->delete(route('finanzas.cxp.pagos.destroy', $pago->id), [
        'motivo' => 'Compensación registrada por error',
    ])->assertSessionHasNoErrors();

    expect((float) $venta->fresh()->saldo_pendiente)->toBe(100.0);
    expect((float) $entrada->fresh()->monto_pagado)->toBe(0.0);
    expect(VentaAbono::where('venta_id', $venta->id)->count())->toBe(0);
    expect(EntradaPago::where('entrada_id', $entrada->id)->count())->toBe(0);
});

it('anular el abono compensado (lado CxC) revierte también el pago de la compra', function () {
    $venta   = crearVentaCredito($this, 100);
    $entrada = crearCompraConSaldo($this, 80);

    $this->post(route('finanzas.compensaciones.cxc-cxp'), [
        'venta_id' => $venta->id, 'entrada_id' => $entrada->id,
        'monto' => 50, 'fecha' => now()->toDateString(),
    ])->assertSessionHasNoErrors();

    $abono = VentaAbono::where('venta_id', $venta->id)->firstOrFail();

    $this->delete(route('finanzas.cxc.abonos.destroy', $abono->id), [
        'motivo' => 'Compensación registrada por error',
    ])->assertSessionHasNoErrors();

    expect((float) $venta->fresh()->saldo_pendiente)->toBe(100.0);
    expect((float) $entrada->fresh()->monto_pagado)->toBe(0.0);
    expect($entrada->fresh()->estado_pago)->toBe('pendiente');
    expect(VentaAbono::where('venta_id', $venta->id)->count())->toBe(0);
    expect(EntradaPago::where('entrada_id', $entrada->id)->count())->toBe(0);
});

it('un abono/pago por compensación no se puede editar', function () {
    $venta   = crearVentaCredito($this, 100);
    $entrada = crearCompraConSaldo($this, 80);

    $this->post(route('finanzas.compensaciones.cxc-cxp'), [
        'venta_id' => $venta->id, 'entrada_id' => $entrada->id,
        'monto' => 50, 'fecha' => now()->toDateString(),
    ])->assertSessionHasNoErrors();

    $abono = VentaAbono::where('venta_id', $venta->id)->firstOrFail();
    $pago  = EntradaPago::where('entrada_id', $entrada->id)->firstOrFail();

    $this->put(route('finanzas.cxc.abonos.update', $abono->id), [
        'monto' => 60, 'fecha' => now()->toDateString(), 'metodo_pago_id' => $this->env->metodo('efectivo')->id,
    ])->assertStatus(422);

    $this->put(route('finanzas.cxp.pagos.update', $pago->id), [
        'monto' => 60, 'fecha' => now()->toDateString(), 'metodo_pago_id' => $this->env->metodo('efectivo')->id,
    ])->assertStatus(422);
});
