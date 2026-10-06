<?php

namespace App\Services\VisorVentas;

/**
 * La foto no se pudo leer; el mensaje ya está en palabras de la cajera.
 *
 * `cobrada`: la API respondió (y cobró) aunque la lectura no sirva —se negó,
 * se cortó o devolvió algo ilegible—. Esa lectura gasta el cupo del día: si no,
 * fallar a propósito saltaría el límite.
 */
class LecturaFallida extends \RuntimeException
{
    public function __construct(
        string $message,
        int $code = 0,
        ?\Throwable $previous = null,
        public readonly bool $cobrada = false,
        public readonly ?string $modelo = null,
        public readonly ?int $tokensEntrada = null,
        public readonly ?int $tokensSalida = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
