<?php

use Illuminate\Support\Facades\RateLimiter;
use Tests\Support\TestEnv;

/**
 * El PIN maestro de impresión no tenía límite de intentos: con 4 dígitos se
 * adivinaba en minutos. Tras 5 fallos en un minuto se bloquea con un mensaje
 * en español.
 */
beforeEach(function () {
    $this->env = TestEnv::crear();
    config(['app.print_pin' => '4321']);
    RateLimiter::clear('pin-impresion:' . $this->env->admin->id . '|127.0.0.1');
});

test('tras 5 intentos fallidos el PIN se bloquea con un mensaje claro', function () {
    $this->actingAs($this->env->admin);
    $url = route('configuracion.impresion.verificar-pin');

    for ($i = 0; $i < 5; $i++) {
        $this->postJson($url, ['pin' => '0000'])->assertStatus(422)->assertJsonPath('errors.pin.0', 'PIN incorrecto.');
    }

    // Aunque ahora acierte, sigue bloqueado hasta que pase el minuto.
    $r = $this->postJson($url, ['pin' => '4321']);
    expect($r->status())->toBeIn([422, 429]);
    expect((string) $r->json('message'))->toContain('Demasiados intentos');
});

test('el PIN correcto sigue funcionando', function () {
    $this->actingAs($this->env->admin)
        ->postJson(route('configuracion.impresion.verificar-pin'), ['pin' => '4321'])
        ->assertOk()
        ->assertJson(['ok' => true]);
});
