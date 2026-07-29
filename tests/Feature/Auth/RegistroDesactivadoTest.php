<?php

use Illuminate\Support\Facades\Route;

/**
 * El registro público de usuarios está DESACTIVADO a propósito.
 *
 * ─── El fallo que esto cierra ────────────────────────────────────────────────
 *
 * ventoryPOS es software cerrado: las cuentas las crea el proveedor y los
 * usuarios de cada empresa se dan de alta desde `/usuarios`, que asigna el
 * `empresa_id` del administrador que los crea.
 *
 * `RegisteredUserController::store()` es andamiaje de Breeze que nadie quitó:
 * hace `User::create()` con name/email/password y nada más, mientras que
 * `users.empresa_id` es NOT NULL. Devolvía un 500 ante CUALQUIER entrada — y
 * `Welcome.tsx`, que es página PÚBLICA, enlazaba ahí en tres sitios, más un
 * cuarto enlace en el login. Cualquier visitante que pulsara "Empezar gratis"
 * se comía un error del servidor.
 *
 * El fallo estuvo oculto porque su test vivía entre los 20 rojos crónicos de
 * Breeze que se daban por "ruido preexistente".
 *
 * ─── Qué se comprueba ────────────────────────────────────────────────────────
 *
 * Que la ruta no existe y que la interfaz no la ofrece. El controlador y
 * `Register.tsx` siguen en disco a propósito (ver routes/auth.php): lo que no
 * puede volver sin querer es la EXPOSICIÓN.
 */
it('la ruta de registro no existe', function () {
    expect(Route::has('register'))->toBeFalse();

    $this->get('/register')->assertNotFound();
    $this->post('/register', [
        'name'                  => 'Intruso',
        'email'                 => 'intruso@example.com',
        'password'              => 'password',
        'password_confirmation' => 'password',
    ])->assertNotFound();
});

it('la portada pública no ofrece crear cuenta', function () {
    // `canRegister` es lo que apaga los tres CTA de alta. Si alguien reactiva la
    // ruta, este test se pone rojo y obliga a decidir a qué empresa pertenece un
    // auto-registrado ANTES de volver a exponer el formulario.
    $this->get('/')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Welcome')
            ->where('canRegister', false));
});

it('el login no ofrece crear cuenta', function () {
    $this->get('/login')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Auth/Login')
            ->where('canRegister', false));
});
