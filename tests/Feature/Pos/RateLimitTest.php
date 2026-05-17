<?php

use Tests\Support\TestEnv;

/**
 * M17 — Rate-limit `throttle:60,1` en POST /ventas.
 *
 * El middleware de throttle corre ANTES del FormRequest, así que cuenta cada
 * request que toca la ruta aunque el payload sea inválido. Eso nos permite
 * hacer un test rápido: 60 POSTs vacíos + 1 que ya debe responder 429.
 *
 * Cada `it()` crea un usuario distinto vía TestEnv::crear, así que la clave
 * del bucket (URL + user id) es independiente entre tests — no hace falta
 * limpiar el limiter manualmente.
 *
 * Usamos postJson (no post) porque bootstrap/app.php intercepta el 429 y
 * trata de renderizarlo con Inertia. En tests no hay manifest de Vite y eso
 * termina en 500. La rama JSON del handler devuelve el response tal cual.
 */
beforeEach(function () {
    $this->env = TestEnv::crear();
    $this->actingAs($this->env->admin);
});

it('un POST aislado a ventas.store NO devuelve 429', function () {
    // Payload vacío → fallará validación pero el throttle dejó pasar el request
    $response = $this->postJson(route('ventas.store'), []);
    expect($response->status())->not->toBe(429);
});

it('el 61° request en menos de 1 minuto recibe HTTP 429', function () {
    for ($i = 0; $i < 60; $i++) {
        $this->postJson(route('ventas.store'), []);
    }

    $response = $this->postJson(route('ventas.store'), []);
    expect($response->status())->toBe(429);
});
