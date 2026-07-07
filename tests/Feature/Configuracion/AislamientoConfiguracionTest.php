<?php

use App\Models\Modulo;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\User;
use Tests\Support\TestEnv;

/**
 * Aislamiento multi-empresa del módulo Configuración + anti-escalada de
 * privilegios: los controladores de Usuarios/Roles/Locales/Empresas/Permisos
 * scopean por empresa_id del usuario autenticado y solo un admin puede
 * otorgar o gestionar roles/usuarios admin.
 */
beforeEach(function () {
    $this->env  = TestEnv::crear(); // empresa A
    $this->otra = TestEnv::crear(); // empresa B
    $this->actingAs($this->env->admin);
});

/** Crea en la empresa A un usuario NO admin con permisos de Configuración. */
function usuarioConfigNoAdmin(TestEnv $env): User
{
    $rol = Rol::create([
        'empresa_id' => $env->empresa->id,
        'nombre'     => 'Encargado config ' . uniqid('', false),
        'es_admin'   => false,
        'activo'     => true,
    ]);

    foreach (['config.usuarios', 'config.roles'] as $slug) {
        $modulo = Modulo::firstOrCreate(
            ['slug' => $slug],
            ['padre_id' => null, 'nombre' => $slug, 'orden' => 99, 'activo' => true],
        );
        Permiso::create([
            'rol_id'    => $rol->id,
            'modulo_id' => $modulo->id,
            'ver'       => true,
            'crear'     => true,
            'editar'    => true,
            'eliminar'  => true,
        ]);
    }

    return User::create([
        'empresa_id' => $env->empresa->id,
        'local_id'   => $env->local->id,
        'rol_id'     => $rol->id,
        'name'       => 'Encargado',
        'email'      => 'encargado_' . uniqid('', false) . '@test.com',
        'password'   => 'secreto123',
        'activo'     => true,
    ]);
}

// ── Aislamiento cross-empresa ───────────────────────────────────────────────

it('no permite editar (ni resetear password de) un usuario de otra empresa', function () {
    $this->put(route('configuracion.usuarios.update', $this->otra->admin), [
        'local_id' => null,
        'rol_id'   => $this->env->rolAdmin->id,
        'name'     => 'Tomado',
        'email'    => $this->otra->admin->email,
        'password' => 'hackeado123',
        'activo'   => true,
    ])->assertForbidden();
});

it('no permite eliminar un usuario de otra empresa', function () {
    $this->delete(route('configuracion.usuarios.destroy', $this->otra->admin))
        ->assertForbidden();
    expect(User::find($this->otra->admin->id))->not->toBeNull();
});

it('no permite editar ni eliminar un rol de otra empresa', function () {
    $payload = ['nombre' => 'Renombrado', 'es_admin' => false, 'activo' => true];
    $this->put(route('configuracion.roles.update', $this->otra->rolAdmin), $payload)->assertForbidden();
    $this->delete(route('configuracion.roles.destroy', $this->otra->rolAdmin))->assertForbidden();
});

it('no permite editar un local de otra empresa', function () {
    $this->put(route('configuracion.locales.update', $this->otra->local), [
        'nombre' => 'Tomado',
        'activo' => true,
    ])->assertForbidden();
});

it('no permite editar otra empresa ni eliminar empresas desde el panel', function () {
    $this->put(route('configuracion.empresas.update', $this->otra->empresa), [
        'razon_social' => 'Tomada SAC',
        'ruc'          => $this->otra->empresa->ruc,
        'modo_almacen' => 'simple',
        'modo_cierre_caja' => 'con_declaraciones',
        'modo_cierre_inventario' => 'por_venta',
    ])->assertForbidden();

    $this->delete(route('configuracion.empresas.destroy', $this->env->empresa))->assertForbidden();
    expect(\App\Models\Empresa::find($this->env->empresa->id))->not->toBeNull();
});

it('no permite reescribir los permisos de un rol de otra empresa', function () {
    $modulo = Modulo::firstOrCreate(
        ['slug' => 'config.usuarios'],
        ['padre_id' => null, 'nombre' => 'config.usuarios', 'orden' => 99, 'activo' => true],
    );
    $this->post(route('configuracion.permisos.store', $this->otra->rolAdmin), [
        'permisos' => [['modulo_id' => $modulo->id, 'ver' => true]],
    ])->assertForbidden();
});

