<?php

use App\Models\Auditoria;
use App\Models\CierreInventario;
use Tests\Support\TestEnv;

beforeEach(function () {
    $this->env = TestEnv::crear();
    $this->actingAs($this->env->admin);
});

it('reabrir sin motivo da 422 con error de validación', function () {
    $turno = $this->env->abrirTurno();
    $turno->update(['estado' => 'cerrado', 'fecha_cierre' => now()]);

    $this->post(route('turnos.reabrir', $turno), [])
        ->assertSessionHasErrors('motivo');

    expect($turno->fresh()->estado)->toBe('cerrado');
});

it('reabrir con motivo demasiado corto (< 10 chars) da 422', function () {
    $turno = $this->env->abrirTurno();
    $turno->update(['estado' => 'cerrado', 'fecha_cierre' => now()]);

    $this->post(route('turnos.reabrir', $turno), ['motivo' => 'corto'])
        ->assertSessionHasErrors('motivo');
});

it('reabrir anula el CierreInventario confirmado asociado al turno', function () {
    $turno = $this->env->abrirTurno();
    $turno->update([
        'estado'       => 'cerrado',
        'fecha_cierre' => now(),
        'monto_cierre_declarado' => 100,
        'monto_cierre_esperado'  => 100,
        'diferencia'             => 0,
    ]);

    // Crear cierre de inventario confirmado para este turno
    $cierre = CierreInventario::create([
        'empresa_id'        => $this->env->empresa->id,
        'almacen_id'        => $this->env->almacen->id,
        'user_id'           => $this->env->admin->id,
        'turno_id'          => $turno->id,
        'fecha'             => now(),
        'estado'            => 'confirmado',
        'observacion'       => 'Cierre de cierre de turno',
        'total_items'       => 0,
        'total_diferencias' => 0,
    ]);

    $this->post(route('turnos.reabrir', $turno), [
        'motivo' => 'Recontamos la caja y hay diferencia real',
    ])->assertRedirect();

    $cierre->refresh();
    expect($cierre->estado)->toBe('anulado');
    expect($cierre->observacion)->toContain('Anulado por reapertura de turno');
    expect($cierre->observacion)->toContain('Recontamos la caja');
});

it('reabrir registra el motivo y los cierres anulados en la auditoría', function () {
    $turno = $this->env->abrirTurno();
    $turno->update(['estado' => 'cerrado', 'fecha_cierre' => now()]);

    $cierre = CierreInventario::create([
        'empresa_id'        => $this->env->empresa->id,
        'almacen_id'        => $this->env->almacen->id,
        'user_id'           => $this->env->admin->id,
        'turno_id'          => $turno->id,
        'fecha'             => now(),
        'estado'            => 'confirmado',
        'total_items'       => 0,
        'total_diferencias' => 0,
    ]);

    $this->post(route('turnos.reabrir', $turno), [
        'motivo' => 'Cajero olvidó declarar pago Yape de S/ 150',
    ])->assertRedirect();

    $audit = Auditoria::where('accion', 'turno.reabierto')
        ->where('modelo_id', $turno->id)
        ->latest('id')->first();
    expect($audit)->not->toBeNull();
    expect($audit->contexto['motivo'])->toBe('Cajero olvidó declarar pago Yape de S/ 150');
    expect($audit->contexto['cierres_inventario_anulados'])->toBe([$cierre->id]);
});

it('reabrir sin cierre de inventario asociado funciona y deja la lista vacía en auditoría', function () {
    $turno = $this->env->abrirTurno();
    $turno->update(['estado' => 'cerrado', 'fecha_cierre' => now()]);

    $this->post(route('turnos.reabrir', $turno), [
        'motivo' => 'Reabrir para corregir un olvido',
    ])->assertRedirect();

    $audit = Auditoria::where('accion', 'turno.reabierto')
        ->where('modelo_id', $turno->id)
        ->latest('id')->first();
    expect($audit->contexto['cierres_inventario_anulados'])->toBe([]);
});
