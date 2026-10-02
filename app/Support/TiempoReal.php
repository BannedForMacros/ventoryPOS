<?php

namespace App\Support;

use App\Events\Cambio;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Junta los "cambió X" de una operación y los avisa UNA vez, cuando la
 * transacción se confirma (una venta toca muchas filas de stock: sale un
 * solo aviso). Si la operación se deshace no se avisa nada, y si Reverb no
 * responde se registra en el log y la operación sigue como si nada.
 */
class TiempoReal
{
    /** @var array<int, array<string, true>> empresa → recursos */
    private static array $pendientes = [];

    /**
     * @param bool $enConsola true solo para cambios puntuales que llegan por la
     *                        cola y el usuario espera ver (estado de SUNAT).
     */
    public static function marcar(?int $empresaId, array $recursos, bool $enConsola = false): void
    {
        if (!$empresaId || !self::activo($enConsola)) return;

        foreach ($recursos as $r) {
            self::$pendientes[$empresaId][$r] = true;
        }
        // Sin transacción corre en el acto; dentro de una, al confirmar la
        // más externa (y se descarta si se deshace).
        DB::afterCommit(fn () => self::enviar());
    }

    public static function enviar(): void
    {
        $pendientes = self::$pendientes;
        self::$pendientes = [];

        foreach ($pendientes as $empresaId => $recursos) {
            try {
                broadcast(new Cambio($empresaId, array_keys($recursos)));
            } catch (\Throwable $e) {
                Log::warning('Tiempo real: no se pudo avisar', ['empresa' => $empresaId, 'error' => $e->getMessage()]);
            }
        }
    }

    /**
     * Solo avisan las acciones de los usuarios (peticiones web). Los procesos
     * de consola —comandos, cola, tareas programadas— no: un recálculo de
     * stock guarda miles de filas y haría recargar todas las pantallas
     * abiertas una y otra vez.
     */
    private static function activo(bool $enConsola = false): bool
    {
        if (!$enConsola && app()->runningInConsole() && !app()->runningUnitTests()) return false;

        return !in_array(config('broadcasting.default'), [null, 'null', 'log'], true);
    }
}
