<?php

use App\Models\PlanillaColumna;
use Tests\Support\TestEnv;

/**
 * Configuración de la planilla de caja: columnas por empresa y la columna
 * de cada medio de pago. Solo con la función activada.
 */
beforeEach(function () {
    $this->env = TestEnv::crear(['usa_planilla_caja' => true]);
    $this->actingAs($this->env->admin);
});

it('crea, renombra y borra columnas; al borrar, el medio queda con columna propia', function () {
    $this->post(route('configuracion.planilla-columnas.store'), ['nombre' => 'Depósitos'])->assertRedirect();
    $col = PlanillaColumna::where('empresa_id', $this->env->empresa->id)->firstOrFail();
    expect($col->nombre)->toBe('Depósitos');

    $this->env->metodo('transferencia')->update(['planilla_columna_id' => $col->id]);

    $this->put(route('configuracion.planilla-columnas.update', $col->id), ['nombre' => 'Transferencias y depósitos'])->assertRedirect();
    expect($col->fresh()->nombre)->toBe('Transferencias y depósitos');

    $this->delete(route('configuracion.planilla-columnas.destroy', $col->id))->assertRedirect();
    expect(PlanillaColumna::find($col->id))->toBeNull();
    expect($this->env->metodo('transferencia')->fresh()->planilla_columna_id)->toBeNull();
});

it('no permite dos columnas con el mismo nombre', function () {
    $this->post(route('configuracion.planilla-columnas.store'), ['nombre' => 'Efectivo']);
    $this->post(route('configuracion.planilla-columnas.store'), ['nombre' => 'Efectivo'])->assertSessionHasErrors('nombre');
});

it('asigna la columna al medio de pago, pero no una de otra empresa', function () {
    $mia = PlanillaColumna::create(['empresa_id' => $this->env->empresa->id, 'nombre' => 'Yape / Cuenta BCP', 'orden' => 1]);
    $ajena = PlanillaColumna::create(['empresa_id' => TestEnv::crear()->empresa->id, 'nombre' => 'Otra', 'orden' => 1]);
    $plin = $this->env->metodo('plin');
    $datos = ['nombre' => $plin->nombre, 'tipo_id' => $plin->tipo_id, 'activo' => true];

    $this->put(route('configuracion.metodos-pago.update', $plin->id), $datos + ['planilla_columna_id' => $mia->id])->assertRedirect();
    expect($plin->fresh()->planilla_columna_id)->toBe($mia->id);

    $this->put(route('configuracion.metodos-pago.update', $plin->id), $datos + ['planilla_columna_id' => $ajena->id])
        ->assertSessionHasErrors('planilla_columna_id');
});

it('sin la función activada no se pueden configurar columnas', function () {
    $this->env->empresa->update(['usa_planilla_caja' => false]);
    $this->post(route('configuracion.planilla-columnas.store'), ['nombre' => 'Efectivo'])->assertForbidden();
});
