<?php

namespace App\Console\Commands;

use App\Models\Stock;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Recalcula (reconstruye) el stock de TODOS los productos con movimientos o con
 * inventario inicial, por empresa/almacén. Equivalente por CLI al botón
 * "Recalcular stock", pero cubriendo también las aperturas (stock_iniciales),
 * así que es SEGURO: arranca del inventario inicial + movimientos posteriores.
 *
 *   php artisan stock:recalcular --empresa=1097
 *   php artisan stock:recalcular --empresa=1097 --almacen=1110
 */
class RecalcularStock extends Command
{
    protected $signature = 'stock:recalcular
        {--empresa= : Limitar a una empresa}
        {--almacen= : Limitar a un almacén}';

    protected $description = 'Reconstruye el stock (respetando el inventario inicial) por empresa/almacén.';

    public function handle(): int
    {
        $empresaId = $this->option('empresa') ? (int) $this->option('empresa') : null;
        $almacenId = $this->option('almacen') ? (int) $this->option('almacen') : null;

        $almacenIds = DB::table('almacenes')
            ->when($empresaId, fn ($q) => $q->where('empresa_id', $empresaId))
            ->when($almacenId, fn ($q) => $q->where('id', $almacenId))
            ->pluck('id')->toArray();

        if (empty($almacenIds)) { $this->warn('No hay almacenes en el alcance.'); return self::SUCCESS; }

        $pares = Stock::combinacionesConMovimientos($almacenIds);
        $this->info('Combinaciones (almacén×producto) a reconstruir: ' . $pares->count());

        DB::transaction(function () use ($almacenIds, $pares) {
            // Reset y reconstrucción (mismo criterio que el botón "Recalcular stock").
            Stock::whereIn('almacen_id', $almacenIds)->update(['cantidad' => 0, 'costo_promedio' => 0]);
            $bar = $this->output->createProgressBar($pares->count());
            foreach ($pares as $p) {
                Stock::reconstruir((int) $p['almacen_id'], (int) $p['producto_id']);
                $bar->advance();
            }
            $bar->finish();
        });

        $this->newLine();
        $this->info('Stock reconstruido (inventario inicial + movimientos posteriores). ✔');
        return self::SUCCESS;
    }
}
