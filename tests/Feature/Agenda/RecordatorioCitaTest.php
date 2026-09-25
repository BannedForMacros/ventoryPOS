<?php

use App\Models\Cita;
use App\Models\Cliente;
use App\Services\CitaService;
use App\Services\WhatsappService;
use Carbon\Carbon;
use Tests\Support\TestEnv;

/**
 * Recordatorio de cita por WhatsApp, en un clic.
 *
 * El plantón es la mayor pérdida de estos negocios —la silla vacía ya no se
 * recupera— y avisar el día antes es lo que lo baja. El mensaje lo manda la
 * persona desde su propio WhatsApp: enviar de verdad sin intervención exige la
 * API de WhatsApp Business, que cuesta y hay que homologar. Lo que el sistema
 * aporta es el texto ya escrito, el enlace listo y el rastro de a quién se le
 * avisó.
 */

beforeEach(function () {
    $this->env = TestEnv::crear();
    $this->actingAs($this->env->admin);
    $this->svc = app(CitaService::class);
    $this->servicio = $this->env->crearProducto(['precio_venta' => 50, 'nombre' => 'Corte de cabello']);
});

function clienteCon(?string $telefono): Cliente
{
    return Cliente::create([
        'empresa_id'       => test()->env->empresa->id,
        'tipo_documento'   => 'DNI',
        'numero_documento' => (string) random_int(10000000, 99999999),
        'nombres'          => 'Andrea Lucía',
        'apellidos'        => 'Torres Mendoza',
        'telefono'         => $telefono,
        'activo'           => true,
    ]);
}

function citaPara(Cliente $cliente, ?Carbon $cuando = null): Cita
{
    return test()->svc->crear([
        'local_id'   => test()->env->local->id,
        'cliente_id' => $cliente->id,
        'fecha_hora' => ($cuando ?? Carbon::tomorrow()->setTime(15, 30))->toDateTimeString(),
        'items' => [[
            'producto_id'        => test()->servicio->id,
            'producto_unidad_id' => test()->servicio->unidadBase->id,
            'cantidad'           => 1,
            'duracion_min'       => 45,
        ]],
    ], test()->env->admin);
}

it('completa el prefijo de país: sin el 51, WhatsApp abre un contacto que no existe', function () {
    // Los teléfonos se guardan como los dicta el cliente. Un móvil peruano de 9
    // cifras sin país abre un chat vacío y la persona cree que avisó.
    expect(WhatsappService::telefonoWhatsapp('987 654 321'))->toBe('51987654321')
        ->and(WhatsappService::telefonoWhatsapp('+51 987654321'))->toBe('51987654321')
        // Lo que ya trae país o no es un móvil peruano se respeta tal cual:
        // adivinar de más es cómo se manda un mensaje al país equivocado.
        ->and(WhatsappService::telefonoWhatsapp('074123456'))->toBe('074123456')
        ->and(WhatsappService::telefonoWhatsapp(''))->toBeNull()
        ->and(WhatsappService::telefonoWhatsapp(null))->toBeNull();
});

it('arma el mensaje con los datos de la cita', function () {
    $cita = citaPara(clienteCon('987654321'));

    $r = $this->svc->recordatorio($cita);

    expect($r['url'])->toStartWith('https://wa.me/51987654321?text=')
        // Solo el nombre de pila: un recordatorio se escribe como se habla.
        ->and($r['mensaje'])->toContain('Andrea')
        ->and($r['mensaje'])->not->toContain('Torres Mendoza')
        ->and($r['mensaje'])->toContain('15:30')
        ->and($r['mensaje'])->toContain('Corte de cabello');
});

it('respeta la plantilla de la empresa y no deja variables crudas', function () {
    $this->env->empresa->update([
        'agenda_recordatorio_plantilla' => 'Hola {cliente}, te esperamos el {fecha} a las {hora}. {variable_inventada}',
    ]);
    $cita = citaPara(clienteCon('987654321'));

    $mensaje = $this->svc->recordatorio($cita)['mensaje'];

    expect($mensaje)->toStartWith('Hola Andrea, te esperamos el')
        ->and($mensaje)->toContain('15:30')
        // Una plantilla a medias no puede dejar "{algo}" en el mensaje que lee
        // el cliente: lo que no se reemplaza se borra.
        ->and($mensaje)->not->toContain('{');
});

it('sin teléfono no hay enlace, y se dice en vez de abrir WhatsApp a ninguna parte', function () {
    $cita = citaPara(clienteCon(null));

    $r = $this->svc->recordatorio($cita);

    expect($r['url'])->toBeNull()
        // El mensaje sí se arma: solo falta a quién mandárselo.
        ->and($r['mensaje'])->toContain('Andrea');
});

it('marcar el recordatorio deja el rastro que distinguía "ya avisé" de "se me pasó"', function () {
    $cita = citaPara(clienteCon('987654321'));
    expect($cita->recordatorio_enviado_at)->toBeNull();

    $this->post(route('agenda.recordatorio', $cita))->assertSessionHasNoErrors();

    expect($cita->fresh()->recordatorio_enviado_at)->not->toBeNull();

    // Y queda en auditoría, como el resto de acciones de la cita.
    expect(\App\Models\Auditoria::where('accion', 'cita.recordatorio_enviado')
        ->where('modelo_id', $cita->id)->exists())->toBeTrue();
});

it('la bandeja trae las citas de mañana y dice a quién falta avisarle', function () {
    $conAviso = citaPara(clienteCon('987654321'));
    $this->post(route('agenda.recordatorio', $conAviso));

    citaPara(clienteCon('987000111'));   // pendiente
    citaPara(clienteCon(null));          // sin teléfono
    // Pasado mañana: no entra en la bandeja de mañana.
    citaPara(clienteCon('987222333'), Carbon::tomorrow()->addDay()->setTime(10, 0));

    $this->get(route('agenda.recordatorios'))->assertInertia(function ($page) {
        $citas = collect($page->toArray()['props']['citas']);

        expect($citas)->toHaveCount(3)
            ->and($citas->whereNotNull('recordado_at')->count())->toBe(1)
            ->and($citas->whereNull('url')->count())->toBe(1);
    });
});

it('la bandeja ignora las citas canceladas o ya atendidas', function () {
    $cancelada = citaPara(clienteCon('987654321'));
    $this->svc->cancelar($cancelada, 'El cliente reprogramó', $this->env->admin);

    citaPara(clienteCon('987000111'));

    $this->get(route('agenda.recordatorios'))
        ->assertInertia(fn ($page) => $page->has('citas', 1));
});
