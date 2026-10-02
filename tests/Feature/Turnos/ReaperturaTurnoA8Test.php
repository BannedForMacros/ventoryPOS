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

it('reabrir anula la entrega a administración del cierre anterior (al cerrar de nuevo no queda duplicada)', function () {
    $turno = $this->env->abrirTurno();
    $turno->update([
        'estado'            => 'cerrado',
        'fecha_cierre'      => now(),
        'efectivo_arrastre' => 1500,
        'destino_efectivo'  => 'parcial',
    ]);
    // Entrega mal declarada al cerrar + un retiro hecho DURANTE el turno.
    $alCierre = App\Models\TurnoRetiro::create([
        'empresa_id' => $this->env->empresa->id, 'turno_id' => $turno->id, 'user_id' => $this->env->admin->id,
        'concepto' => App\Models\TurnoRetiro::CONCEPTO_ENTREGA_ADMIN, 'monto' => 2770.20,
        'momento' => 'cierre', 'estado' => 'aprobado',
    ]);
    $durante = App\Models\TurnoRetiro::create([
        'empresa_id' => $this->env->empresa->id, 'turno_id' => $turno->id, 'user_id' => $this->env->admin->id,
        'concepto' => App\Models\TurnoRetiro::CONCEPTO_ENTREGA_ADMIN, 'monto' => 300,
        'momento' => 'turno', 'estado' => 'aprobado',
    ]);

    $this->post(route('turnos.reabrir', $turno), [
        'motivo' => 'La cajera entregó mal el efectivo al cerrar',
    ])->assertRedirect();

    $turno->refresh();
    expect(App\Models\TurnoRetiro::find($alCierre->id))->toBeNull();      // ya no cuenta: se vuelve a declarar al cerrar
    $anulada = App\Models\TurnoRetiro::withoutGlobalScope('vigentes')->find($alCierre->id);
    expect($anulada)->not->toBeNull();                                     // la fila se conserva
    expect($anulada->estado)->toBe('anulado');
    expect($anulada->observacion)->toContain('Anulada al reabrir el turno');
    expect((float) $turno->retiros()->sum('monto'))->toBe(300.0);
    expect(App\Models\TurnoRetiro::find($durante->id))->not->toBeNull();  // lo del turno sigue
    expect($turno->efectivo_arrastre)->toBeNull();
    expect($turno->destino_efectivo)->toBeNull();

    $audit = Auditoria::where('accion', 'turno.reabierto')->where('modelo_id', $turno->id)->latest('id')->first();
    expect($audit->contexto['cierre_anterior']['entregas_cierre'][0]['monto'])->toBe('2770.20');
    expect($audit->contexto['cierre_anterior']['efectivo_arrastre'])->toEqual(1500);
});
