<?php

use App\Models\Rol;
use App\Models\User;
use Tests\Support\TestEnv;

/**
 * Borrar un usuario con historial daba 500 (decenas de FKs RESTRICT hacia
 * users). Ahora, si tiene historial, se desactiva; solo se borra si está
 * limpio. El autoborrado de Breeze (DELETE /profile) ya no existe.
 */
beforeEach(function () {
    $this->env = TestEnv::crear();
    $this->cajero = User::create([
        'empresa_id' => $this->env->empresa->id,
        'local_id'   => $this->env->local->id,
        'rol_id'     => $this->env->rolAdmin->id,
        'name'       => 'Cajero',
        'email'      => 'cajero+' . uniqid('', false) . '@test.com',
        'password'   => bcrypt('secret'),
        'activo'     => true,
    ]);
});

test('eliminar un usuario con turnos lo desactiva en vez de dar error', function () {
    $this->env->abrirTurno($this->cajero);

    $this->actingAs($this->env->admin)
        ->delete(route('configuracion.usuarios.destroy', $this->cajero->id))
        ->assertRedirect()
        ->assertSessionHas('success');

    $u = User::find($this->cajero->id);
    expect($u)->not->toBeNull()->and($u->activo)->toBeFalse();
});

test('eliminar un usuario sin historial lo borra', function () {
    $this->actingAs($this->env->admin)
        ->delete(route('configuracion.usuarios.destroy', $this->cajero->id))
        ->assertRedirect();

    expect(User::find($this->cajero->id))->toBeNull();
});

test('el superadmin que elimina un usuario con historial también lo desactiva', function () {
    $this->env->abrirTurno($this->cajero);
    $super = User::create([
        'empresa_id' => null,
        'name'       => 'Super',
        'email'      => 'super+' . uniqid('', false) . '@test.com',
        'password'   => bcrypt('secret'),
        'activo'     => true,
    ]);
    $super->forceFill(['es_superadmin' => true, 'email_verified_at' => now()])->save();

    $this->actingAs($super)
        ->delete(route('admin.usuarios.destroy', $this->cajero->id))
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(User::find($this->cajero->id)?->activo)->toBeFalse();
});

test('un usuario ya no puede borrar su propia cuenta desde el perfil', function () {
    $this->actingAs($this->cajero)
        ->delete('/profile', ['password' => 'secret'])
        ->assertStatus(405);

    expect(User::find($this->cajero->id))->not->toBeNull();
});
