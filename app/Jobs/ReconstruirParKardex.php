<?php

namespace App\Jobs;

use App\Services\KardexService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Rearma el kardex y el stock de UN producto en UN almacén.
 *
 * POR QUÉ EXISTE: el kardex en vivo solo agrega filas al final. Cuando llega un
 * movimiento con fecha pasada, o se corrige uno ya registrado (edición,
 * anulación, reverso), la cadena de saldos queda desordenada. Antes eso se
 * arreglaba apretando "Recalcular" para toda la empresa; ahora se rearma solo
 * este producto, en segundo plano, apenas se confirma la operación.
 *
 * Es idempotente: si el kardex ya describe la historia correcta no escribe
 * nada, así que encolarlo dos veces para el mismo producto no hace daño.
 */
class ReconstruirParKardex implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public array $backoff = [10, 60];

    public function __construct(
        public int $almacenId,
        public int $productoId,
        public string $motivo = '',
    ) {}

    public function handle(KardexService $kardex): void
    {
        $kardex->reconstruirPar($this->almacenId, $this->productoId);
    }
}
