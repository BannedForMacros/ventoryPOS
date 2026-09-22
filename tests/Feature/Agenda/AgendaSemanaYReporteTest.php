<?php

use App\Models\Cita;
use App\Services\CitaService;
use Carbon\Carbon;
use Tests\Support\TestEnv;

/**
 * La vista semanal y el reporte por profesional.
 *
 * "Profesional" es neutro a propósito, como la columna `citas.profesional_id`:
 * es la estilista de una peluquería, el veterinario de una clínica o el técnico
 * de un taller. Ni la agenda ni el reporte hablan de un rubro concreto.
 */

beforeEach(function () {
    $this->env = TestEnv::crear();
    $this->svc = app(CitaService::class);
    $this->actingAs($this->env->admin);

    $this->servicio = $this->env->crearProducto(['precio_venta' => 50, 'nombre' => 'Servicio de prueba']);
});

/** Crea una cita en la fecha/hora pedida, opcionalmente con un profesional. */
function citaEn(string $fechaHora, ?int $profesionalId = null, int $duracion = 60): Cita
{
    return test()->svc->crear([
        'local_id'       => test()->env->local->id,
        'cliente_id'     => test()->env->clienteGeneral->id,
        'profesional_id' => $profesionalId,
        'fecha_hora'     => $fechaHora,
        'items' => [[
            'producto_id'        => test()->servicio->id,
            'producto_unidad_id' => test()->servicio->unidadBase->id,
            'cantidad'           => 1,
            'duracion_min'       => $duracion,
        ]],
    ], test()->env->admin);
}

it('la vista semanal abre la fecha pedida a su semana completa, de lunes a domingo', function () {
    // Un miércoles cualquiera, futuro (el formulario no admite citas pasadas).
    $miercoles = Carbon::today()->next(Carbon::WEDNESDAY);

    $this->get(route('agenda.index', ['vista' => 'semana', 'fecha_desde' => $miercoles->toDateString()]))
        ->assertInertia(fn ($page) => $page
            ->where('vista', 'semana')
            ->where('filters.fecha_desde', $miercoles->copy()->startOfWeek(Carbon::MONDAY)->toDateString())
            ->where('filters.fecha_hasta', $miercoles->copy()->endOfWeek(Carbon::SUNDAY)->toDateString()));
});

it('la vista lista respeta el día pedido sin abrirlo a la semana', function () {
    $dia = Carbon::today()->next(Carbon::WEDNESDAY)->toDateString();

    // En lista cada extremo va por su cuenta (es la pantalla la que manda los
    // dos, como hace el botón de navegar por día). Lo que se comprueba aquí es
    // que NO se abre a la semana entera.
    $this->get(route('agenda.index', ['vista' => 'lista', 'fecha_desde' => $dia, 'fecha_hasta' => $dia]))
        ->assertInertia(fn ($page) => $page
            ->where('vista', 'lista')
            ->where('filters.fecha_desde', $dia)
            ->where('filters.fecha_hasta', $dia));
});

it('la semana trae las citas de sus siete días, no solo las del día ancla', function () {
    $lunes = Carbon::today()->next(Carbon::MONDAY);

    citaEn($lunes->copy()->setTime(10, 0)->toDateTimeString());
    citaEn($lunes->copy()->addDays(3)->setTime(16, 0)->toDateTimeString()); // jueves
    // Fuera de la semana: no debe aparecer.
    citaEn($lunes->copy()->addDays(8)->setTime(9, 0)->toDateTimeString());

    $this->get(route('agenda.index', ['vista' => 'semana', 'fecha_desde' => $lunes->toDateString()]))
        ->assertInertia(fn ($page) => $page->has('citas', 2));
});

it('el reporte agrupa por profesional y solo cuenta el dinero de las citas cobradas', function () {
    $turno = $this->env->abrirTurno();
    $lunes = Carbon::today()->next(Carbon::MONDAY);

    // Una cita cobrada: su venta es la que pone el importe.
    $cobrada = citaEn($lunes->copy()->setTime(9, 0)->toDateTimeString(), $this->env->admin->id);
    $this->svc->completarYCobrar($cobrada, [
        'tipo_comprobante' => 'ticket',
        'items' => [[
            'producto_id'        => $this->servicio->id,
            'producto_unidad_id' => $this->servicio->unidadBase->id,
            'cantidad'           => 1,
            'precio_unitario'    => 50,
        ]],
        'pagos' => [['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => 50]],
    ], $this->env->admin, $turno);

    // Una que se plantó: cuenta como no asistió y NO suma dinero.
    $plantada = citaEn($lunes->copy()->setTime(11, 0)->toDateTimeString(), $this->env->admin->id);
    $this->svc->marcarNoAsistio($plantada, $this->env->admin);

    // Y una sin nadie asignado: se agrupa aparte en vez de perderse.
    citaEn($lunes->copy()->setTime(15, 0)->toDateTimeString(), null);

    $resp = $this->get(route('reportes.agenda', [
        'fecha_desde' => $lunes->toDateString(),
        'fecha_hasta' => $lunes->copy()->addDays(6)->toDateString(),
    ]));
    $resp->assertInertia(function ($page) {
        $filas = collect($page->toArray()['props']['filas']);

        $delAdmin = $filas->firstWhere('profesional_id', test()->env->admin->id);
        expect($delAdmin['completadas'])->toBe(1)
            ->and($delAdmin['no_asistio'])->toBe(1)
            ->and((float) $delAdmin['monto'])->toBe(50.0)
            // Sobre las que YA tuvieron desenlace: 1 de 2.
            ->and((float) $delAdmin['asistencia'])->toBe(50.0);

        $sinAsignar = $filas->firstWhere('profesional_id', null);
        expect($sinAsignar)->not->toBeNull()
            ->and($sinAsignar['profesional'])->toBe('Sin profesional asignado')
            ->and((float) $sinAsignar['monto'])->toBe(0.0);
    });
});

it('el reporte no se abre si la empresa no usa agenda', function () {
    $this->env->empresa->update(['usa_agenda' => false]);

    $this->get(route('reportes.agenda'))->assertStatus(403);
});

it('la empresa que no usa agenda no ve el módulo en su menú', function () {
    // `modulos.activo` es global: sin filtrar por empresa, una ferretería vería
    // "Agenda" y "Reportes → Agenda" en su menú y al entrar se comería un 403.
    $slugs = fn () => collect($this->get(route('dashboard'))->viewData('page')['props']['modules'] ?? [])
        ->flatMap(fn ($m) => [$m['slug'], ...collect($m['hijos'] ?? [])->pluck('slug')])
        ->all();

    expect($slugs())->toContain('agenda');

    $this->env->empresa->update(['usa_agenda' => false]);

    expect($slugs())->not->toContain('agenda')
        ->and($slugs())->not->toContain('reportes.agenda');
});
