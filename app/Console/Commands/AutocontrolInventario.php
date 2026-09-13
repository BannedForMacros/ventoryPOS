<?php

namespace App\Console\Commands;

use App\Services\AuditoriaService;
use App\Services\KardexService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Autocontrol nocturno del inventario.
 *
 * POR QUÉ EXISTE: la reconstrucción automática por producto corre en la cola.
 * Si el worker estuvo caído, o un movimiento se escapó por un camino que no la
 * dispara, el kardex podría quedar desfasado sin que nadie lo note hasta ver un
 * balance raro. Cada noche este comando revisa TODOS los productos de cada
 * empresa contra sus documentos y corrige solo los que difieren, dejando
 * constancia en auditoría. Es lo que reemplaza apretar "Recalcular" a mano.
 *
 *   php artisan inventario:autocontrol
 *   php artisan inventario:autocontrol --empresa=1097 --simular
 */
class AutocontrolInventario extends Command
{
    protected $signature = 'inventario:autocontrol
        {--empresa= : Limitar a una empresa}
        {--simular : No escribe nada; solo informa}';

    protected $description = 'Revisa stock y kardex de cada empresa contra sus documentos y corrige lo que difiera.';

    public function handle(KardexService $kardex): int
    {
        if (!Schema::hasTable('movimientos_inventario')) {
            $this->warn('Esta instalación no tiene kardex (movimientos_inventario): nada que controlar.');

            return self::SUCCESS;
        }

        $simular = (bool) $this->option('simular');

        $empresas = DB::table('almacenes')
            ->when($this->option('empresa'), fn ($q) => $q->where('empresa_id', (int) $this->option('empresa')))
            ->distinct()->orderBy('empresa_id')->pluck('empresa_id');

        foreach ($empresas as $empresaId) {
            $almacenIds = DB::table('almacenes')->where('empresa_id', $empresaId)
                ->pluck('id')->map(fn ($id) => (int) $id)->all();

            $res = $kardex->reconstruirAlmacenes($almacenIds, $simular);

            $this->line(sprintf('Empresa %d: %d productos revisados · kardex %d · stock %d · sin respaldo %d',
                $empresaId, $res['pares'], $res['kardex_corregidos'], $res['stock_corregidos'], $res['sin_respaldo']));

            $huboAlgo = $res['kardex_corregidos'] || $res['stock_corregidos'] || $res['sin_respaldo'];
            if ($simular || !$huboAlgo) {
                continue;
            }

            $nombres = DB::table('productos')
                ->whereIn('id', collect($res['cambios'])->pluck('producto_id'))->pluck('nombre', 'id');

            AuditoriaService::logSistema((int) $empresaId, 'stock.autoreparado', [
                'productos_revisados' => $res['pares'],
                'kardex_corregidos'   => $res['kardex_corregidos'],
                'stock_corregidos'    => $res['stock_corregidos'],
                'sin_respaldo'        => $res['sin_respaldo'],
                'valor_antes'         => $res['valor_antes'],
                'valor_despues'       => $res['valor_despues'],
                'detalle'             => collect($res['cambios'])->take(50)->map(fn ($c) => [
                    'producto_id'      => $c['producto_id'],
                    'producto'         => $nombres[$c['producto_id']] ?? null,
                    'almacen_id'       => $c['almacen_id'],
                    'cantidad_antes'   => round($c['cantidad_antes'], 4),
                    'cantidad_despues' => round($c['cantidad_despues'], 4),
                    'costo_antes'      => round($c['costo_antes'], 4),
                    'costo_despues'    => round($c['costo_despues'], 4),
                    'solo_kardex'      => !$c['stock_cambia'] && !$c['sin_respaldo'],
                    'sin_respaldo'     => $c['sin_respaldo'],
                ])->values()->all(),
            ]);

            Log::info('Autocontrol de inventario corrigió productos', [
                'empresa_id' => $empresaId,
                'kardex'     => $res['kardex_corregidos'],
                'stock'      => $res['stock_corregidos'],
            ]);
        }

        return self::SUCCESS;
    }
}
