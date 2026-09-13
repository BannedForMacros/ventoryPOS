<?php

namespace App\Console\Commands;

use App\Services\KardexService;
use Illuminate\Console\Command;

/**
 * Equivalente a kardex:reconstruir: stock y kardex salen del mismo motor, así que
 * recalcular uno es recalcular el otro. Se conserva el nombre por compatibilidad.
 *
 *   php artisan stock:recalcular --empresa=1097 --simular
 */
class RecalcularStock extends Command
{
    protected $signature = 'stock:recalcular
        {--empresa= : Limitar a una empresa}
        {--almacen= : Limitar a un almacén}
        {--simular : No escribe nada; muestra qué productos cambiarían}';

    protected $description = 'Rearma stock y kardex desde los documentos (mismo motor que kardex:reconstruir).';

    public function handle(KardexService $kardex): int
    {
        return ReconstruirKardex::ejecutar($this, $kardex);
    }
}
