<?php

use App\Models\Rol;
use Tests\Support\TestEnv;

beforeEach(function () {
    $this->env = TestEnv::crear();
    $this->actingAs($this->env->admin);
});

/**
 * M20 FE — el panel de Roles permite definir/editar `max_descuento_porcentaje`.
 * Estos tests cubren el flujo HTTP que el admin ejecuta desde la pantalla.
 */
it('crear rol con tope definido lo persiste como decimal', function () {
    $this->post(route('configuracion.roles.store'), [
        'empresa_id'               => $this->env->empresa->id,
        'nombre'                   => 'Cajero limitado',
        'descripcion'              => 'Solo puede aplicar hasta 10% de descuento',
        'es_admin'                 => false,
        'activo'                   => true,
        'max_descuento_porcentaje' => 10,
    ])->assertRedirect();

    $rol = Rol::where('nombre', 'Cajero limitado')->first();
    expect($rol)->not->toBeNull();
    expect((float) $rol->max_descuento_porcentaje)->toBe(10.0);
});

it('crear rol con tope vacío persiste null (sin tope, típicamente admin)', function () {
    $this->post(route('configuracion.roles.store'), [
        'empresa_id'               => $this->env->empresa->id,
        'nombre'                   => 'Admin sin tope',
        'descripcion'              => null,
        'es_admin'                 => true,
        'activo'                   => true,
        'max_descuento_porcentaje' => '', // string vacío del input
    ])->assertRedirect();

    $rol = Rol::where('nombre', 'Admin sin tope')->first();
    expect($rol->max_descuento_porcentaje)->toBeNull();
});

it('crear rol con tope > 100 falla validación', function () {
    $this->post(route('configuracion.roles.store'), [
        'empresa_id'               => $this->env->empresa->id,
        'nombre'                   => 'Imposible',
        'es_admin'                 => false,
        'activo'                   => true,
        'max_descuento_porcentaje' => 150, // > 100% no tiene sentido
    ])->assertSessionHasErrors('max_descuento_porcentaje');

    expect(Rol::where('nombre', 'Imposible')->exists())->toBeFalse();
});

it('crear rol con tope negativo falla validación', function () {
    $this->post(route('configuracion.roles.store'), [
        'empresa_id'               => $this->env->empresa->id,
        'nombre'                   => 'Negativo',
        'es_admin'                 => false,
        'activo'                   => true,
        'max_descuento_porcentaje' => -5,
    ])->assertSessionHasErrors('max_descuento_porcentaje');
});

it('editar rol existente cambia el tope persistido', function () {
    $rol = Rol::create([
        'empresa_id' => $this->env->empresa->id,
        'nombre'     => 'Pruebas',
        'es_admin'   => false,
        'activo'     => true,
        'max_descuento_porcentaje' => 5,
    ]);

    $this->put(route('configuracion.roles.update', $rol->id), [
        'empresa_id'               => $this->env->empresa->id,
        'nombre'                   => 'Pruebas',
        'es_admin'                 => false,
        'activo'                   => true,
        'max_descuento_porcentaje' => 25.5,
    ])->assertRedirect();

    expect((float) $rol->fresh()->max_descuento_porcentaje)->toBe(25.5);
});

it('editar rol enviando tope vacío lo cambia a null', function () {
    $rol = Rol::create([
        'empresa_id' => $this->env->empresa->id,
        'nombre'     => 'Promovido',
        'es_admin'   => false,
        'activo'     => true,
        'max_descuento_porcentaje' => 10,
    ]);

    $this->put(route('configuracion.roles.update', $rol->id), [
        'empresa_id'               => $this->env->empresa->id,
        'nombre'                   => 'Promovido',
        'es_admin'                 => true,
        'activo'                   => true,
        'max_descuento_porcentaje' => '', // promovido a admin, sin tope
    ])->assertRedirect();

    expect($rol->fresh()->max_descuento_porcentaje)->toBeNull();
});

it('el index expone max_descuento_porcentaje en cada rol', function () {
    Rol::create([
        'empresa_id' => $this->env->empresa->id,
        'nombre'     => 'Cajero 15%',
        'es_admin'   => false,
        'activo'     => true,
        'max_descuento_porcentaje' => 15,
    ]);

    $response = $this->get(route('configuracion.roles.index'));
    $props = $response->original->getData()['page']['props'];

    $rolEncontrado = collect($props['roles'])->firstWhere('nombre', 'Cajero 15%');
    expect($rolEncontrado)->not->toBeNull();
    expect((float) $rolEncontrado['max_descuento_porcentaje'])->toBe(15.0);
});
