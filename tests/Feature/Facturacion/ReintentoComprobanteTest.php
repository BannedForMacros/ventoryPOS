<?php

use App\Jobs\EmitirComprobanteElectronico;
use App\Models\Venta;
use App\Models\VentaComprobante;
use App\Services\Facturacion\FacturaMacClient;
use App\Services\Facturacion\VentaAContrato;
use App\Services\VentaService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use MacSoft\Facturacion\Contrato\Enum\EstadoComprobante;
use Tests\Support\TestEnv;

/**
 * Reintento de un comprobante que el emisor YA creó.
 *
 * POR QUÉ ESTE ARCHIVO EXISTE: la condición que elige entre "reenviar el mismo
 * correlativo" y "emitir de nuevo" se evaluaba DESPUÉS de marcar el comprobante como
 * `enviando`, así que era siempre falsa. Consecuencias, ninguna visible:
 *
 *   · El reintento re-POSTeaba a /ventas con la misma clave de idempotencia. El
 *     emisor la absorbía y devolvía el comprobante rechazado tal cual. A SUNAT no
 *     llegaba nada, pero en pantalla parecía que sí se había reintentado.
 *   · Y de paso se borraba el motivo del rechazo, dejando a la cajera con un
 *     comprobante rechazado y sin explicación: peor que antes de reintentar.
 *
 * Los tests miran a QUÉ URL se llamó, que es lo único que distingue un reenvío real
 * de uno imaginario: con el bug, el primer test veía un POST a /api/v1/ventas.
 *
 * NOTA PARA QUIEN EDITE ESTE ARCHIVO: aquí NO va `uses(Tests\TestCase::class, ...)`.
 * `tests/Pest.php` ya asocia ese TestCase a toda la carpeta `Feature` con
 * `pest()->extend(...)->in('Feature')`, y repetirlo en el archivo hace que Pest ni
 * siquiera llegue a construir la suite ("Test case `Tests\TestCase` can not be used").
 */

beforeEach(function () {
    // El job encola ConsultarEstadoComprobante cuando el estado no es terminal, y en
    // tests la cola es `sync`: sin este fake ese job correría inline y su GET al
    // emisor se colaría en las aserciones de Http de más abajo.
    Queue::fake();

    $this->env   = TestEnv::crear();
    $this->turno = $this->env->abrirTurno();

    // El cliente HTTP de FacturaMac se niega a hablar sin token configurado.
    config([
        'facturamac.enabled'  => true,
        'facturamac.base_url' => 'http://emisor.test',
        'facturamac.token'    => 'token-de-prueba',
    ]);
});

/**
 * Venta REAL (pasa por VentaService) con su comprobante en el estado que se quiera
 * probar. Se emite como `boleta` porque un `ticket` es nota de venta interna y el job
 * se sale antes de mirar nada: no serviría para probar el reintento.
 *
 * El comprobante se inserta a mano en vez de emitirse: lo que se prueba es qué hace el
 * job ante un comprobante que YA existe en el emisor, no cómo llegó a existir.
 *
 * @return array{0: Venta, 1: VentaComprobante}
 */
function ventaParaReintento(TestEnv $env, $turno, array|false $ceAttrs = []): array
{
    $producto = $env->crearProducto(['precio_venta' => 75, 'incluye_igv' => true]);

    $venta = app(VentaService::class)->crear([
        'tipo_comprobante' => 'boleta',
        'cliente_id'       => $env->clienteGeneral->id,
        'items' => [[
            'producto_id'        => $producto->id,
            'producto_unidad_id' => $producto->unidadBase->id,
            'cantidad'           => 1,
            'precio_unitario'    => 75,
        ]],
        'pagos' => [[
            'metodo_pago_id' => $env->metodo('efectivo')->id,
            'monto'          => 75,
        ]],
    ], $env->admin, $turno);

    // `false` = venta sin comprobante previo (casos donde debe emitirse de cero).
    if ($ceAttrs === false) {
        return [$venta->fresh(), null];
    }

    $ce = VentaComprobante::create(array_merge([
        'tipo'            => '03', // Catálogo 01 SUNAT: boleta
        'venta_id'        => $venta->id,
        'estado'          => EstadoComprobante::RECHAZADO->value,
        'facturamac_id'   => 4321,
        'serie'           => 'B002',
        'correlativo'     => 9,
        'numero'          => 'B002-00000009',
        'error'           => 'RUC del receptor no existe',
        'intentos'        => 1,
        'idempotency_key' => 'venta-' . $venta->id,
    ], $ceAttrs));

    return [$venta->fresh(), $ce];
}

