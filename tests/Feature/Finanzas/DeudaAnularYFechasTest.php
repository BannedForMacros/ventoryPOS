<?php

use App\Models\Deuda;
use Illuminate\Support\Facades\DB;
use Tests\Support\TestEnv;

/**
 * Anular una deuda deshace TODO su dinero (antes la sacaba del balance pero su
 * plata quedaba en las cuentas e inflaba el patrimonio); reactivarla lo repone
 * idéntico. Y ningún movimiento de dinero acepta fecha futura.
 */

beforeEach(function () {
    $this->env   = TestEnv::crear();
    $this->turno = $this->env->abrirTurno();
    $this->actingAs($this->env->admin);
});

function asientosDeuda(int $deudaId): array
{
    $pagoIds = DB::table('deuda_pagos')->where('deuda_id', $deudaId)->pluck('id');

    return DB::table('cuenta_movimientos')
        ->where(fn ($q) => $q->where(fn ($w) => $w->where('ref_tipo', 'deuda')->where('ref_id', $deudaId))
            ->orWhere(fn ($w) => $w->where('ref_tipo', 'deuda_pago')->whereIn('ref_id', $pagoIds)))
        ->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
}

function deudaConDinero($test): Deuda
{
    $efectivo = $test->env->metodo('efectivo')->id;
    $test->post(route('finanzas.deudas.store'), [
        'direccion' => 'por_pagar', 'tipo' => 'personal', 'nombre' => 'Préstamo a anular',
        'monto_original' => 800, 'fecha_inicio' => now()->toDateString(),
        'registrar_caja' => true, 'metodo_pago_id' => $efectivo,
    ])->assertSessionHasNoErrors();
    $deuda = Deuda::where('nombre', 'Préstamo a anular')->firstOrFail();
    $test->post(route('finanzas.deudas.pago', $deuda), [
        'tipo' => 'amortizacion', 'fecha' => now()->toDateString(), 'monto' => 300, 'metodo_pago_id' => $efectivo,
    ])->assertSessionHasNoErrors();

    return $deuda->fresh();
}

it('anular retira todo su dinero y reactivar lo devuelve idéntico', function () {
    $deuda = deudaConDinero($this);
    $antes = asientosDeuda($deuda->id);
    expect($antes)->toHaveCount(2);

    $this->post(route('finanzas.deudas.anular', $deuda), ['motivo' => 'Registrada por error'])->assertSessionHasNoErrors();
    expect($deuda->fresh()->estado)->toBe('anulada');
    expect(asientosDeuda($deuda->id))->toHaveCount(0); // ni desembolso ni cuota en las cuentas
    expect(DB::table('deuda_pagos')->where('deuda_id', $deuda->id)->whereNull('deleted_at')->count())->toBe(1); // el registro se conserva

    $this->post(route('finanzas.deudas.reactivar', $deuda), ['motivo' => 'Se anuló la equivocada'])->assertSessionHasNoErrors();
    expect($deuda->fresh()->estado)->toBe('activa');
    expect(asientosDeuda($deuda->id))->toEqual($antes);
});

it('reactivar no revive el dinero de una cuota eliminada mientras estaba anulada', function () {
    $deuda = deudaConDinero($this);
    $this->post(route('finanzas.deudas.anular', $deuda), ['motivo' => 'Registrada por error'])->assertSessionHasNoErrors();

    $pago = DB::table('deuda_pagos')->where('deuda_id', $deuda->id)->first();
    $this->delete(route('finanzas.deudas.pagos.destroy', $pago->id), ['motivo' => 'Cuota que no fue'])->assertSessionHasNoErrors();

    $this->post(route('finanzas.deudas.reactivar', $deuda), ['motivo' => 'Vuelve sin la cuota'])->assertSessionHasNoErrors();
    $asientos = asientosDeuda($deuda->id);
    expect($asientos)->toHaveCount(1);
    expect($asientos[0]['ref_tipo'])->toBe('deuda');
    // DIN-1: el saldo también vuelve sin la cuota (800, no los 500 de antes de anular).
    expect((float) $deuda->fresh()->saldo)->toBe(800.0);
    expect($deuda->fresh()->estado)->toBe('activa');
});

it('DIN-2 anular dos veces a la vez (doble clic) no pierde el dinero al reactivar', function () {
    $deuda = deudaConDinero($this);
    $antes = asientosDeuda($deuda->id);
    $stale = Deuda::find($deuda->id); // la segunda petición la cargó aún 'activa'

    $this->post(route('finanzas.deudas.anular', $deuda), ['motivo' => 'Registrada por error'])->assertSessionHasNoErrors();

    $req = \Illuminate\Http\Request::create('/x', 'POST', ['motivo' => 'Registrada por error']);
    $req->setUserResolver(fn () => $this->env->admin);
    expect(fn () => app(\App\Http\Controllers\Finanzas\DeudaController::class)->anular($req, $stale))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
    expect(\App\Models\Auditoria::where('accion', 'deuda.anulada')->where('modelo_id', $deuda->id)->count())->toBe(1);

    $this->post(route('finanzas.deudas.reactivar', $deuda), ['motivo' => 'Se anuló la equivocada'])->assertSessionHasNoErrors();
    expect(asientosDeuda($deuda->id))->toEqual($antes);
});

it('no deja anular una deuda con cruces de otros módulos', function () {
    $deuda = Deuda::create([
        'empresa_id' => $this->env->empresa->id, 'user_id' => $this->env->admin->id, 'direccion' => 'por_cobrar',
        'tipo' => 'personal', 'nombre' => 'Con cruce', 'monto_original' => 200, 'saldo' => 200,
        'fecha_inicio' => now()->toDateString(), 'estado' => 'activa', 'cliente_id' => $this->env->clienteGeneral->id,
    ]);
    $anticipo = \App\Models\ClienteAnticipo::create([
        'empresa_id' => $this->env->empresa->id, 'cliente_id' => $this->env->clienteGeneral->id,
        'user_id' => $this->env->admin->id, 'fecha' => now()->toDateString(),
        'monto' => 100, 'saldo' => 100, 'tipo_valorizacion' => 'monto', 'estado' => 'activo',
    ]);
    $this->post(route('finanzas.deudas.pago', $deuda), [
        'tipo' => 'amortizacion', 'fecha' => now()->toDateString(), 'monto' => 100, 'cliente_anticipo_id' => $anticipo->id,
    ])->assertSessionHasNoErrors();

    $this->post(route('finanzas.deudas.anular', $deuda), ['motivo' => 'Intento de anular'])->assertRedirect();
    expect($deuda->fresh()->estado)->toBe('activa');
});

it('rechaza movimientos de dinero con fecha futura, con un mensaje claro', function () {
    $manana = now()->addDay()->toDateString();

    $this->post(route('finanzas.deudas.store'), [
        'direccion' => 'por_pagar', 'tipo' => 'personal', 'nombre' => 'Futura',
        'monto_original' => 100, 'fecha_inicio' => $manana,
        'registrar_caja' => false, 'metodo_pago_id' => $this->env->metodo('efectivo')->id,
    ])->assertSessionHasErrors(['fecha_inicio' => 'La fecha no puede ser posterior a hoy (' . today()->format('d/m/Y') . ').']);

    // Hoy sí.
    $this->post(route('finanzas.deudas.store'), [
        'direccion' => 'por_pagar', 'tipo' => 'personal', 'nombre' => 'De hoy',
        'monto_original' => 100, 'fecha_inicio' => now()->toDateString(),
        'registrar_caja' => false, 'metodo_pago_id' => $this->env->metodo('efectivo')->id,
    ])->assertSessionHasNoErrors();
});
