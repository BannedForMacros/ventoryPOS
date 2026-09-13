<?php

namespace App\Console\Commands;

use App\Services\KardexService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Rearma kardex y stock desde los documentos con el MOTOR ÚNICO (KardexService).
 *
 * Solo escribe los productos cuya historia o saldo difieren: correrlo sobre un
 * inventario ya cuadrado no cambia nada. Con --simular no escribe y lista qué
 * productos cambiarían, para revisar el impacto antes de aplicarlo.
 *
 *   php artisan kardex:reconstruir --empresa=1097 --simular
 *   php artisan kardex:reconstruir --empresa=1097
 */
class ReconstruirKardex extends Command
{
    protected $signature = 'kardex:reconstruir
        {--empresa= : Limitar a una empresa}
        {--almacen= : Limitar a un almacén}
        {--simular : No escribe nada; muestra qué productos cambiarían}';

    protected $description = 'Rearma kardex y stock desde los documentos (motor único). Solo escribe lo que difiere.';

    public function handle(KardexService $kardex): int
    {
        return self::ejecutar($this, $kardex);
    }

    /** Compartido con stock:recalcular: los dos comandos corren el mismo motor. */
    public static function ejecutar(Command $cmd, KardexService $kardex): int
    {
        $empresaId = $cmd->option('empresa') ? (int) $cmd->option('empresa') : null;
        $almacenId = $cmd->option('almacen') ? (int) $cmd->option('almacen') : null;
        $simular   = (bool) $cmd->option('simular');

        $almacenIds = DB::table('almacenes')
            ->when($empresaId, fn ($q) => $q->where('empresa_id', $empresaId))
            ->when($almacenId, fn ($q) => $q->where('id', $almacenId))
            ->pluck('id')->map(fn ($id) => (int) $id)->all();

        if (empty($almacenIds)) {
            $cmd->warn('No hay almacenes en el alcance indicado.');

            return self::SUCCESS;
        }

        $pares = $kardex->paresDeAlmacenes($almacenIds);
        $cmd->info(($simular ? '[SIMULACIÓN] ' : '') . "Revisando {$pares->count()} productos (almacén × producto)...");

        $bar = $cmd->getOutput()->createProgressBar($pares->count());
        $bar->start();
        $res = $kardex->reconstruirAlmacenes($almacenIds, $simular, fn () => $bar->advance(), $pares);
        $bar->finish();
        $cmd->newLine(2);

        $verbo = $simular ? 'cambiarían' : 'corregidos';
        $cmd->line("  Kardex {$verbo}: {$res['kardex_corregidos']}");
        $cmd->line("  Stock {$verbo}:  {$res['stock_corregidos']}");
        $cmd->line("  Sin respaldo documental (no se tocan): {$res['sin_respaldo']}");
        $cmd->line(sprintf('  Valor del stock: S/ %s → S/ %s',
            number_format($res['valor_antes'], 2), number_format($res['valor_despues'], 2)));

        $conStock = collect($res['cambios'])->filter(fn ($c) => $c['stock_cambia'] || $c['sin_respaldo']);
        if ($conStock->isNotEmpty()) {
            $nombres = DB::table('productos')->whereIn('id', $conStock->pluck('producto_id'))->pluck('nombre', 'id');
            $cmd->newLine();
            $cmd->table(
                ['Almacén', 'Producto', 'Cant. antes', 'Cant. después', 'Costo antes', 'Costo después', 'Nota'],
                $conStock
                    ->sortByDesc(fn ($c) => abs($c['cantidad_despues'] * $c['costo_despues'] - $c['cantidad_antes'] * $c['costo_antes']))
                    ->take(50)
                    ->map(fn ($c) => [
                        $c['almacen_id'],
                        mb_strimwidth((string) ($nombres[$c['producto_id']] ?? "#{$c['producto_id']}"), 0, 40, '…'),
                        round($c['cantidad_antes'], 4),
                        round($c['cantidad_despues'], 4),
                        round($c['costo_antes'], 4),
                        round($c['costo_despues'], 4),
                        $c['sin_respaldo'] ? 'sin documentos: revisar' : '',
                    ])->values()->all(),
            );
            if ($conStock->count() > 50) {
                $cmd->line('  … y ' . ($conStock->count() - 50) . ' más.');
            }
        }

        if ($simular) {
            $cmd->warn('Simulación: no se escribió nada.');
        }

        return self::SUCCESS;
    }

    /**
     * Compatibilidad: rearma UN par con el motor único. Devuelve las filas de
     * kardex resultantes.
     */
    public function reconstruirPar(object $almacen, int $productoId): int
    {
        app(KardexService::class)->reconstruirPar((int) $almacen->id, $productoId);

        return DB::table('movimientos_inventario')
            ->where('almacen_id', $almacen->id)->where('producto_id', $productoId)->count();
    }
}
