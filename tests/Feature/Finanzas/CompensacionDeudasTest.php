<?php

use App\Models\Auditoria;
use App\Models\Deuda;
use App\Models\DeudaPago;
use Illuminate\Support\Facades\DB;
use Tests\Support\TestEnv;

/**
 * Compensación entre deudas: une una deuda por pagar contra una por cobrar,
 * crea un movimiento tipo compensación en cada una (sin tocar tesorería) y
 * reduce/cierra saldos según corresponda. No usa migraciones: aplica el
 * script SQL en cada test para que el schema esté presente.
 */

beforeEach(function () {
    $this->env = TestEnv::crear();
    $this->actingAs($this->env->admin);

    DB::unprepared(file_get_contents(database_path('scripts/add_compensacion_deuda_pagos.sql')));
});

function crearDeudaComp($test, string $direccion, float $monto, array $extra = []): Deuda
{
    return Deuda::create(array_merge([
        'empresa_id'     => $test->env->empresa->id,
        'user_id'        => $test->env->admin->id,
        'direccion'      => $direccion,
        'tipo'           => 'personal',
        'nombre'         => "Deuda {$direccion} " . uniqid(),
        'monto_original' => $monto,
        'saldo'          => $monto,
        'fecha_inicio'   => now()->toDateString(),
        'estado'         => 'activa',
    ], $extra));
}

it('compensar dos deudas opuestas reduce ambos saldos', function () {
    $porPagar  = crearDeudaComp($this, 'por_pagar', 1000);
    $porCobrar = crearDeudaComp($this, 'por_cobrar', 800);

    $this->post(route('finanzas.deudas.compensar'), [
        'deuda_por_pagar_id'  => $porPagar->id,
        'deuda_por_cobrar_id' => $porCobrar->id,
        'fecha'               => now()->toDateString(),
        'monto'               => 800,
        'observacion'         => 'Compensación manual',
    ])->assertSessionHasNoErrors()
      ->assertRedirect();

    $porPagar->refresh();
    $porCobrar->refresh();

    expect((float) $porPagar->saldo)->toBe(200.0);
    expect((float) $porCobrar->saldo)->toBe(0.0);
    expect($porCobrar->estado)->toBe('pagada');
    expect($porPagar->estado)->toBe('activa');

    expect(DeudaPago::where('tipo', 'compensacion')->count())->toBe(2);
    expect(Auditoria::where('accion', 'deuda.compensacion_creada')->exists())->toBeTrue();
});

it('el monto por defecto es el menor saldo y cierra la deuda menor', function () {
    $porPagar  = crearDeudaComp($this, 'por_pagar', 500);
    $porCobrar = crearDeudaComp($this, 'por_cobrar', 1500);

    $this->post(route('finanzas.deudas.compensar'), [
        'deuda_por_pagar_id'  => $porPagar->id,
        'deuda_por_cobrar_id' => $porCobrar->id,
        'fecha'               => now()->toDateString(),
        'monto'               => 500,
    ])->assertSessionHasNoErrors();

    $porPagar->refresh();
    $porCobrar->refresh();

    expect($porPagar->estado)->toBe('pagada');
    expect((float) $porCobrar->saldo)->toBe(1000.0);
    expect($porCobrar->estado)->toBe('activa');
});

it('rechaza compensar deudas de la misma dirección', function () {
    $a = crearDeudaComp($this, 'por_pagar', 500);
    $b = crearDeudaComp($this, 'por_pagar', 400);

    $this->post(route('finanzas.deudas.compensar'), [
        'deuda_por_pagar_id'  => $a->id,
        'deuda_por_cobrar_id' => $b->id,
        'fecha'               => now()->toDateString(),
        'monto'               => 400,
    ])->assertSessionHasErrors(['deuda_por_cobrar_id']);
});

it('rechaza compensar una deuda no activa', function () {
    $porPagar  = crearDeudaComp($this, 'por_pagar', 500);
    $porCobrar = crearDeudaComp($this, 'por_cobrar', 400);
    $porCobrar->update(['estado' => 'pagada']);

    $this->post(route('finanzas.deudas.compensar'), [
        'deuda_por_pagar_id'  => $porPagar->id,
        'deuda_por_cobrar_id' => $porCobrar->id,
        'fecha'               => now()->toDateString(),
        'monto'               => 400,
    ])->assertSessionHasErrors(['deuda_por_cobrar_id']);
});

it('rechaza compensar más del saldo disponible', function () {
    $porPagar  = crearDeudaComp($this, 'por_pagar', 300);
    $porCobrar = crearDeudaComp($this, 'por_cobrar', 400);

    $this->post(route('finanzas.deudas.compensar'), [
        'deuda_por_pagar_id'  => $porPagar->id,
        'deuda_por_cobrar_id' => $porCobrar->id,
        'fecha'               => now()->toDateString(),
        'monto'               => 350,
    ])->assertSessionHasErrors(['monto']);
});

it('eliminar un movimiento de compensación restaura ambos saldos', function () {
    $porPagar  = crearDeudaComp($this, 'por_pagar', 1000);
    $porCobrar = crearDeudaComp($this, 'por_cobrar', 800);

    $this->post(route('finanzas.deudas.compensar'), [
        'deuda_por_pagar_id'  => $porPagar->id,
        'deuda_por_cobrar_id' => $porCobrar->id,
        'fecha'               => now()->toDateString(),
        'monto'               => 500,
    ])->assertSessionHasNoErrors();

    $pago = DeudaPago::where('deuda_id', $porPagar->id)->where('tipo', 'compensacion')->first();

    $this->delete(route('finanzas.deudas.pagos.destroy', $pago), ['motivo' => 'Se compensó por error'])
        ->assertSessionHasNoErrors();

    $porPagar->refresh();
    $porCobrar->refresh();

    expect((float) $porPagar->saldo)->toBe(1000.0);
    expect((float) $porCobrar->saldo)->toBe(800.0);
    expect(DeudaPago::where('tipo', 'compensacion')->count())->toBe(0);
    expect(Auditoria::where('accion', 'deuda.compensacion_eliminada')->exists())->toBeTrue();
});

it('el endpoint activas devuelve solo deudas activas filtradas por dirección', function () {
    $pp = crearDeudaComp($this, 'por_pagar', 100);
    $pc = crearDeudaComp($this, 'por_cobrar', 200);
    crearDeudaComp($this, 'por_pagar', 50)->update(['estado' => 'pagada']);

    $res = $this->get(route('finanzas.deudas.activas', ['direccion' => 'por_pagar']));
    $res->assertOk();
    $ids = collect($res->json())->pluck('id')->all();
    expect($ids)->toContain($pp->id)
        ->not->toContain($pc->id);
});
