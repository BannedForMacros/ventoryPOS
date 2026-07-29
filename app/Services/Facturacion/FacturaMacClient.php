<?php

namespace App\Services\Facturacion;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use MacSoft\Facturacion\Contrato\Dto\ErrorRespuesta;
use MacSoft\Facturacion\Contrato\Dto\Respuesta;
use MacSoft\Facturacion\Contrato\Enum\CodigoError;
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
 *
 * ─── El token se INYECTA, no se lee de la configuración global ────────────────
 *
 * Antes se leía de `config('facturamac.token')`, es decir, del `.env`: UN token para
 * toda la instalación. Como el token identifica a una empresa emisora, con dos
 * contribuyentes en el mismo ventoryPOS las ventas del segundo salían firmadas con el
 * RUC del primero. Ahora el token llega por constructor y quien lo resuelve es
 * `FacturacionEmpresa::cliente($empresaId)`, que lo saca de la conexión de ESA
 * empresa. Este cliente ya no puede equivocarse de emisor porque ya no elige.
 *
 * La URL sí sigue en `config/facturamac.php`: FacturaMac es un único servicio para
 * toda la instalación. Eso es dónde vive el servicio, no de quién es la cuenta.
 */
class FacturaMacClient
{
    private string $baseUrl;
    private ?string $token;
    private int $timeout;

    public function __construct(?string $token = null)
    {
        $this->baseUrl = rtrim((string) config('facturamac.base_url'), '/');
        $this->token   = $token;
        $this->timeout = (int) config('facturamac.timeout', 20);
    }

    /** ¿Este cliente tiene con qué autenticarse? Lo usa la pantalla de configuración. */
    public function tieneToken(): bool
    {
        return trim((string) $this->token) !== '';
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
     * Estado actual del comprobante. `GET /api/v1/ventas/{id}`.
     *
     * Es la única forma de saber si una boleta salió del Resumen Diario con CDR
     * aceptado: en el momento de emitir todavía no existe respuesta de SUNAT.
     *
     * @return array<string, mixed>
     *
     * @throws FacturaMacException
     */
    /**
     * Devuelve el DTO del contrato, no el array crudo: los campos del comprobante
     * y de SUNAT van anidados, y leerlos a mano por clave de primer nivel es
     * exactamente el error que hacía que el motivo de un rechazo no se registrase
     * nunca. Con el DTO, un cambio de forma se detecta al compilar.
     */
    public function consultar(int $id): Respuesta
    {
        $response = $this->enviar(
            fn (PendingRequest $req) => $req->get("{$this->baseUrl}/api/v1/ventas/{$id}"),
            "consultar comprobante {$id}",
        );

        $this->verificar($response, "consultar comprobante {$id}");

        return Respuesta::desdeArray($this->json($response));
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
                ->get("{$this->baseUrl}/api/v1/ventas/{$id}/pdf"),
            "descargar PDF del comprobante {$id}",
        );

        $this->verificar($response, "descargar PDF del comprobante {$id}");

        return $response->body();
    }

