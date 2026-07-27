<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * La venta NO se puede proyectar a un comprobante electrónico válido.
 *
 * Es una excepción de DOMINIO, no un fallo técnico: nunca significa "la red falló",
 * siempre significa "con estos datos, emitir sería emitir algo incorrecto". Por eso
 * el Job debe marcar `error_mapeo` y NO reintentar: reintentar el mismo dato malo
 * solo repite el fallo. Alguien tiene que corregir la venta, el cliente o la
 * configuración de la empresa.
 *
 * Lleva contexto estructurado (venta_id + datos) porque cuando esto salta en
 * producción lo primero que se necesita es saber QUÉ venta y CON QUÉ NÚMEROS
 * descuadró, sin tener que reproducirlo.
 */
class MapeoComprobanteException extends RuntimeException
{
    /**
     * @param array<string, mixed> $datos Números y valores concretos del fallo.
     */
    public function __construct(
        public readonly ?int $ventaId,
        public readonly string $motivo,
        public readonly array $datos = [],
    ) {
        parent::__construct($motivo);
    }

    /**
     * @param array<string, mixed> $datos
     */
    public static function para(?int $ventaId, string $motivo, array $datos = []): self
    {
        return new self($ventaId, $motivo, $datos);
    }

    /**
     * Laravel adjunta automáticamente lo que devuelve `context()` a la entrada de log,
     * así que el descuadre queda registrado con sus cifras sin escribir un Log::error
     * extra en cada punto de fallo.
     *
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return ['venta_id' => $this->ventaId, 'motivo' => $this->motivo] + $this->datos;
    }
}
