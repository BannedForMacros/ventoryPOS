<?php

use App\Models\Gasto;
use App\Models\GastoConcepto;
use App\Models\GastoTipo;
use App\Models\Modulo;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\User;
use Tests\Support\TestEnv;

/**
 * Revisión de gastos de turno: quién puede tocarlos y cuándo. Cada prueba
 * fallaba antes de su arreglo.
 */

beforeEach(function () {
    $this->env = TestEnv::crear();

    $this->tipo = GastoTipo::create(['empresa_id' => $this->env->empresa->id, 'nombre' => 'Operativo', 'categoria' => 'operativo', 'activo' => true]);
    $this->concepto = GastoConcepto::create(['empresa_id' => $this->env->empresa->id, 'gasto_tipo_id' => $this->tipo->id, 'nombre' => 'Movilidad', 'activo' => true]);

    $this->rolCajera = Rol::create(['empresa_id' => $this->env->empresa->id, 'nombre' => 'Cajera', 'es_admin' => false, 'activo' => true]);
    Permiso::create([
        'rol_id' => $this->rolCajera->id, 'modulo_id' => Modulo::where('slug', 'gastos')->value('id'),
        'ver' => true, 'crear' => true, 'editar' => true, 'eliminar' => true,
    ]);
});

function rgCajera($test, string $nombre): User
{
    return User::create([
        'empresa_id' => $test->env->empresa->id, 'local_id' => $test->env->local->id, 'rol_id' => $test->rolCajera->id,
        'name' => $nombre, 'email' => 'cajera+' . uniqid() . '@test.com', 'password' => bcrypt('x'), 'activo' => true,
    ]);
}

function rgGasto($test, User $user, int $turnoId, float $monto = 20): Gasto
{
    return Gasto::create([
        'empresa_id' => $test->env->empresa->id, 'local_id' => $test->env->local->id, 'user_id' => $user->id,
        'turno_id' => $turnoId, 'gasto_tipo_id' => $test->tipo->id, 'gasto_concepto_id' => $test->concepto->id,
        'monto' => $monto, 'fecha' => now()->toDateString(),
    ]);
}

function rgDatos($test, float $monto, ?string $fecha = null): array
{
    return [
        'gasto_tipo_id' => $test->tipo->id, 'gasto_concepto_id' => $test->concepto->id,
        'monto' => $monto, 'fecha' => $fecha ?? now()->toDateString(),
    ];
}

it('#13 una cajera no puede editar, eliminar ni reactivar el gasto del turno de otra cajera', function () {
    $ana  = rgCajera($this, 'Ana');
    $beto = rgCajera($this, 'Beto');
    $turnoAna = $this->env->abrirTurno($ana);
    $this->env->abrirTurno($beto);
    $gasto = rgGasto($this, $ana, $turnoAna->id);

    $this->actingAs($beto);
    $this->put(route('gastos.update', $gasto), rgDatos($this, 99))->assertForbidden();
    $this->delete(route('gastos.destroy', $gasto))->assertForbidden();
    expect((float) $gasto->fresh()->monto)->toBe(20.0);

    $gasto->delete();
    $this->post(route('gastos.restore', $gasto->id))->assertForbidden();
    expect(Gasto::withTrashed()->find($gasto->id)->trashed())->toBeTrue();

    // La dueña sí puede.
    $gasto->restore();
    $this->actingAs($ana);
    $this->put(route('gastos.update', $gasto), rgDatos($this, 25))->assertSessionHasNoErrors();
    expect((float) $gasto->fresh()->monto)->toBe(25.0);
});

it('#18 una cajera no modifica un gasto de su turno cerrado, y la fecha del gasto debe ser la del turno', function () {
    $ana = rgCajera($this, 'Ana');
    $cerrado = $this->env->abrirTurno($ana);
    $gastoCerrado = rgGasto($this, $ana, $cerrado->id);
    $cerrado->update(['estado' => 'cerrado', 'fecha_cierre' => now()]);

    $this->actingAs($ana);
    $this->put(route('gastos.update', $gastoCerrado), rgDatos($this, 99))->assertSessionHasErrors();
    $this->delete(route('gastos.destroy', $gastoCerrado))->assertSessionHasErrors();
    expect((float) $gastoCerrado->fresh()->monto)->toBe(20.0);
    expect($gastoCerrado->fresh()->trashed())->toBeFalse();

    $this->actingAs($this->env->admin);

    // Turno abierto hoy: un gasto con fecha de hace una semana no le pertenece.
    $abierto = $this->env->abrirTurno();
    $gasto = rgGasto($this, $this->env->admin, $abierto->id);
    $this->put(route('gastos.update', $gasto), rgDatos($this, 20, now()->subWeek()->toDateString()))->assertSessionHasErrors('fecha');
    expect($gasto->fresh()->fecha->toDateString())->toBe(now()->toDateString());
});

