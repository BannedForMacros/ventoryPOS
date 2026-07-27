<?php

namespace App\Services\Facturacion;

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
 */
class FacturaMacException extends RuntimeException
{
    /**
     * @param array<string, mixed>|string|null $cuerpo Respuesta cruda, para diagnóstico.
     */
    public function __construct(
        string $mensaje,
        public readonly bool $reintentable,
        public readonly ?int $status = null,
        public readonly array|string|null $cuerpo = null,
        ?Throwable $previous = null,
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

    /** @return array<string, mixed> */
    public function context(): array
    {
        return [
            'reintentable' => $this->reintentable,
            'status'       => $this->status,
            'cuerpo'       => $this->cuerpo,
        ];
    }
}
