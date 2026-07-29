<?php

use App\Mail\ComprobanteElectronicoMail;
use App\Models\Cliente;
use App\Models\Venta;
use App\Models\VentaComprobante;
use App\Services\Facturacion\CompartirComprobante;
use App\Services\VentaService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\Support\TestEnv;

/**
 * Compartir el comprobante electrónico con el cliente: WhatsApp (enlace wa.me)
 * y correo (PDF adjunto), ambos apoyados en una URL FIRMADA temporal.
 *
 * Lo que de verdad se está protegiendo aquí es que el PDF sea público SIN ser
 * enumerable: si la firma dejara de validarse, cualquiera podría recorrer
 * /comprobante/1..N y leerse los comprobantes de toda la instalación. Por eso
 * hay tres tests sobre la firma (válida, manipulada, caducada) y no uno.
 */

const PDF_FALSO = '%PDF-1.4 comprobante de prueba';

beforeEach(function () {
    $this->env   = TestEnv::crear();
    $this->turno = $this->env->abrirTurno();

    config(['facturamac.base_url' => 'http://facturamac.test']);

    // El cliente HTTP de FacturaMac se niega a hablar sin token, y el token ya no
    // sale del `.env`: sale de la conexión de ESTA empresa.
    $this->env->conectarFacturacion();

    // Ninguna llamada real a FacturaMac: el PDF llega falseado.
    Http::fake([
        'facturamac.test/api/v1/ventas/*/pdf' => Http::response(PDF_FALSO, 200, [
            'Content-Type' => 'application/pdf',
        ]),
    ]);
});

/**
 * Venta con su comprobante ya emitido. El comprobante se inserta a mano en vez
 * de pasar por el Job: aquí no se está probando la emisión, sino cómo se comparte
 * algo que YA está emitido.
 */
function ventaConComprobante(TestEnv $env, $turno, array $ceOpts = [], ?Cliente $cliente = null): Venta
{
    $producto = $env->crearProducto(['precio_venta' => 100, 'incluye_igv' => false]);

    $venta = app(VentaService::class)->crear([
        // `ticket` para que la creación no arrastre la emisión electrónica:
        // el comprobante lo montamos nosotros justo debajo.
        'tipo_comprobante' => 'ticket',
        'cliente_id'       => ($cliente ?? $env->clienteGeneral)->id,
        'items' => [[
            'producto_id'        => $producto->id,
            'producto_unidad_id' => $producto->unidadBase->id,
            'cantidad'           => 1,
            'precio_unitario'    => 100,
        ]],
        'pagos' => [[
            'metodo_pago_id' => $env->metodo('efectivo')->id,
            'monto'          => 100,
        ]],
    ], $env->admin, $turno);

    VentaComprobante::create(array_merge([
        'venta_id'        => $venta->id,
        'tipo'            => '03', // Catálogo 01 SUNAT: boleta
        'serie'           => 'B002',
        'correlativo'     => 123,
        'numero'          => 'B002-00000123',
        'estado'          => 'aceptado',
        'facturamac_id'   => 555,
        'idempotency_key' => 'venta-' . $venta->id,
        'intentos'        => 1,
    ], $ceOpts));

    return $venta->fresh();
}

function comprobanteDe(Venta $venta): VentaComprobante
{
    return $venta->comprobanteElectronico()->firstOrFail();
}

// ── URL FIRMADA ──────────────────────────────────────────────────────────────

it('la URL firmada abre el PDF sin sesión iniciada', function () {
    $venta = ventaConComprobante($this->env, $this->turno);
    $url   = app(CompartirComprobante::class)->enlaceFirmado(comprobanteDe($venta));

    // A propósito SIN actingAs: el cliente final no es usuario del POS. Ese es
    // justamente el motivo de existir de toda esta funcionalidad.
    $respuesta = $this->get($url);

    $respuesta->assertOk();
    expect($respuesta->headers->get('Content-Type'))->toContain('application/pdf');
    expect($respuesta->headers->get('Content-Disposition'))->toContain('B002-00000123.pdf');
    expect($respuesta->getContent())->toBe(PDF_FALSO);
});

it('una firma manipulada devuelve 403', function () {
    $venta = ventaConComprobante($this->env, $this->turno);
    $ce    = comprobanteDe($venta);
    $url   = app(CompartirComprobante::class)->enlaceFirmado($ce);

    // El ataque realista: quedarme con una firma válida y cambiar el id para
    // leer el comprobante del cliente de al lado.
    $otroId    = $ce->id + 1;
    $urlFalsa  = str_replace("/comprobante/{$ce->id}/pdf", "/comprobante/{$otroId}/pdf", $url);

    // 403, NO 404. Si respondiera 404 para los ids que no existen y 403 para los
    // que sí, la propia diferencia sería un oráculo de enumeración: el atacante
    // sabría qué comprobantes hay sin llegar a leer ninguno.
    $this->get($urlFalsa)->assertStatus(403);

    // Y lo mismo tocando solo la firma sobre el id correcto.
    $this->get($url . 'x')->assertStatus(403);
});

