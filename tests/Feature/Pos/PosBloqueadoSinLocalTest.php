<?php

use Tests\Support\TestEnv;

beforeEach(function () {
    $this->env = TestEnv::crear();
});

it('admin con local_id asignado y modo simple: puedeVender=true', function () {
    $this->env->abrirTurno();
    $this->actingAs($this->env->admin);

    $response = $this->get(route('pos.index'));
    $props = $response->original->getData()['page']['props'];

    expect($props['puedeVender'])->toBeTrue();
    expect($props['razonNoVender'])->toBeNull();
});

it('admin global SIN local_id en modo central_y_local: puedeVender=false con mensaje claro', function () {
    // Cambiar empresa a central_y_local y crear almacén central
    $this->env->empresa->update(['modo_almacen' => 'central_y_local']);
    \App\Models\Almacen::create([
        'empresa_id' => $this->env->empresa->id,
        'local_id'   => null,
        'nombre'     => 'Central',
        'tipo'       => 'central',
        'activo'     => true,
    ]);
    // Quitar local_id del admin (admin global)
    $this->env->admin->update(['local_id' => null]);
    $this->env->abrirTurno($this->env->admin);

    $this->actingAs($this->env->admin->fresh());

    $response = $this->get(route('pos.index'));
    $props = $response->original->getData()['page']['props'];

    expect($props['puedeVender'])->toBeFalse();
    expect($props['razonNoVender'])->toBe(
        'No tienes un local asignado. Selecciona un local para operar el POS.'
    );
});

it('en modo simple sin almacén configurado para el local: puedeVender=false con mensaje específico', function () {
    // Desactivar el único almacén
    $this->env->almacen->update(['activo' => false]);
    $this->env->abrirTurno();
    $this->actingAs($this->env->admin);

    $response = $this->get(route('pos.index'));
    $props = $response->original->getData()['page']['props'];

    expect($props['puedeVender'])->toBeFalse();
    expect($props['razonNoVender'])->toBe(
        'No hay almacén de ventas configurado para tu local. Contacta al administrador.'
    );
});