/** Turno cerrado de la cajera con un gasto mal cargado (200 en vez de 20): contó 80 en el cajón. */
function rgTurnoCerradoConGastoMalo($test): array
{
    $ana   = rgCajera($test, 'Ana');
    $turno = $test->env->abrirTurno($ana); // apertura 100
    $gasto = rgGasto($test, $ana, $turno->id, 200);
    app(\App\Services\TesoreriaService::class)->registrar($test->env->empresa->id, null, $ana, now()->toDateString(), 'egreso', 200, 'Gasto', 'gasto', $gasto->id);

    // Cierre como lo hace TurnoController::cerrar: esperado −100, declara 80 → sobrante 180.
    $turno->update([
        'estado' => 'cerrado', 'fecha_cierre' => now(), 'user_cierre_id' => $ana->id,
        'monto_cierre_esperado' => $turno->calcularMontoEsperado(), 'monto_cierre_declarado' => 80,
        'diferencia' => 80 - $turno->calcularMontoEsperado(),
    ]);
    app(\App\Services\TesoreriaService::class)->registrar($test->env->empresa->id, null, $ana, now()->toDateString(), 'ingreso', 180, 'Sobrante', 'cierre_turno', $turno->id);

    // Al día siguiente la cajera ya trabaja en otro turno de la misma caja: reabrir se bloquea.
    $test->env->abrirTurno($ana);

    return [$turno, $gasto];
}

it('DIN-4 el admin corrige el gasto de un turno cerrado sin reabrirlo y el cuadre se recalcula', function () {
    [$turno, $gasto] = rgTurnoCerradoConGastoMalo($this);
    expect((float) $turno->fresh()->diferencia)->toBe(180.0);

    $this->actingAs($this->env->admin);
    $this->put(route('gastos.update', $gasto), rgDatos($this, 20))->assertSessionHasNoErrors();

    expect((float) $gasto->fresh()->monto)->toBe(20.0);
    $turno->refresh();
    expect((float) $turno->monto_cierre_esperado)->toBe(80.0);
    expect((float) $turno->diferencia)->toBe(0.0);
    expect($turno->estado)->toBe('cerrado');
    // El sobrante que "explicaba" el gasto mal cargado desaparece: la caja no cuenta dos veces.
    expect(\App\Models\CuentaMovimiento::where('ref_tipo', 'cierre_turno')->where('ref_id', $turno->id)->count())->toBe(0);
    $netoGastoMasCierre = (float) \App\Models\CuentaMovimiento::where('empresa_id', $this->env->empresa->id)
        ->whereIn('ref_tipo', ['gasto', 'cierre_turno'])->get()->sum(fn ($m) => $m->tipo === 'ingreso' ? (float) $m->monto : -(float) $m->monto);
    expect(round($netoGastoMasCierre, 2))->toBe(-20.0);

    // Eliminarlo también recuadra: sin gasto, la cajera contó 20 de menos (faltante).
    $this->delete(route('gastos.destroy', $gasto))->assertSessionHasNoErrors();
    expect((float) $turno->fresh()->diferencia)->toBe(-20.0);
    $faltante = \App\Models\CuentaMovimiento::where('ref_tipo', 'cierre_turno')->where('ref_id', $turno->id)->first();
    expect($faltante->tipo)->toBe('egreso');
    expect((float) $faltante->monto)->toBe(20.0);

    // Y reactivarlo lo devuelve a cuadre.
    $this->post(route('gastos.restore', $gasto->id))->assertSessionHasNoErrors();
    expect((float) $turno->fresh()->diferencia)->toBe(0.0);
    expect(\App\Models\CuentaMovimiento::where('ref_tipo', 'cierre_turno')->where('ref_id', $turno->id)->count())->toBe(0);
});

it('DIN-4 un turno ya consolidado no se recuadra solo: pide reabrir', function () {
    [$turno, $gasto] = rgTurnoCerradoConGastoMalo($this);
    \App\Models\TurnoConsolidacion::create([
        'turno_id' => $turno->id, 'empresa_id' => $turno->empresa_id, 'user_id' => $this->env->admin->id,
        'fecha' => now()->toDateString(), 'efectivo_declarado' => 80, 'efectivo_esperado' => -100,
        'caja_chica' => 0, 'efectivo_contado' => 80,
    ]);

    $this->actingAs($this->env->admin);
    $this->put(route('gastos.update', $gasto), rgDatos($this, 20))->assertSessionHasErrors();
    expect((float) $gasto->fresh()->monto)->toBe(200.0);
});
