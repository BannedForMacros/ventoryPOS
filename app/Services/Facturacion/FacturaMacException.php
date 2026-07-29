<?php

namespace App\Services\Facturacion;

use MacSoft\Facturacion\Contrato\Dto\ErrorRespuesta;
use MacSoft\Facturacion\Contrato\Enum\CodigoError;
use RuntimeException;
use Throwable;

/**
 * Fallo hablando con FacturaMac.
 *
 * La propiedad que importa es `reintentable`, y no es un detalle cosmético: es lo que
 * decide si el Job vuelve a intentarlo o se rinde. Confundir los dos casos tiene coste
 * real en ambas direcciones — reintentar un 422 quema intentos y retrasa el aviso al
 * cajero; NO reintentar un timeout deja sin comprobante una venta que solo necesitaba
 * que SUNAT volviera a responder.
 *
 * Reintentable   → red caída, timeout, 5xx, 429. El dato está bien, el canal no.
 * No reintentable→ 4xx (salvo 429). El dato está mal; repetirlo da el mismo error.
 *
 * PERO el HTTP status es solo el ÚLTIMO recurso. El contrato (§4.3) manda `reintentable`
 * en el cuerpo precisamente para que la cola no tenga que deducirlo, y hay un caso donde
 * deducirlo está MAL: `estado_no_permite_nota_credito` viaja con 409 y `reintentable:
 * true`, porque una boleta en `pendiente_resumen` hay que ESPERARLA. Clasificarla por el
 * 4xx significaría que ninguna boleta devuelta genera jamás su nota de crédito.
 *
 * Los cuatro campos del contrato tienen destinatarios distintos y por eso se guardan por
 * separado en vez de aplastarse en un texto:
 *
 *   - `codigo`         → para programar contra él (`esCodigo(...)`). Nunca cambia.
 *   - `mensajeUsuario` → para la pantalla de la cajera.
 *   - `campo`          → para que la interfaz señale qué corregir.
 *   - `solucion`       → qué hacer al respecto.
 *
 * `getMessage()` sigue siendo el texto completo con contexto de operación (es lo que ya
 * consumen los Jobs y lo que se guarda en `venta_comprobantes.error`); los campos nuevos
 * son ADITIVOS y quien no los use no nota el cambio.
 */
class FacturaMacException extends RuntimeException
{
    /**
     * @param array<string, mixed>|string|null $cuerpo   Respuesta cruda, para diagnóstico.
     * @param string|null                      $codigo   Código estable del contrato §4.3, TAL CUAL llegó:
     *                                                   se guarda como string y no como enum porque el
     *                                                   catálogo del emisor puede ir por delante del
     *                                                   paquete y un código nuevo no debe perderse.
     */
    public function __construct(
        string $mensaje,
        public readonly bool $reintentable,
        public readonly ?int $status = null,
        public readonly array|string|null $cuerpo = null,
        ?Throwable $previous = null,
        public readonly ?string $codigo = null,
        public readonly ?string $campo = null,
        public readonly ?string $solucion = null,
        public readonly ?string $mensajeUsuario = null,
    ) {
        parent::__construct($mensaje, $status ?? 0, $previous);
    }

    public static function reintentable(string $mensaje, ?int $status = null, array|string|null $cuerpo = null, ?Throwable $previous = null): self
    {
        return new self($mensaje, true, $status, $cuerpo, $previous);
    }

    public static function definitiva(string $mensaje, ?int $status = null, array|string|null $cuerpo = null): self
    {
        return new self($mensaje, false, $status, $cuerpo);
    }

    /**
     * Construye la excepción a partir del error del contrato (§4.3).
     *
     * `$reintentable` llega ya decidido desde el cliente porque solo él sabe si el
     * cuerpo traía el campo o hubo que caer al status; el DTO no conoce el HTTP.
     *
     * @param array<string, mixed>|string|null $cuerpo
     */
    public static function desdeContrato(
        string $mensaje,
        ErrorRespuesta $error,
        bool $reintentable,
        ?int $status = null,
        array|string|null $cuerpo = null,
        ?string $codigo = null,
    ): self {
        return new self(
            mensaje: $mensaje,
            reintentable: $reintentable,
            status: $status,
            cuerpo: $cuerpo,
            previous: null,
            // Si el emisor mandó un código que este paquete aún no conoce, el DTO lo
            // degrada a `error_interno`; aquí conservamos el original para no perderlo
            // en los logs ni en un `esCodigo()` del consumidor.
            codigo: $codigo ?? $error->error->value,
            campo: $error->campo,
            solucion: $error->solucion,
            mensajeUsuario: $error->mensaje,
        );
    }

    /**
     * El código como enum, o `null` si el emisor mandó uno que el paquete no conoce.
     * Permite `match ($e->codigoError()) { ... }` sin comparar cadenas a mano.
     */
    public function codigoError(): ?CodigoError
    {
        return $this->codigo !== null ? CodigoError::tryFrom($this->codigo) : null;
    }

    /**
     * ¿Es alguno de estos códigos? Decidir por código y no por texto es lo que hace
     * que mejorar la redacción de un mensaje no rompa una rama de negocio.
     */
    public function esCodigo(CodigoError|string ...$codigos): bool
    {
        if ($this->codigo === null) {
            return false;
        }

        foreach ($codigos as $codigo) {
            if (($codigo instanceof CodigoError ? $codigo->value : $codigo) === $this->codigo) {
                return true;
            }
        }

        return false;
    }

    /**
     * Texto para una persona. Cae a `getMessage()` cuando no hubo cuerpo del contrato
     * (fallo de red, proxy caído): mejor un mensaje técnico que ninguno.
     */
    public function paraUsuario(): string
    {
        return $this->mensajeUsuario ?: $this->getMessage();
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return [
            'reintentable' => $this->reintentable,
            'status'       => $this->status,
            'codigo'       => $this->codigo,
            'campo'        => $this->campo,
            'solucion'     => $this->solucion,
            'cuerpo'       => $this->cuerpo,
        ];
    }
}