/** Respuesta del emisor, en la forma que espera `Respuesta::desdeArray()`. */
function respuestaEmisorReintento(string $estado = 'aceptado', int $id = 4321): array
{
    return [
        'emitido'     => true,
        'modo'        => 'beta',
        'id'          => $id,
        'estado'      => $estado,
        'comprobante' => [
            'tipo'            => 'boleta',
            'serie'           => 'B002',
            'numero'          => 9,
            'numero_completo' => 'B002-00000009',
            'hash'            => 'abc',
            'qr'              => 'qr',
        ],
        'totales' => ['total' => 75.00],
    ];
}

/** Ejecuta el job igual que lo haría el worker, sin pasar por la cola. */
function correrEmision(Venta $venta): void
{
    (new EmitirComprobanteElectronico($venta))->handle(
        app(VentaAContrato::class),
        app(FacturaMacClient::class),
    );
}

// ── A qué endpoint se llama ──────────────────────────────────────────────────────

it('un comprobante rechazado se REENVÍA por /reintentar, no se vuelve a emitir', function () {
    [$venta, $ce] = ventaParaReintento($this->env, $this->turno);

    Http::fake([
        'emisor.test/api/v1/ventas/4321/reintentar' => Http::response(respuestaEmisorReintento(), 200),
        'emisor.test/api/v1/ventas'                 => Http::response(respuestaEmisorReintento(), 201),
    ]);

    correrEmision($venta);

    // ESTA es la aserción que cazaba el bug: con la condición evaluada después de
    // marcarEnviando() era siempre falsa y aquí se veía un POST a /api/v1/ventas,
    // que el emisor absorbía por idempotencia sin mandar nada a SUNAT.
    Http::assertSent(fn ($r) => str_contains($r->url(), '/api/v1/ventas/4321/reintentar'));
    Http::assertNotSent(fn ($r) => str_ends_with($r->url(), '/api/v1/ventas'));

    expect($ce->fresh()->estado)->toBe('aceptado');
});

it('un comprobante con error de envío también se reenvía sobre el mismo correlativo', function () {
    // `error_envio` es el otro estado en el que el comprobante ya existe en el emisor
    // (falló el transporte hacia SUNAT, no el contenido). Si solo se contemplara
    // `rechazado`, todo fallo de red gastaría un correlativo nuevo y dejaría un hueco
    // en la numeración que hay que justificar ante SUNAT (G11).
    [$venta] = ventaParaReintento($this->env, $this->turno, [
        'estado' => EstadoComprobante::ERROR_ENVIO->value,
    ]);

    Http::fake([
        'emisor.test/api/v1/ventas/4321/reintentar' => Http::response(respuestaEmisorReintento(), 200),
        'emisor.test/api/v1/ventas'                 => Http::response(respuestaEmisorReintento(), 201),
    ]);

    correrEmision($venta);

    Http::assertSent(fn ($r) => str_contains($r->url(), '/api/v1/ventas/4321/reintentar'));
    Http::assertNotSent(fn ($r) => str_ends_with($r->url(), '/api/v1/ventas'));
});

it('una venta sin comprobante previo SÍ emite por /ventas', function () {
    // El contrapunto obligatorio: si el arreglo se pasara de listo y mandara todo por
    // /reintentar, una venta nueva jamás se emitiría. Sin este test, "usar siempre
    // /reintentar" pasaría los dos anteriores.
    [$venta] = ventaParaReintento($this->env, $this->turno, false);

    Http::fake(['*' => Http::response(respuestaEmisorReintento('enviando'), 201)]);

    correrEmision($venta);

    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/api/v1/ventas'));
    Http::assertNotSent(fn ($r) => str_contains($r->url(), '/reintentar'));
});

