<?php

use Illuminate\Support\Facades\Route;
use Tests\Support\TestEnv;

/**
 * Un rechazo de negocio (abort 422) vuelve a la pantalla como el error
 * "aviso" (se muestra en español), no como la pantalla técnica de error.
 */
beforeEach(function () {
    $this->env = TestEnv::crear();
    $this->actingAs($this->env->admin);
    Route::middleware('web')->post('/_prueba-rechazo', fn () => abort(422, 'Esta venta ya está cerrada.'));
});

it('desde una pantalla vuelve con el aviso en español', function () {
    $this->from('/ventas')->post('/_prueba-rechazo', [], ['X-Inertia' => 'true'])
        ->assertRedirect('/ventas')
        ->assertSessionHasErrors(['aviso' => 'Esta venta ya está cerrada.']);
});

it('una petición JSON sigue recibiendo su 422 con el mensaje', function () {
    $this->postJson('/_prueba-rechazo')->assertStatus(422)->assertJson(['message' => 'Esta venta ya está cerrada.']);
});