it('una URL caducada devuelve 403', function () {
    $venta = ventaConComprobante($this->env, $this->turno);
    $url   = app(CompartirComprobante::class)->enlaceFirmado(comprobanteDe($venta));

    // El enlace sigue siendo el mismo y la firma sigue siendo auténtica: lo
    // único que cambia es el calendario. Viajamos un día más allá de la
    // vigencia para no depender de si el corte es inclusivo o exclusivo.
    Carbon::setTestNow(now()->addDays(CompartirComprobante::DIAS_VIGENCIA + 1));

    $this->get($url)->assertStatus(403);

    Carbon::setTestNow();
});

it('el enlace firmado sigue siendo válido justo antes de caducar', function () {
    $venta = ventaConComprobante($this->env, $this->turno);
    $url   = app(CompartirComprobante::class)->enlaceFirmado(comprobanteDe($venta));

    Carbon::setTestNow(now()->addDays(CompartirComprobante::DIAS_VIGENCIA)->subHour());

    $this->get($url)->assertOk();

    Carbon::setTestNow();
});

// ── WHATSAPP ─────────────────────────────────────────────────────────────────

it('el enlace de WhatsApp lleva el prefijo 51 cuando el teléfono tiene 9 dígitos', function () {
    $cliente = Cliente::create([
        'empresa_id'       => $this->env->empresa->id,
        'tipo_documento'   => 'DNI',
        'numero_documento' => '10101010',
        'nombres'          => 'Rosa',
        'apellidos'        => 'Quispe',
        'telefono'         => '999 888 777', // como la teclea la cajera
        'activo'           => true,
    ]);

    $venta = ventaConComprobante($this->env, $this->turno, [], $cliente);
    $url   = app(CompartirComprobante::class)->urlWhatsapp(comprobanteDe($venta));

    // Sin el 51, wa.me abre un chat con un contacto que no existe: el enlace
    // "funciona" pero el mensaje no llega a nadie.
    expect($url)->toStartWith('https://wa.me/51999888777?text=');
});

it('un teléfono que ya trae prefijo no se duplica', function () {
    $cliente = Cliente::create([
        'empresa_id'       => $this->env->empresa->id,
        'tipo_documento'   => 'DNI',
        'numero_documento' => '20202020',
        'nombres'          => 'Luis',
        'apellidos'        => 'Ramos',
        'telefono'         => '+51 977-666-555',
        'activo'           => true,
    ]);

    $venta = ventaConComprobante($this->env, $this->turno, [], $cliente);
    $url   = app(CompartirComprobante::class)->urlWhatsapp(comprobanteDe($venta));

    expect($url)->toStartWith('https://wa.me/51977666555?text=');
    expect($url)->not->toContain('wa.me/5151');
});

it('sin teléfono el enlace sale sin número pero con el mensaje', function () {
    $cliente = Cliente::create([
        'empresa_id'       => $this->env->empresa->id,
        'tipo_documento'   => 'DNI',
        'numero_documento' => '30303030',
        'nombres'          => 'Cliente',
        'apellidos'        => 'Mostrador',
        'telefono'         => null,
        'activo'           => true,
    ]);

    $venta = ventaConComprobante($this->env, $this->turno, [], $cliente);
    $url   = app(CompartirComprobante::class)->urlWhatsapp(comprobanteDe($venta));

    // wa.me sin número abre WhatsApp con el mensaje escrito y deja elegir a
    // quién mandárselo: es lo correcto para un cliente de mostrador.
    expect($url)->toStartWith('https://wa.me/?text=');

    $mensaje = rawurldecode(str_replace('https://wa.me/?text=', '', $url));
    expect($mensaje)->toContain('BOLETA B002-00000123');
    expect($mensaje)->toContain('S/ 100.00');
    expect($mensaje)->toContain('/comprobante/');
    expect($mensaje)->toContain('signature=');
});

it('el mensaje nombra a la empresa y lleva el enlace firmado', function () {
    $venta = ventaConComprobante($this->env, $this->turno);
    $mensaje = app(CompartirComprobante::class)->mensaje(comprobanteDe($venta));

    expect($mensaje)->toContain($this->env->empresa->nombre_comercial);
    expect($mensaje)->toContain('BOLETA B002-00000123');
    expect($mensaje)->toContain('expires=');
});

// ── CORREO ───────────────────────────────────────────────────────────────────

