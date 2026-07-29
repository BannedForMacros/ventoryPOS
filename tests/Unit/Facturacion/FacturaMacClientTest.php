<?php

use App\Services\Facturacion\FacturaMacClient;
use App\Services\Facturacion\FacturaMacException;
use Illuminate\Support\Facades\Http;
use MacSoft\Facturacion\Contrato\Enum\CodigoError;

/**
 * Tests de la lectura del ERROR del emisor (contrato §4.3 y §5). SIN BASE DE DATOS.
 *
 * Aquí no se prueba el camino feliz: se prueba lo que pasa cuando FacturaMac dice que
 * no. Dos decisiones cuelgan de esto y las dos cuestan dinero:
 *
 *   1. QUÉ TEXTO ve la cajera. El cuerpo trae `error` (código, para programar) y
 *      `mensaje` (para la persona). Leer el primero donde iba el segundo llenaba la
 *      pantalla y `venta_comprobantes.error` de `cliente_sin_ruc`.
 *   2. SI LA COLA INSISTE. El cuerpo trae `reintentable` justamente para no tener que
 *      deducirlo del status, y hay un caso —`estado_no_permite_nota_credito`, 409 con
 *      `reintentable: true`— donde deducirlo del 4xx pierde la nota de crédito de toda
 *      boleta devuelta el mismo día.
 */
uses(Tests\TestCase::class);

beforeEach(function () {
    config()->set('facturamac.base_url', 'https://emisor.test');
    config()->set('facturamac.timeout', 5);
});

/** Provoca el error emitiendo una venta y devuelve la excepción resultante. */
function errorAlEmitir(array|string $cuerpo, int $status): FacturaMacException
{
    // `Http::fake()` ACUMULA stubs en lugar de reemplazarlos, y el primero que casa
    // con la URL gana. Sin este cambiazo, dos llamadas en el mismo test recibirían las
    // dos la respuesta de la primera y el test pasaría (o fallaría) por la razón
    // equivocada.
    Http::swap(new Illuminate\Http\Client\Factory());

    Http::fake([
        'emisor.test/*' => Http::response($cuerpo, $status),
    ]);

    try {
        (new FacturaMacClient('token-de-prueba'))->emitirVenta(['idempotency_key' => 'venta-1']);
    } catch (FacturaMacException $e) {
        return $e;
    }

    throw new RuntimeException('Se esperaba una FacturaMacException y no se lanzó ninguna.');
}

// ── El texto y el código son cosas distintas ────────────────────────────────────

it('lee el mensaje para la persona y conserva el código aparte', function () {
    $e = errorAlEmitir([
        'error'        => 'cliente_sin_ruc',
        'mensaje'      => 'Una factura requiere un cliente con RUC.',
        'campo'        => 'cliente.documento.tipo',
        'solucion'     => 'Registra el RUC del cliente o emite una boleta.',
        'reintentable' => false,
    ], 422);

    // Lo que ve la cajera: una frase, no un identificador de programador.
    expect($e->mensajeUsuario)->toBe('Una factura requiere un cliente con RUC.')
        ->and($e->paraUsuario())->toBe('Una factura requiere un cliente con RUC.')
        ->and($e->getMessage())->toContain('Una factura requiere un cliente con RUC.');

    // Y lo que usa el código: el código, no el texto.
    expect($e->codigo)->toBe('cliente_sin_ruc')
        ->and($e->codigoError())->toBe(CodigoError::CLIENTE_SIN_RUC)
        ->and($e->esCodigo(CodigoError::CLIENTE_SIN_RUC))->toBeTrue()
        ->and($e->esCodigo(CodigoError::TOTALES_NO_CUADRAN))->toBeFalse();

    expect($e->campo)->toBe('cliente.documento.tipo')
        ->and($e->solucion)->toBe('Registra el RUC del cliente o emite una boleta.')
        ->and($e->reintentable)->toBeFalse()
        ->and($e->status)->toBe(422);
});

