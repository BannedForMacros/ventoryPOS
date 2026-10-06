<?php

use App\Models\Cuenta;
use App\Services\TesoreriaService;
use Tests\Support\TestEnv;

/**
 * Desactivar una cuenta con saldo la sacaba del balance y el dinero
 * "desaparecía". Solo se desactiva con saldo 0.
 */
beforeEach(function () {
    $this->env = TestEnv::crear();
    $this->actingAs($this->env->admin);
    $this->cuenta = Cuenta::create([
        'empresa_id' => $this->env->empresa->id,
        'nombre'     => 'BCP Soles',
        'activo'     => true,
    ]);
});

test('no se puede desactivar una cuenta con saldo', function () {
    app(TesoreriaService::class)->registrar(
        $this->env->empresa->id, $this->cuenta->id, $this->env->admin,
        now()->toDateString(), 'ingreso', 150, 'Depósito de prueba',
    );

    $this->delete(route('configuracion.cuentas.destroy', $this->cuenta->id))
        ->assertSessionHasErrors('aviso');
    expect($this->cuenta->fresh()->activo)->toBeTrue();

    // Tampoco por el formulario de edición.
    $this->put(route('configuracion.cuentas.update', $this->cuenta->id), ['nombre' => 'BCP Soles', 'activo' => false])
        ->assertSessionHasErrors('aviso');
    expect($this->cuenta->fresh()->activo)->toBeTrue();
});

test('una cuenta con saldo cero sí se desactiva', function () {
    $this->delete(route('configuracion.cuentas.destroy', $this->cuenta->id))
        ->assertSessionHasNoErrors();
    expect($this->cuenta->fresh()->activo)->toBeFalse();
});
