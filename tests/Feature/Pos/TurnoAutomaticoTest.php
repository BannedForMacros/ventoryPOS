<?php

use App\Models\Turno;
use App\Models\User;
use App\Models\Venta;
use App\Services\VentaService;
use Tests\Support\TestEnv;

/**
 * Negocios que no usan caja: el turno existe, pero lo maneja el sistema.
 *
 * Una peluquería, una veterinaria o un taller pequeño abren la puerta y
 * trabajan. Pero una venta NO puede existir sin turno (`ventas.turno_id` es
 * obligatorio y el correlativo cuelga de él), así que el turno del día se abre
 * solo con la primera venta, UNO POR PERSONA, y se cierra solo cuando el día
 * termina.
 *
 * Todo es opt-in: una empresa que no lo encienda se comporta igual que siempre,
 * y eso es justo lo que protegen las contrapruebas de aquí.
 */

beforeEach(function () {
    $this->env = TestEnv::crear();
    $this->actingAs($this->env->admin);
    $this->producto = $this->env->crearProducto(['precio_venta' => 20, 'stock_inicial' => 50]);
    $this->efectivo = $this->env->metodo('efectivo');
});

/** Segunda persona del mismo local: dos sillas atendiendo a la vez. */
function otraPersona(): User
{
    return User::create([
        'empresa_id'        => test()->env->empresa->id,
        'local_id'          => test()->env->local->id,
        'rol_id'            => test()->env->rolAdmin->id,
        'name'              => 'Estilista 2',
        'email'             => 'estilista2+' . uniqid() . '@test.com',
        'password'          => bcrypt('secret'),
        'email_verified_at' => now(),
        'activo'            => true,
    ]);
}

/** Payload mínimo de una venta por HTTP. */
function ventaPayload(): array
{
    return [
        'tipo_comprobante' => 'ticket',
        'idempotency_key'  => 'turno-auto-' . uniqid(),
        'items' => [[
            'producto_id'        => test()->producto->id,
            'producto_unidad_id' => test()->producto->unidadBase->id,
            'cantidad'           => 1,
            'precio_unitario'    => 20,
        ]],
        'pagos' => [['metodo_pago_id' => test()->efectivo->id, 'monto' => 20]],
    ];
}

it('sin turno abierto y en modo automático, la venta abre el turno del día sola', function () {
    $this->env->empresa->update(['modo_turno' => 'automatico']);
    expect(Turno::where('user_id', $this->env->admin->id)->count())->toBe(0);

    $this->post(route('ventas.store'), ventaPayload())->assertSessionHasNoErrors();

    $turno = Turno::where('user_id', $this->env->admin->id)->firstOrFail();
    expect($turno->estado)->toBe('abierto')
        // Sin fondo: no hay cajón que cuadrar.
        ->and((float) $turno->monto_apertura)->toBe(0.0)
        ->and($turno->fecha_apertura->toDateString())->toBe(now()->toDateString());

    expect(Venta::where('turno_id', $turno->id)->count())->toBe(1);
});

it('la segunda venta del día reutiliza el mismo turno, no abre otro', function () {
    $this->env->empresa->update(['modo_turno' => 'automatico']);

    $this->post(route('ventas.store'), ventaPayload())->assertSessionHasNoErrors();
    $this->post(route('ventas.store'), ventaPayload())->assertSessionHasNoErrors();

    expect(Turno::where('user_id', $this->env->admin->id)->count())->toBe(1);
});

it('cada persona tiene SU turno del día, también con un solo local', function () {
    // Es lo que se pidió expresamente: aunque el negocio tenga un local, cada
    // quien responde por lo suyo y el reporte por profesional sigue sirviendo.
    $this->env->empresa->update(['modo_turno' => 'automatico']);
    $otra = otraPersona();

    $this->post(route('ventas.store'), ventaPayload())->assertSessionHasNoErrors();
    $this->actingAs($otra)->post(route('ventas.store'), ventaPayload())->assertSessionHasNoErrors();

    expect(Turno::where('empresa_id', $this->env->empresa->id)->count())->toBe(2)
        ->and(Turno::where('user_id', $this->env->admin->id)->count())->toBe(1)
        ->and(Turno::where('user_id', $otra->id)->count())->toBe(1);
});

it('en modo manual sigue exigiendo turno abierto, como siempre', function () {
    // Contraprueba: el cambio NO puede filtrarse a quien no lo pidió.
    $this->post(route('ventas.store'), ventaPayload())
        ->assertSessionHasErrors('turno');

    expect(Venta::where('empresa_id', $this->env->empresa->id)->count())->toBe(0);
});

it('el correlativo por día es corrido entre las personas del local', function () {
    // Con turnos por persona, numerar por turno haría que las dos emitieran su
    // V-0001 el mismo día. Por eso el alcance es elegible.
    $this->env->empresa->update(['modo_turno' => 'automatico', 'venta_correlativo_alcance' => 'dia']);
    $otra = otraPersona();

    $this->post(route('ventas.store'), ventaPayload())->assertSessionHasNoErrors();
    $this->actingAs($otra)->post(route('ventas.store'), ventaPayload())->assertSessionHasNoErrors();

    expect(Venta::where('empresa_id', $this->env->empresa->id)->orderBy('id')->pluck('numero')->all())
        ->toBe(['V-0001', 'V-0002']);
});

it('con alcance por turno, cada persona arranca en V-0001 (comportamiento de siempre)', function () {
    $this->env->empresa->update(['modo_turno' => 'automatico']); // alcance 'turno' por defecto
    $otra = otraPersona();

    $this->post(route('ventas.store'), ventaPayload())->assertSessionHasNoErrors();
    $this->actingAs($otra)->post(route('ventas.store'), ventaPayload())->assertSessionHasNoErrors();

    expect(Venta::where('empresa_id', $this->env->empresa->id)->orderBy('id')->pluck('numero')->all())
        ->toBe(['V-0001', 'V-0001']);
});

it('el cierre automático cierra los turnos de ayer sin declarar nada', function () {
    $this->env->empresa->update(['turno_cierre_automatico' => true]);

    $ayer = $this->env->abrirTurno();
    $ayer->update(['fecha_apertura' => now()->subDay()]);

    $this->artisan('turnos:cerrar-dia')->assertSuccessful();

    $ayer->refresh();
    expect($ayer->estado)->toBe('cerrado')
        ->and($ayer->fecha_cierre)->not->toBeNull()
        // Nadie contó el cajón: declarado y diferencia se quedan vacíos a
        // propósito. Rellenarlos simularía un arqueo que no ocurrió.
        ->and($ayer->monto_cierre_declarado)->toBeNull()
        ->and($ayer->diferencia)->toBeNull()
        ->and($ayer->user_cierre_id)->toBeNull();
});

it('el cierre automático NO toca el turno de hoy ni el de quien no lo activó', function () {
    // El de hoy sigue vivo: para eso es el turno del día.
    $this->env->empresa->update(['turno_cierre_automatico' => true]);
    $hoy = $this->env->abrirTurno();

    $this->artisan('turnos:cerrar-dia')->assertSuccessful();
    expect($hoy->fresh()->estado)->toBe('abierto');

    // Y una empresa sin el flag no se toca aunque tenga turnos viejos abiertos.
    $this->env->empresa->update(['turno_cierre_automatico' => false]);
    $hoy->update(['fecha_apertura' => now()->subDays(3)]);

    $this->artisan('turnos:cerrar-dia')->assertSuccessful();
    expect($hoy->fresh()->estado)->toBe('abierto');
});