it('los índices de Configuración solo exponen datos de la propia empresa', function () {
    $this->get(route('configuracion.usuarios.index'))
        ->assertOk()
        ->assertSee($this->env->admin->email)
        ->assertDontSee($this->otra->admin->email);

    $this->get(route('configuracion.empresas.index'))
        ->assertOk()
        ->assertSee($this->env->empresa->razon_social)
        ->assertDontSee($this->otra->empresa->razon_social);
});

it('al crear un usuario, empresa_id se fuerza desde el actor y el rol debe ser de su empresa', function () {
    // rol de OTRA empresa → falla validación aunque el id exista
    $this->post(route('configuracion.usuarios.store'), [
        'empresa_id' => $this->otra->empresa->id, // se ignora
        'rol_id'     => $this->otra->rolAdmin->id,
        'name'       => 'Nuevo',
        'email'      => 'nuevo_' . uniqid('', false) . '@test.com',
        'password'   => 'secreto123',
        'activo'     => true,
    ])->assertSessionHasErrors('rol_id');

    // rol propio → se crea, pero SIEMPRE en la empresa del actor
    $email = 'nuevo_' . uniqid('', false) . '@test.com';
    $this->post(route('configuracion.usuarios.store'), [
        'empresa_id' => $this->otra->empresa->id, // se ignora
        'rol_id'     => $this->env->rolAdmin->id,
        'name'       => 'Nuevo',
        'email'      => $email,
        'password'   => 'secreto123',
        'activo'     => true,
    ])->assertRedirect();

    expect(User::where('email', $email)->first()->empresa_id)->toBe($this->env->empresa->id);
});

// ── Anti-escalada de privilegios (intra-empresa) ────────────────────────────

it('un no-admin con config.usuarios no puede asignarse un rol admin ni gestionar admins', function () {
    $encargado = usuarioConfigNoAdmin($this->env);
    $this->actingAs($encargado);

    // Cambiarse su propio rol está bloqueado de plano
    $this->put(route('configuracion.usuarios.update', $encargado), [
        'local_id' => $encargado->local_id,
        'rol_id'   => $this->env->rolAdmin->id,
        'name'     => $encargado->name,
        'email'    => $encargado->email,
        'activo'   => true,
    ])->assertForbidden();

    // Tampoco puede resetear el password del admin (toma de cuenta)
    $this->put(route('configuracion.usuarios.update', $this->env->admin), [
        'local_id' => null,
        'rol_id'   => $this->env->rolAdmin->id,
        'name'     => $this->env->admin->name,
        'email'    => $this->env->admin->email,
        'password' => 'hackeado123',
        'activo'   => true,
    ])->assertForbidden();

    // Ni crear un tercero con rol admin
    $this->post(route('configuracion.usuarios.store'), [
        'rol_id'   => $this->env->rolAdmin->id,
        'name'     => 'Cómplice',
        'email'    => 'complice_' . uniqid('', false) . '@test.com',
        'password' => 'secreto123',
        'activo'   => true,
    ])->assertForbidden();
});

it('un no-admin con config.roles no puede crear ni convertir roles a admin', function () {
    $encargado = usuarioConfigNoAdmin($this->env);
    $this->actingAs($encargado);

    $this->post(route('configuracion.roles.store'), [
        'nombre'   => 'Escalada',
        'es_admin' => true,
        'activo'   => true,
    ])->assertForbidden();

    $this->put(route('configuracion.roles.update', $encargado->rol_id), [
        'nombre'   => 'Encargado convertido',
        'es_admin' => true,
        'activo'   => true,
    ])->assertForbidden();
});

it('un admin no puede eliminarse ni desactivarse a sí mismo', function () {
    $this->delete(route('configuracion.usuarios.destroy', $this->env->admin))->assertForbidden();

    $this->put(route('configuracion.usuarios.update', $this->env->admin), [
        'local_id' => null,
        'rol_id'   => $this->env->admin->rol_id,
        'name'     => $this->env->admin->name,
        'email'    => $this->env->admin->email,
        'activo'   => false,
    ])->assertForbidden();
});

it('no se puede eliminar un rol con usuarios asignados', function () {
    $this->delete(route('configuracion.roles.destroy', $this->env->rolAdmin))
        ->assertSessionHasErrors('rol');
    expect(Rol::find($this->env->rolAdmin->id))->not->toBeNull();
});