it('envía el correo con el PDF adjunto', function () {
    Mail::fake();

    $cliente = Cliente::create([
        'empresa_id'       => $this->env->empresa->id,
        'tipo_documento'   => 'DNI',
        'numero_documento' => '40404040',
        'nombres'          => 'Ana',
        'apellidos'        => 'Torres',
        'email'            => 'ana@correo.pe',
        'activo'           => true,
    ]);

    $venta = ventaConComprobante($this->env, $this->turno, [], $cliente);

    $this->actingAs($this->env->admin)
        ->postJson(route('ventas.comprobante.enviar-correo', $venta))
        ->assertOk()
        ->assertJson(['email' => 'ana@correo.pe', 'con_adjunto' => true]);

    Mail::assertSent(ComprobanteElectronicoMail::class, function ($mail) {
        expect($mail->hasTo('ana@correo.pe'))->toBeTrue();
        // El binario viaja en el Mailable: se descargó en el controlador, no
        // dentro del envío.
        expect($mail->pdf)->toBe(PDF_FALSO);

        $mail->assertHasAttachedData(PDF_FALSO, 'B002-00000123.pdf', ['mime' => 'application/pdf']);

        return true;
    });
});

it('el correo del formulario manda sobre el registrado del cliente', function () {
    Mail::fake();

    $cliente = Cliente::create([
        'empresa_id'       => $this->env->empresa->id,
        'tipo_documento'   => 'DNI',
        'numero_documento' => '50505050',
        'nombres'          => 'Pedro',
        'apellidos'        => 'Silva',
        'email'            => 'viejo@correo.pe',
        'activo'           => true,
    ]);

    $venta = ventaConComprobante($this->env, $this->turno, [], $cliente);

    $this->actingAs($this->env->admin)
        ->postJson(route('ventas.comprobante.enviar-correo', $venta), ['email' => 'nuevo@correo.pe'])
        ->assertOk();

    Mail::assertSent(ComprobanteElectronicoMail::class, fn ($mail) => $mail->hasTo('nuevo@correo.pe'));
});

it('sin correo del cliente ni destinatario responde 422 con mensaje para la cajera', function () {
    Mail::fake();

    // El cliente general de TestEnv no tiene email.
    $venta = ventaConComprobante($this->env, $this->turno);

    $respuesta = $this->actingAs($this->env->admin)
        ->postJson(route('ventas.comprobante.enviar-correo', $venta));

    $respuesta->assertStatus(422);
    expect($respuesta->json('message'))->toContain('no tiene correo registrado');

    Mail::assertNothingSent();
});

it('no envía correo de un comprobante que aún no está emitido', function () {
    Mail::fake();

    $venta = ventaConComprobante($this->env, $this->turno, [
        'estado'        => 'pendiente',
        'facturamac_id' => null,
    ]);

    $this->actingAs($this->env->admin)
        ->postJson(route('ventas.comprobante.enviar-correo', $venta), ['email' => 'quien@sea.pe'])
        ->assertStatus(422);

    Mail::assertNothingSent();
});

// ── BLOQUE `compartir` EN estado() ───────────────────────────────────────────

it('estado() expone el bloque compartir cuando el comprobante está emitido', function () {
    $cliente = Cliente::create([
        'empresa_id'       => $this->env->empresa->id,
        'tipo_documento'   => 'DNI',
        'numero_documento' => '60606060',
        'nombres'          => 'Mario',
        'apellidos'        => 'Vega',
        'telefono'         => '987654321',
        'email'            => 'mario@correo.pe',
        'activo'           => true,
    ]);

    $venta = ventaConComprobante($this->env, $this->turno, [], $cliente);

    $respuesta = $this->actingAs($this->env->admin)
        ->getJson(route('ventas.comprobante.estado', $venta))
        ->assertOk()
        ->assertJsonStructure(['compartir' => ['whatsapp', 'pdf_publico', 'email_cliente']]);

    expect($respuesta->json('compartir.whatsapp'))->toStartWith('https://wa.me/51987654321?text=');
    expect($respuesta->json('compartir.pdf_publico'))->toContain('signature=');
    expect($respuesta->json('compartir.email_cliente'))->toBe('mario@correo.pe');
});

it('un comprobante NO emitido no expone el bloque compartir', function () {
    $venta = ventaConComprobante($this->env, $this->turno, [
        'estado'        => 'error_envio',
        'facturamac_id' => null,
    ]);

    // Sin comprobante en SUNAT no hay PDF que compartir: el enlace sería un 404
    // en el móvil del cliente y una llamada a la tienda.
    $this->actingAs($this->env->admin)
        ->getJson(route('ventas.comprobante.estado', $venta))
        ->assertOk()
        ->assertJsonMissingPath('compartir');
});

it('un comprobante en vuelo (emitido pero sin id de FacturaMac) tampoco expone compartir', function () {
    // `enviando` cuenta como emitido para las guardas de anulación, pero el PDF
    // todavía no existe: compartirlo mandaría al cliente a un 404.
    $venta = ventaConComprobante($this->env, $this->turno, [
        'estado'        => 'enviando',
        'facturamac_id' => null,
    ]);

    expect(comprobanteDe($venta)->esEmitido())->toBeTrue();

    $this->actingAs($this->env->admin)
        ->getJson(route('ventas.comprobante.estado', $venta))
        ->assertOk()
        ->assertJsonMissingPath('compartir');
});
