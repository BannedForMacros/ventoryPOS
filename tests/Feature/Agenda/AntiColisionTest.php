<?php

use App\Models\Cita;
use App\Services\CitaService;
use Tests\Support\TestEnv;

beforeEach(function () {
    $this->env = TestEnv::crear();
    $this->actingAs($this->env->admin);
});

/**
 * Helper local: payload base válido (futuro, profesional asignado, 1 item).
 */
function payloadCita(TestEnv $env, array $extra = []): array
{
    $producto = $env->crearProducto(['precio_venta' => 30]);
    return array_merge([
        'local_id'   => $env->local->id,
        'cliente_id' => $env->clienteGeneral->id,
        'fecha_hora' => now()->addDay()->setTime(10, 0)->toDateTimeString(),
        'profesional_id' => $env->admin->id,
        'items' => [[
            'producto_id'        => $producto->id,
            'producto_unidad_id' => $producto->unidadBase->id,
            'cantidad'           => 1,
            'duracion_min'       => 60,
        ]],
    ], $extra);
}

it('rechaza una cita con fecha_hora en el pasado', function () {
    $payload = payloadCita($this->env, [
        'fecha_hora' => now()->subDay()->toDateTimeString(),
    ]);

    $this->post(route('agenda.store'), $payload)
        ->assertSessionHasErrors('fecha_hora');
});

it('rechaza una segunda cita del mismo profesional que solapa el horario', function () {
    // Cita 1: mañana 10:00, dura 60 min → ocupa hasta 11:00
    app(CitaService::class)->crear(payloadCita($this->env), $this->env->admin);

    // Cita 2: mañana 10:30 → solape de 30 min
    $colision = payloadCita($this->env, [
        'fecha_hora' => now()->addDay()->setTime(10, 30)->toDateTimeString(),
    ]);

    $this->post(route('agenda.store'), $colision)
        ->assertSessionHasErrors('fecha_hora');

    // Solo la primera quedó persistida
    expect(Cita::where('empresa_id', $this->env->empresa->id)->count())->toBe(1);
});

it('permite citas back-to-back del mismo profesional (10:00 dura 60 + 11:00 dura 30)', function () {
    app(CitaService::class)->crear(payloadCita($this->env), $this->env->admin);

    $payload = payloadCita($this->env, [
        'fecha_hora' => now()->addDay()->setTime(11, 0)->toDateTimeString(),
    ]);
    // sobreescribimos duracion del item a 30 con un producto nuevo
    $producto = $this->env->crearProducto(['precio_venta' => 20]);
    $payload['items'] = [[
        'producto_id'        => $producto->id,
        'producto_unidad_id' => $producto->unidadBase->id,
        'cantidad'           => 1,
        'duracion_min'       => 30,
    ]];

    $this->post(route('agenda.store'), $payload)->assertRedirect();
    expect(Cita::where('empresa_id', $this->env->empresa->id)->count())->toBe(2);
});

it('permite solape SI los profesionales son distintos', function () {
    // Profesional A
    app(CitaService::class)->crear(payloadCita($this->env), $this->env->admin);

    // Profesional B (otro user en la misma empresa+local)
    $otroPro = \App\Models\User::create([
        'empresa_id' => $this->env->empresa->id,
        'local_id'   => $this->env->local->id,
        'rol_id'     => $this->env->rolAdmin->id,
        'name'       => 'Profesional B',
        'email'      => 'pro_b_' . uniqid() . '@test.com',
        'password'   => bcrypt('x'),
        'email_verified_at' => now(),
        'activo'     => true,
    ]);

    $payload = payloadCita($this->env, [
        'profesional_id' => $otroPro->id,
        'fecha_hora'     => now()->addDay()->setTime(10, 30)->toDateTimeString(),
    ]);

    $this->post(route('agenda.store'), $payload)->assertRedirect();
    expect(Cita::where('empresa_id', $this->env->empresa->id)->count())->toBe(2);
});

it('cuando NO se asigna profesional, valida solape contra el local', function () {
    // Cita sin profesional asignado
    $payload1 = payloadCita($this->env, ['profesional_id' => null]);
    app(CitaService::class)->crear($payload1, $this->env->admin);

    // Segunda cita en el mismo local, sin profesional, horario solapado
    $payload2 = payloadCita($this->env, [
        'profesional_id' => null,
        'fecha_hora'     => now()->addDay()->setTime(10, 30)->toDateTimeString(),
    ]);

    $this->post(route('agenda.store'), $payload2)
        ->assertSessionHasErrors('fecha_hora');
});