    /**
     * Emite la Nota de Crédito que revierte un comprobante ya aceptado.
     * `POST /api/v1/ventas/{id}/nota-credito`.
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
            fn (PendingRequest $req) => $req->post("{$this->baseUrl}/api/v1/ventas/{$id}/nota-credito", $data),
            "emitir nota de crédito del comprobante {$id}",
        );

        $this->verificar($response, "emitir nota de crédito del comprobante {$id}");

        return $this->json($response);
    }

    /**
     * Reenvía a SUNAT un comprobante rechazado. `POST /api/v1/ventas/{id}/reintentar`.
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
            fn (PendingRequest $req) => $req->post("{$this->baseUrl}/api/v1/ventas/{$id}/reintentar"),
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

    /**
     * Canjea un CÓDIGO CORTO de vinculación por el token de la empresa.
     * `POST /api/v1/vincular` — PÚBLICO, sin autenticación.
     *
     * Es el único método que NO pasa por `enviar()`, y a propósito: aquí todavía no
     * hay token, que es justo lo que se viene a buscar. La guarda de `enviar()` lo
     * rechazaría antes de salir.
     *
     * POR QUÉ UN CÓDIGO Y NO EL TOKEN A MANO: el requisito es que todo se haga por
     * interfaz. Un token de Sanctum son 50 caracteres que hay que copiar sin
     * equivocarse, y equivocarse significa emitir con el RUC de otro o no emitir. Un
     * código de 8 caracteres de un solo uso y con caducidad se dicta por teléfono.
     *
     * Contrato (cerrado, lo implementa FacturaMac):
     *   200 → { token, emisor: { ruc, razon_social, modo } }
     *   422 → codigo_invalido   410 → codigo_expirado
     *   409 → codigo_usado      429 → rate limit
     *
     * @return array<string, mixed>
     *
     * @throws FacturaMacException
     */
    public function vincular(string $codigo, string $instalacion): array
    {
        try {
            $response = Http::acceptJson()
                ->asJson()
                ->timeout(min($this->timeout, 15))
                ->withOptions(['http_errors' => false])
                ->post("{$this->baseUrl}/api/v1/vincular", [
                    'codigo'      => $codigo,
                    'sistema'     => 'ventoryPOS',
                    'instalacion' => $instalacion,
                ]);
        } catch (ConnectionException $e) {
            throw FacturaMacException::reintentable(
                "No se pudo contactar con FacturaMac en {$this->baseUrl}: {$e->getMessage()}",
                null,
                null,
                $e,
            );
        } catch (Throwable $e) {
            throw FacturaMacException::reintentable(
                "Fallo inesperado hablando con FacturaMac al vincular: {$e->getMessage()}",
                null,
                null,
                $e,
            );
        }

        if ($response->successful()) {
            return $this->json($response);
        }

        // Los códigos del contrato se traducen a algo que lea la persona que tiene el
        // código delante. `error` es el código estable; nunca se le enseña tal cual.
        $codigoError = (string) ($this->json($response)['error'] ?? '');

        $mensaje = match (true) {
            $codigoError === 'codigo_invalido'  => 'El código de vinculación no es válido. Revísalo y vuelve a intentarlo.',
            $codigoError === 'codigo_expirado'  => 'El código de vinculación ya caducó. Pide uno nuevo en FacturaMac.',
            $codigoError === 'codigo_usado'     => 'Ese código de vinculación ya se usó. Pide uno nuevo en FacturaMac.',
            $response->status() === 429         => 'Demasiados intentos seguidos. Espera un minuto y vuelve a intentarlo.',
            default                             => $this->mensajeDe($this->cuerpo($response))
                ?? "FacturaMac respondió {$response->status()} al vincular.",
        };

        // 429 es el único reintentable: los otros tres exigen un código distinto.
        throw new FacturaMacException(
            $mensaje,
            $response->status() === 429,
            $response->status(),
            $this->cuerpo($response),
        );
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
                "Esta empresa no está conectada con FacturaMac; no se puede {$operacion}. "
                . 'Conéctala en Configuración → Facturación Electrónica.',
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
     * Clasifica la respuesta. El criterio lo usa el Job tal cual: si el problema puede
     * desaparecer solo con el tiempo, es reintentable.
     *
     * TRES CAPAS, en este orden y no en otro:
     *
     *   1. `reintentable` del CUERPO (§4.3). Manda siempre: el emisor conoce la
     *      situación concreta y puede saber algo que el catálogo no.
     *   2. El catálogo, por código (`CodigoError::esReintentable()`).
     *   3. El HTTP status, solo cuando el cuerpo no trae nada útil.
     *
     * Deducir por status a secas —lo que se hacía antes— rompe un caso por diseño:
     * `estado_no_permite_nota_credito` es 409 CON `reintentable: true`, porque una
     * boleta en `pendiente_resumen` hay que ESPERARLA hasta el Resumen Diario de las
     * 23:55. Tratar ese 4xx como definitivo significaría que ninguna boleta devuelta
     * genera jamás su nota de crédito.
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
        $datos  = is_array($cuerpo) ? $cuerpo : [];

        // Respaldo de la capa 3. 5xx: FacturaMac o SUNAT con problemas. 429: nos
        // estamos pasando de ritmo. 408: timeout del servidor. Se arregla esperando.
        $porStatus = $status >= 500 || $status === 429 || $status === 408;

        // El código estable del contrato. Se conserva TAL CUAL aunque el catálogo del
        // paquete todavía no lo conozca: el emisor puede ir por delante y perder el
        // código dejaría al consumidor decidiendo por el texto del mensaje.
        $codigo = is_string($datos['error'] ?? null) && $datos['error'] !== ''
            ? $datos['error']
            : null;
        $codigoConocido = $codigo !== null ? CodigoError::tryFrom($codigo) : null;

        $mensajeUsuario = $this->mensajeDe($cuerpo);

        // Sin código del contrato no hay error del contrato: será una validación de
        // Laravel, un proxy caído o HTML. Se clasifica por status, como toda la vida.
        if ($codigo === null) {
            $reintentableCuerpo = isset($datos['reintentable']) ? (bool) $datos['reintentable'] : null;

            $this->lanzar(
                $operacion,
                $status,
                $cuerpo,
                $reintentableCuerpo ?? $porStatus,
                $mensajeUsuario ?? $this->cuerpoLegible($response),
            );
        }

        // El DTO compartido es quien sabe leer §4.3; no reimplementamos el catálogo.
        // Se le pasa el mensaje ya resuelto porque él solo mira `mensaje`, y hay
        // respuestas antiguas o de otro origen que traen `message`. Si no hay ninguno
        // se QUITA la clave para que aplique el texto por defecto del DTO: un
        // `"mensaje": ""` le haría lanzar, y un error del emisor no puede convertirse
        // en una excepción distinta de camino a casa.
        $datosError = $datos;
        $datosError['campo']    = $this->texto($datos['campo'] ?? null);
        $datosError['solucion'] = $this->texto($datos['solucion'] ?? null);

        if ($mensajeUsuario !== null) {
            $datosError['mensaje'] = $mensajeUsuario;
        } else {
            unset($datosError['mensaje']);
        }

        $error = ErrorRespuesta::desdeArray($datosError);

        $reintentable = $codigoConocido !== null
            // `esReintentable()` ya aplica «cuerpo manda, catálogo de respaldo».
            ? $error->esReintentable()
            // Código desconocido: el catálogo no opina (el DTO lo degradó a
            // `error_interno`, que es reintentable, y creérselo dejaría la cola
            // girando sobre un error definitivo). Vale lo que dijo el emisor y,
            // si no dijo nada, el status.
            : ($error->reintentable ?? $porStatus);

        $this->lanzar(
            $operacion,
            $status,
            $cuerpo,
            $reintentable,
            $this->detalle($error, $codigo),
            $error,
            $codigo,
        );
    }