it('un rechazo previo SIN id del emisor no puede reenviarse: emite de nuevo', function () {
    // Sin `facturamac_id` no hay comprobante que reenviar: el fallo ocurrió antes de
    // que el emisor llegara a crearlo (timeout de red, 5xx, token malo). Mandar un
    // /reintentar aquí daría un 404 y la venta se quedaría sin comprobante para siempre.
    [$venta] = ventaParaReintento($this->env, $this->turno, ['facturamac_id' => null]);

    Http::fake(['*' => Http::response(respuestaEmisorReintento('enviando'), 201)]);

    correrEmision($venta);

    Http::assertSent(fn ($r) => str_ends_with($r->url(), '/api/v1/ventas'));
    Http::assertNotSent(fn ($r) => str_contains($r->url(), '/reintentar'));
});

// ── El motivo del rechazo ────────────────────────────────────────────────────────

it('si el reintento vuelve a fallar, el motivo del rechazo NO se borra', function () {
    [$venta, $ce] = ventaParaReintento($this->env, $this->turno);

    // El emisor responde que sigue rechazado.
    Http::fake(['emisor.test/*' => Http::response(respuestaEmisorReintento('rechazado'), 200)]);

    correrEmision($venta);

    $ce = $ce->fresh();

    expect($ce->estado)->toBe('rechazado')
        // Antes se limpiaba dos veces —al marcar `enviando` y al persistir la
        // respuesta—, así que la cajera acababa con un comprobante rechazado y sin
        // ninguna pista de por qué: peor que antes de darle a "reintentar".
        ->and($ce->error)->toBe('RUC del receptor no existe');
});

it('si el reintento sale bien, el motivo del rechazo sí se limpia', function () {
    // La otra mitad de la regla: conservar el error no puede convertirse en dejar un
    // mensaje de fallo colgado sobre un comprobante que SUNAT ya aceptó.
    [$venta, $ce] = ventaParaReintento($this->env, $this->turno);

    Http::fake(['emisor.test/*' => Http::response(respuestaEmisorReintento('aceptado'), 200)]);

    correrEmision($venta);

    $ce = $ce->fresh();

    expect($ce->estado)->toBe('aceptado')
        ->and($ce->error)->toBeNull();
});

// ── Un solo comprobante fiscal ───────────────────────────────────────────────────

it('el reintento conserva la clave de idempotencia y el correlativo ya consumido', function () {
    // G6: la clave se fija UNA vez. Si un reintento la regenerase, el emisor dejaría de
    // reconocer la petición como repetida y podría acabar habiendo dos comprobantes
    // fiscales reales de la misma venta. Igual con `facturamac_id`: reenviar tiene que
    // caer sobre el MISMO documento, no crear otro.
    [$venta, $ce] = ventaParaReintento($this->env, $this->turno, [
        'idempotency_key' => 'clave-original',
    ]);

    Http::fake(['emisor.test/*' => Http::response(respuestaEmisorReintento(), 200)]);

    correrEmision($venta);

    $ce = $ce->fresh();

    expect($ce->idempotency_key)->toBe('clave-original')
        ->and($ce->facturamac_id)->toBe(4321)
        ->and($ce->numero)->toBe('B002-00000009')
        // Y solo una petición: ni reenvío + emisión, ni dos reenvíos.
        ->and(Http::recorded()->count())->toBe(1);

    Http::assertNotSent(fn ($r) => str_ends_with($r->url(), '/api/v1/ventas'));
});

it('un comprobante ya informado a SUNAT no se reintenta jamás', function () {
    [$venta] = ventaParaReintento($this->env, $this->turno, [
        'estado' => EstadoComprobante::ACEPTADO->value,
        'error'  => null,
    ]);

    Http::fake(['*' => Http::response(respuestaEmisorReintento(), 200)]);

    correrEmision($venta);

    // Ni reenvío ni emisión: `esEmitido()` corta antes de tocar la red. Es la guarda
    // contra un doble clic en "reintentar" sobre algo que SUNAT ya aceptó.
    Http::assertNothingSent();
});
