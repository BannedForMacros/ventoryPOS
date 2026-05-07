<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Lanzada cuando una salida de stock (venta, transferencia, salida manual) intenta
 * descontar más unidades de las disponibles en el almacén indicado.
 *
 * Pensada para ser capturada por las capas Service y traducida en mensajes amigables
 * al usuario. Cuando se lanza dentro de una DB::transaction, la transacción
 * se revierte automáticamente — el efecto es que la venta/transferencia NO se persiste.
 */
class InsufficientStockException extends RuntimeException
{
    public function __construct(
        public readonly int   $almacenId,
        public readonly int   $productoId,
        public readonly float $disponible,
        public readonly float $solicitado,
        public readonly ?string $productoNombre = null,
    ) {
        $nombre  = $productoNombre ?? "ID {$productoId}";
        $message = sprintf(
            'Stock insuficiente para "%s" (almacen %d). Disponible: %s, solicitado: %s.',
            $nombre, $almacenId, rtrim(rtrim(number_format($disponible, 4, '.', ''), '0'), '.'),
            rtrim(rtrim(number_format($solicitado, 4, '.', ''), '0'), '.')
        );
        parent::__construct($message, 422);
    }
}