    /**
     * Arma y lanza la excepción. Extraído para que `verificar()` se lea como la
     * decisión que es y no como cuatro `throw` casi iguales.
     *
     * @param array<string, mixed>|string|null $cuerpo
     *
     * @throws FacturaMacException
     */
    private function lanzar(
        string $operacion,
        int $status,
        array|string|null $cuerpo,
        bool $reintentable,
        ?string $detalle,
        ?ErrorRespuesta $error = null,
        ?string $codigo = null,
    ): never {
        $detalle = $detalle !== null && $detalle !== '' ? $detalle : 'sin detalle en la respuesta';

        // El verbo cambia según la decisión, no según el status: un 409 reintentable
        // no lo «rechazó» nadie, es que todavía no toca.
        $texto = $reintentable
            ? "FacturaMac respondió {$status} al {$operacion}: {$detalle}"
            : "FacturaMac rechazó la petición ({$status}) al {$operacion}: {$detalle}";

        throw $error !== null
            ? FacturaMacException::desdeContrato($texto, $error, $reintentable, $status, $cuerpo, $codigo)
            : new FacturaMacException($texto, $reintentable, $status, $cuerpo);
    }

    /**
     * Texto técnico para el log y para `venta_comprobantes.error`.
     *
     * Primero lo que entiende una persona, después qué hacer, y el código al final
     * entre corchetes: es lo que se busca en los logs, pero la cajera lee el principio
     * de la frase, no el final.
     */
    private function detalle(ErrorRespuesta $error, ?string $codigo): string
    {
        $partes = array_filter([
            $error->mensaje,
            $error->solucion,
            $codigo !== null ? "[{$codigo}]" : null,
        ]);

        return implode(' ', $partes);
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

    /**
     * Texto para una PERSONA, si la respuesta trae alguno.
     *
     * El orden es deliberado: el contrato (§4.3) manda `mensaje` para la pantalla y
     * `error` para programar. Leer `error` como si fuera el texto es lo que acababa
     * enseñándole `cliente_sin_ruc` o `totales_no_cuadran` a la cajera y guardándolo
     * en `venta_comprobantes.error`.
     *
     * `message` se conserva de respaldo porque lo usan las validaciones de Laravel y
     * cualquier respuesta anterior al contrato o de otro origen (un proxy, un WAF).
     *
     * @param array<string, mixed>|string $cuerpo
     */
    private function mensajeDe(array|string $cuerpo): ?string
    {
        if (! is_array($cuerpo)) {
            return null;
        }

        $mensaje = $this->texto($cuerpo['mensaje'] ?? null)
            ?? $this->texto($cuerpo['message'] ?? null);

        // `error` como texto SOLO si no es un código del catálogo: hay APIs que ponen
        // ahí la frase entera, pero un código del contrato no es un mensaje.
        if ($mensaje === null) {
            $suelto = $this->texto($cuerpo['error'] ?? null);
            $mensaje = $suelto !== null && CodigoError::tryFrom($suelto) === null ? $suelto : null;
        }

        // Bolsa de validación de Laravel: se anexa porque dice QUÉ campo falló, que
        // es justo lo que le falta al mensaje genérico.
        if (! empty($cuerpo['errors']) && is_array($cuerpo['errors'])) {
            $detalles = [];
            foreach ($cuerpo['errors'] as $campo => $errores) {
                $detalles[] = $campo . ': ' . implode(', ', array_map('strval', (array) $errores));
            }
            $mensaje = trim(($mensaje ? $mensaje . ' — ' : '') . implode(' | ', $detalles));
        }

        return $mensaje !== null && $mensaje !== '' ? $mensaje : null;
    }

    /**
     * Último recurso cuando la respuesta no es JSON: un proxy caído, un WAF o un
     * Cloudflare devuelven HTML. Volcarlo entero dejaba una página web dentro de
     * `venta_comprobantes.error` y en la pantalla de la cajera, así que se resume.
     */
    private function cuerpoLegible(Response $response): ?string
    {
        $body = trim($response->body());

        if ($body === '') {
            return null;
        }

        if (str_starts_with($body, '<')) {
            return 'la respuesta no era JSON (probablemente un proxy o el servidor caído).';
        }

        return mb_strimwidth($body, 0, 300, '…');
    }

    /**
     * Normaliza a texto no vacío. Un cuerpo mal formado (un array donde debía haber
     * una cadena) no puede tumbar al cliente: el fallo real ya lo tenemos delante.
     */
    private function texto(mixed $valor): ?string
    {
        if (! is_scalar($valor)) {
            return null;
        }

        $texto = trim((string) $valor);

        return $texto !== '' ? $texto : null;
    }
}
