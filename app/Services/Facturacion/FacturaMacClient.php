<?php

namespace App\Services\Facturacion;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use MacSoft\Facturacion\Contrato\Dto\Respuesta;
use Throwable;

/**
 * Cliente HTTP de la API de emisión de FacturaMac.
 *
 * Deliberadamente TONTO: no reintenta, no encola, no persiste. Solo habla y traduce
 * el resultado a algo que el Job pueda decidir. El reintento vive en el Job, que es
 * quien conoce el backoff, los intentos consumidos y el estado del comprobante; si el
 * cliente reintentara por su cuenta, los dos se pisarían y multiplicarían los envíos.
 *
 * El tenant emisor (RUC, certificado, clave SOL) NO viaja en el payload: se deduce del
 * Bearer token vía el TenantScope de FacturaMac. Un token equivocado emite con el RUC
 * equivocado, así que el token es dato sensible de configuración, no de request.
 */
class FacturaMacClient
{
    private string $baseUrl;
    private ?string $token;
    private int $timeout;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('facturamac.base_url'), '/');
        $this->token   = config('facturamac.token');
        $this->timeout = (int) config('facturamac.timeout', 20);
    }

    /**
     * Manda una VENTA a emitir. `POST /api/v1/ventas`.
     *
     * El recurso se llama `ventas` y no `comprobantes` porque es lo que el POS envía:
     * una venta. Que salga factura, boleta o nada es decisión del emisor.
     *
     * El header `Idempotency-Key` es la defensa contra el peor escenario de esta
     * integración: el POS manda la emisión, la red se corta ANTES de que llegue la
     * respuesta, el Job reintenta y SUNAT acaba con dos comprobantes fiscales reales
     * de la misma venta.
     *
     * OJO — corrección de un desajuste real: la clave repetida NO devuelve 409, sino
     * `200` con el comprobante original y `reutilizado: true` (el 409 está reservado a
     * `referencia_externa_duplicada`, que sí es un fallo del adaptador). Tratar el 409
     * como éxito era, además de inútil, peligroso: habría dado por emitida una venta
     * que el emisor rechazó por referencia repetida. Quien quiera saber si hubo
     * reutilización mira `Respuesta->reutilizado`.
     *
     * @param  array<string, mixed> $payload
     *
     * @throws FacturaMacException
     */
    public function emitirVenta(array $payload): Respuesta
    {
        $clave = (string) ($payload['idempotency_key'] ?? '');

        $response = $this->enviar(
            fn (PendingRequest $req) => $req
                ->withHeaders(['Idempotency-Key' => $clave])
                ->post("{$this->baseUrl}/api/v1/ventas", $payload),
            'emitir la venta',
        );

        $this->verificar($response, 'emitir la venta');

        return Respuesta::desdeArray($this->json($response));
    }

    /**
     * Configuración fiscal vigente del emisor. `GET /api/v1/configuracion`.
     *
     * Fuente ÚNICA de verdad de modo, umbrales y series. La cachea
     * `ConfiguracionFacturacion`; aquí solo se lee.
     *
     * @return array<string, mixed>
     *
     * @throws FacturaMacException
     */
    public function configuracion(): array
    {
        $response = $this->enviar(
            // Timeout recortado: esto se lee al pintar pantallas del POS, y ahí una
            // espera larga es peor que un default razonable.
            fn (PendingRequest $req) => $req->timeout(min($this->timeout, 8))
                ->get("{$this->baseUrl}/api/v1/configuracion"),
            'leer la configuración',
        );

        $this->verificar($response, 'leer la configuración');

        return $this->json($response);
    }

    /**
     * Estado actual del comprobante. `GET /api/v1/comprobantes/{id}`.
     *
     * Es la única forma de saber si una boleta salió del Resumen Diario con CDR
     * aceptado: en el momento de emitir todavía no existe respuesta de SUNAT.
     *
     * @return array<string, mixed>
     *
     * @throws FacturaMacException
     */
    public function consultar(int $id): array
    {
        $response = $this->enviar(
            fn (PendingRequest $req) => $req->get("{$this->baseUrl}/api/v1/comprobantes/{$id}"),
            "consultar comprobante {$id}",
        );

        $this->verificar($response, "consultar comprobante {$id}");

        return $this->json($response);
    }

    /**
     * PDF del comprobante (ticket 80 mm o A4 según la configuración del tenant).
     * Devuelve el binario tal cual para poder hacerle de proxy al navegador.
     *
     * @throws FacturaMacException
     */
    public function pdf(int $id): string
    {
        $response = $this->enviar(
            fn (PendingRequest $req) => $req
                ->withHeaders(['Accept' => 'application/pdf'])
                // El PDF se genera al vuelo y puede tardar más que un JSON.
                ->timeout($this->timeout * 2)
                ->get("{$this->baseUrl}/api/v1/comprobantes/{$id}/pdf"),
            "descargar PDF del comprobante {$id}",
        );

        $this->verificar($response, "descargar PDF del comprobante {$id}");

        return $response->body();
    }

    /**
     * Emite la Nota de Crédito que revierte un comprobante ya aceptado.
     * `POST /api/v1/comprobantes/{id}/nota-credito`.
     *
     * Es el único camino legal para deshacer una venta ya informada a SUNAT: anularla
     * solo en el POS dejaría la declaración fuera de cuadre (G7).
     *
     * @param  array<string, mixed> $data
     * @return array<string, mixed>
     *
     * @throws FacturaMacException
     */
    public function notaCredito(int $id, array $data): array
    {
        $response = $this->enviar(
            fn (PendingRequest $req) => $req->post("{$this->baseUrl}/api/v1/comprobantes/{$id}/nota-credito", $data),
            "emitir nota de crédito del comprobante {$id}",
        );

        $this->verificar($response, "emitir nota de crédito del comprobante {$id}");

        return $this->json($response);
    }

    /**
     * Reenvía a SUNAT un comprobante rechazado. `POST /api/v1/comprobantes/{id}/reintentar`.
     *
     * Sobre el MISMO comprobante y el mismo correlativo, nunca creando otro: si cada
     * rechazo generase un documento nuevo, la numeración quedaría llena de huecos que
     * hay que justificar ante SUNAT (G11).
     *
     * @return array<string, mixed>
     *
     * @throws FacturaMacException
     */
    public function reintentar(int $id): array
    {
        $response = $this->enviar(
            fn (PendingRequest $req) => $req->post("{$this->baseUrl}/api/v1/comprobantes/{$id}/reintentar"),
            "reintentar comprobante {$id}",
        );

        $this->verificar($response, "reintentar comprobante {$id}");

        return $this->json($response);
    }

    /**
     * Healthcheck + validación del token. `GET /api/v1/ping`.
     * Sirve para que la pantalla de configuración diga "conectado con el RUC X" antes
     * de que alguien descubra en plena caja que el token estaba mal.
     *
     * @return array<string, mixed>
     *
     * @throws FacturaMacException
     */
    public function ping(): array
    {
        $response = $this->enviar(
            fn (PendingRequest $req) => $req->timeout(min($this->timeout, 8))->get("{$this->baseUrl}/api/v1/ping"),
            'ping',
        );

        $this->verificar($response, 'ping');

        return $this->json($response);
    }

    // ── Interno ──────────────────────────────────────────────────────────────────

    private function base(): PendingRequest
    {
        return Http::withToken((string) $this->token)
            ->acceptJson()
            ->asJson()
            ->timeout($this->timeout)
            // No dejamos que el cliente HTTP reintente: eso es competencia del Job.
            ->withOptions(['http_errors' => false]);
    }

    /**
     * Ejecuta la llamada traduciendo los fallos de transporte.
     *
     * Un timeout o una conexión rechazada son SIEMPRE reintentables: puede que la
     * petición ni siquiera llegara, o que llegara y se perdiera la respuesta. El
     * `Idempotency-Key` es lo que hace seguro reintentar en el segundo caso.
     *
     * @param callable(PendingRequest): Response $llamada
     *
     * @throws FacturaMacException
     */
    private function enviar(callable $llamada, string $operacion): Response
    {
        if (empty($this->token)) {
            throw FacturaMacException::definitiva(
                "No hay token de FacturaMac configurado (FACTURAMAC_TOKEN); no se puede {$operacion}.",
            );
        }

        try {
            return $llamada($this->base());
        } catch (ConnectionException $e) {
            throw FacturaMacException::reintentable(
                "No se pudo contactar con FacturaMac para {$operacion}: {$e->getMessage()}",
                null,
                null,
                $e,
            );
        } catch (Throwable $e) {
            throw FacturaMacException::reintentable(
                "Fallo inesperado hablando con FacturaMac al {$operacion}: {$e->getMessage()}",
                null,
                null,
                $e,
            );
        }
    }

    /**
     * Clasifica la respuesta. El criterio es simple y lo usa el Job tal cual:
     * si el problema puede desaparecer solo con el tiempo, es reintentable.
     *
     * @throws FacturaMacException
     */
    private function verificar(Response $response, string $operacion): void
    {
        if ($response->successful()) {
            return;
        }

        $status = $response->status();
        $cuerpo = $this->cuerpo($response);
        $detalle = $this->mensajeDe($cuerpo) ?: $response->body();

        // 5xx: FacturaMac o SUNAT con problemas. 429: nos estamos pasando de ritmo.
        // 408: timeout del servidor. Todo eso se arregla esperando.
        if ($status >= 500 || $status === 429 || $status === 408) {
            throw FacturaMacException::reintentable(
                "FacturaMac respondió {$status} al {$operacion}: {$detalle}",
                $status,
                $cuerpo,
            );
        }

        // 422: el payload no cuadra (totales, validación). 401/403: token. 404: no existe.
        // Reintentar con el mismo dato daría exactamente el mismo error.
        throw FacturaMacException::definitiva(
            "FacturaMac rechazó la petición ({$status}) al {$operacion}: {$detalle}",
            $status,
            $cuerpo,
        );
    }

    /** @return array<string, mixed> */
    private function json(Response $response): array
    {
        $data = $response->json();

        return is_array($data) ? $data : [];
    }

    /** @return array<string, mixed>|string */
    private function cuerpo(Response $response): array|string
    {
        $data = $response->json();

        return is_array($data) ? $data : $response->body();
    }

    /** @param array<string, mixed>|string $cuerpo */
    private function mensajeDe(array|string $cuerpo): ?string
    {
        if (! is_array($cuerpo)) {
            return null;
        }

        $mensaje = $cuerpo['message'] ?? $cuerpo['error'] ?? null;

        if (! empty($cuerpo['errors']) && is_array($cuerpo['errors'])) {
            $detalles = [];
            foreach ($cuerpo['errors'] as $campo => $errores) {
                $detalles[] = $campo . ': ' . implode(', ', (array) $errores);
            }
            $mensaje = trim(($mensaje ? $mensaje . ' — ' : '') . implode(' | ', $detalles));
        }

        return $mensaje ? (string) $mensaje : null;
    }
}