it('nunca deja el código como mensaje de cara al usuario', function () {
    // El defecto exacto que motivó este cambio: `totales_no_cuadran` acababa impreso
    // en la pantalla de caja porque se leía `error` donde el contrato pone `mensaje`.
    $e = errorAlEmitir([
        'error'        => 'totales_no_cuadran',
        'mensaje'      => 'El total declarado (S/ 75.00) no coincide con el calculado (S/ 74.10).',
        'reintentable' => false,
    ], 422);

    expect($e->paraUsuario())->not->toBe('totales_no_cuadran')
        ->and($e->paraUsuario())->toContain('no coincide con el calculado')
        ->and($e->codigo)->toBe('totales_no_cuadran');
});

// ── `reintentable` del cuerpo manda sobre el status ─────────────────────────────

it('respeta reintentable=true del cuerpo aunque el status sea 4xx', function () {
    // El caso por diseño: la boleta sigue en `pendiente_resumen` hasta las 23:55.
    // Hay que ESPERARLA. Clasificar este 409 como definitivo dejaría sin nota de
    // crédito a toda boleta devuelta el mismo día en que se emitió.
    $e = errorAlEmitir([
        'error'        => 'estado_no_permite_nota_credito',
        'mensaje'      => 'El comprobante aún no llegó a SUNAT; inténtalo más tarde.',
        'reintentable' => true,
    ], 409);

    expect($e->reintentable)->toBeTrue()
        ->and($e->status)->toBe(409)
        ->and($e->codigoError())->toBe(CodigoError::ESTADO_NO_PERMITE_NOTA_CREDITO);
});

it('usa el catálogo cuando el cuerpo no dice si es reintentable', function () {
    // Mismo 409, sin el campo. El catálogo compartido sabe que este código espera.
    $e = errorAlEmitir([
        'error'   => 'estado_no_permite_nota_credito',
        'mensaje' => 'El comprobante aún no llegó a SUNAT.',
    ], 409);

    expect($e->reintentable)->toBeTrue();

    // Y al revés: un 409 que sí es culpa del adaptador no se reintenta jamás.
    $duplicada = errorAlEmitir([
        'error'   => 'referencia_externa_duplicada',
        'mensaje' => 'Esa referencia ya se usó en otra venta.',
    ], 409);

    expect($duplicada->reintentable)->toBeFalse();
});

it('respeta reintentable=false del cuerpo aunque el status sea 5xx', function () {
    // SUNAT rechazó: es determinista. Reenviar lo mismo da el mismo rechazo, y cada
    // reintento gasta cola sin que nadie mire el problema de verdad.
    $e = errorAlEmitir([
        'error'        => 'rechazado_por_sunat',
        'mensaje'      => 'SUNAT rechazó el comprobante: RUC del emisor no habido.',
        'reintentable' => false,
    ], 502);

    expect($e->reintentable)->toBeFalse()
        ->and($e->codigoError())->toBe(CodigoError::RECHAZADO_POR_SUNAT);
});

// ── Sin cuerpo útil: manda el status, como siempre ──────────────────────────────

it('reintenta un 503 sin cuerpo', function () {
    $e = errorAlEmitir('', 503);

    expect($e->reintentable)->toBeTrue()
        ->and($e->status)->toBe(503)
        ->and($e->getMessage())->toContain('503')
        ->and($e->codigo)->toBeNull();
});

it('reintenta un 500', function () {
    expect(errorAlEmitir(['error' => 'error_interno', 'mensaje' => 'Fallo interno.'], 500)->reintentable)->toBeTrue();
    expect(errorAlEmitir('', 500)->reintentable)->toBeTrue();
});

it('no reintenta un 422 sin cuerpo del contrato', function () {
    expect(errorAlEmitir('', 422)->reintentable)->toBeFalse();
});

// ── Compatibilidad y cuerpos raros ─────────────────────────────────────────────

it('sigue entendiendo un cuerpo antiguo con message', function () {
    // Respuestas anteriores al contrato, o de otro origen (validación de Laravel).
    $e = errorAlEmitir([
        'message' => 'El campo cliente es obligatorio.',
        'errors'  => ['cliente' => ['El campo cliente es obligatorio.']],
    ], 422);

    expect($e->paraUsuario())->toContain('El campo cliente es obligatorio.')
        ->and($e->getMessage())->toContain('cliente:')
        ->and($e->reintentable)->toBeFalse()
        ->and($e->codigo)->toBeNull();
});

