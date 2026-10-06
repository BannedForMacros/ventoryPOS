<?php

use App\Models\User;
use Tests\Support\TestEnv;

/**
 * Un usuario desactivado podía iniciar sesión (Auth::attempt sin 'activo') y
 * una sesión abierta seguía viva aunque se desactivara al usuario o a su
 * empresa desde /admin.
 */
beforeEach(function () {
    $this->env = TestEnv::crear();
});

test('un usuario desactivado no puede iniciar sesión y recibe un mensaje claro', function () {
    $this->env->admin->update(['activo' => false]);

    $this->post('/login', ['email' => $this->env->admin->email, 'password' => 'secret'])
        ->assertSessionHasErrors(['email' => 'Tu usuario está desactivado. Habla con el administrador.']);

    $this->assertGuest();
});

test('un usuario de una empresa desactivada no puede iniciar sesión', function () {
    $this->env->empresa->update(['activo' => false]);

    $this->post('/login', ['email' => $this->env->admin->email, 'password' => 'secret'])
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('la sesión abierta se cierra si desactivan al usuario', function () {
    $this->actingAs($this->env->admin);
    $this->env->admin->update(['activo' => false]);

    $this->get('/dashboard')->assertRedirect(route('login'));
    $this->assertGuest();
});

test('la sesión abierta se cierra si el superadmin desactiva la empresa', function () {
    $this->actingAs($this->env->admin);
    $this->env->empresa->update(['activo' => false]);

    $this->get('/dashboard')->assertRedirect(route('login'));
    $this->assertGuest();
});

test('el superadmin sin empresa sigue entrando a su panel', function () {
    $super = User::create([
        'empresa_id' => null,
        'name'       => 'Super',
        'email'      => 'super+' . uniqid('', false) . '@test.com',
        'password'   => bcrypt('secret'),
        'activo'     => true,
    ]);
    $super->forceFill(['es_superadmin' => true, 'email_verified_at' => now()])->save();

    $this->actingAs($super)->get(route('admin.empresas.index'))->assertOk();
    $this->assertAuthenticatedAs($super);
});