it('no revienta con un cuerpo vacío', function () {
    $e = errorAlEmitir('', 400);

    expect($e)->toBeInstanceOf(FacturaMacException::class)
        ->and($e->getMessage())->not->toBe('')
        ->and($e->reintentable)->toBeFalse();
});

it('resume el HTML de un proxy caído en vez de volcarlo', function () {
    $html = '<html><head><title>502 Bad Gateway</title></head><body><h1>502 Bad Gateway</h1></body></html>';

    $e = errorAlEmitir($html, 502);

    expect($e->reintentable)->toBeTrue()
        ->and($e->getMessage())->not->toContain('<html>')
        ->and($e->getMessage())->toContain('proxy');
});

it('degrada un código desconocido sin lanzar otra excepción', function () {
    // El catálogo del emisor es aditivo: puede ir por delante de este paquete. Un
    // código nuevo no puede tumbar al POS ni perderse por el camino.
    $e = errorAlEmitir([
        'error'        => 'certificado_por_vencer',
        'mensaje'      => 'El certificado digital vence en 3 días.',
        'reintentable' => false,
    ], 422);

    expect($e->codigo)->toBe('certificado_por_vencer')
        ->and($e->codigoError())->toBeNull()
        ->and($e->esCodigo('certificado_por_vencer'))->toBeTrue()
        ->and($e->paraUsuario())->toBe('El certificado digital vence en 3 días.')
        ->and($e->reintentable)->toBeFalse();
});

it('cae al status cuando el código es desconocido y no dice si reintentar', function () {
    // Sin catálogo y sin `reintentable`, lo único fiable que queda es el status. No
    // se asume reintentable: el DTO degrada a `error_interno` y creérselo dejaría la
    // cola girando sobre un 422 que nunca va a cambiar.
    expect(errorAlEmitir(['error' => 'algo_nuevo', 'mensaje' => 'Vaya.'], 422)->reintentable)->toBeFalse();
    expect(errorAlEmitir(['error' => 'algo_nuevo', 'mensaje' => 'Vaya.'], 503)->reintentable)->toBeTrue();
});

it('no se cae si campo o solucion llegan mal formados', function () {
    $e = errorAlEmitir([
        'error'    => 'datos_invalidos',
        'mensaje'  => 'Revisa los datos.',
        'campo'    => ['cliente', 'items'],   // debía ser una cadena
        'solucion' => null,
    ], 422);

    expect($e->campo)->toBeNull()
        ->and($e->solucion)->toBeNull()
        ->and($e->paraUsuario())->toBe('Revisa los datos.');
});

it('deja el error del contrato disponible para el log', function () {
    $e = errorAlEmitir([
        'error'    => 'cliente_no_identificado',
        'mensaje'  => 'Una boleta de S/ 700 o más exige identificar al cliente.',
        'campo'    => 'cliente.documento.numero',
        'solucion' => 'Pide el DNI del cliente.',
    ], 422);

    expect($e->context())->toMatchArray([
        'reintentable' => false,
        'status'       => 422,
        'codigo'       => 'cliente_no_identificado',
        'campo'        => 'cliente.documento.numero',
        'solucion'     => 'Pide el DNI del cliente.',
    ]);
});

// ── El resto de operaciones usa la misma clasificación ─────────────────────────

it('clasifica igual la nota de crédito', function () {
    Http::fake([
        'emisor.test/*' => Http::response([
            'error'        => 'estado_no_permite_nota_credito',
            'mensaje'      => 'La boleta aún está pendiente del Resumen Diario.',
            'reintentable' => true,
        ], 409),
    ]);

    try {
        (new FacturaMacClient('token-de-prueba'))->notaCredito(812, ['idempotency_key' => 'dev-1']);
        $this->fail('Se esperaba una FacturaMacException.');
    } catch (FacturaMacException $e) {
        expect($e->reintentable)->toBeTrue()
            ->and($e->codigoError())->toBe(CodigoError::ESTADO_NO_PERMITE_NOTA_CREDITO);
    }
});

it('falla sin reintento cuando no hay token configurado', function () {
    // Cliente SIN token: es lo que devuelve `FacturacionEmpresa::cliente()` para una
    // empresa que no está conectada.
    Http::fake();

    expect(fn () => (new FacturaMacClient(null))->ping())
        ->toThrow(FacturaMacException::class);
});
