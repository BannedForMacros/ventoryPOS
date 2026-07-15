<?php

namespace App\Console\Commands;

use App\Models\Stock;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Carga el INVENTARIO INICIAL (stock de apertura) por producto desde un CSV
 * exportado del inventario (nombre, total, costo), a una FECHA DE CORTE.
 *
 * Llena la tabla `stock_iniciales` y luego reconstruye el stock: a partir del
 * corte, Stock::reconstruir() arranca de estas cantidades y suma solo los
 * movimientos posteriores. Así "Recalcular stock" ya NO borra la apertura.
 *
 * El mapeo producto es por NOMBRE (normalizado) dentro de la empresa. Corre
 * SIEMPRE en el servidor destino (usa los producto_id de ESA base).
 *
 *   php artisan stock:cargar-inicial produccion/data/stock_inicial_1307.csv --empresa=2 --fecha=2026-07-13
 *   php artisan stock:cargar-inicial ... --dry     (solo diagnóstico, no escribe)
 */
class CargarStockInicial extends Command
{
    protected $signature = 'stock:cargar-inicial
        {csv : Ruta al CSV con columnas nombre,total,costo}
        {--empresa= : ID de la empresa (obligatorio)}
        {--fecha=2026-07-13 : Fecha de corte del inventario inicial}
        {--almacen= : ID del almacén destino (por defecto, el almacén local de la empresa)}
        {--dry : No escribe nada; solo muestra cuántos mapean y cuáles no}';

    protected $description = 'Carga el inventario inicial (stock de apertura) desde un CSV y reconstruye el stock.';

    public function handle(): int
    {
        $csv       = $this->argument('csv');
        $empresaId = (int) $this->option('empresa');
        $fecha     = substr((string) $this->option('fecha'), 0, 10);
        $dry       = (bool) $this->option('dry');

        if (!$empresaId) { $this->error('Falta --empresa'); return self::FAILURE; }
        if (!is_file($csv)) { $this->error("No existe el CSV: {$csv}"); return self::FAILURE; }

        // Almacén destino: el indicado, o el almacén local activo de la empresa.
        $almacenId = $this->option('almacen') ? (int) $this->option('almacen')
            : (int) DB::table('almacenes')->where('empresa_id', $empresaId)
                ->where('tipo', 'local')->where('activo', true)->orderBy('id')->value('id');
        if (!$almacenId) { $this->error('No se pudo resolver el almacén. Usa --almacen='); return self::FAILURE; }

        $this->info("Empresa {$empresaId} · almacén {$almacenId} · corte {$fecha}" . ($dry ? ' · DRY-RUN' : ''));

        // Índice de productos de la empresa por nombre normalizado.
        $norm = fn ($s) => strtoupper(trim(preg_replace('/\s+/', ' ', (string) $s)));
        $byNombre = [];
        foreach (DB::table('productos')->where('empresa_id', $empresaId)->get(['id', 'nombre']) as $p) {
            $byNombre[$norm($p->nombre)] = $p->id;
        }

        // Leer CSV
        $fh = fopen($csv, 'r');
        $header = fgetcsv($fh);
        $idx = array_flip(array_map(fn ($h) => strtolower(trim($h)), $header));
        foreach (['nombre', 'total', 'costo'] as $req) {
            if (!isset($idx[$req])) { $this->error("El CSV no tiene la columna '{$req}'"); fclose($fh); return self::FAILURE; }
        }

        $mapeados = []; $sinMatch = []; $filas = 0;
        while (($row = fgetcsv($fh)) !== false) {
            $nombre = $row[$idx['nombre']] ?? '';
            if ($nombre === '') { continue; }
            $filas++;
            $pid = $byNombre[$norm($nombre)] ?? null;
            if (!$pid) { $sinMatch[] = $nombre; continue; }
            $mapeados[$pid] = [
                'cantidad' => (float) str_replace(',', '', (string) ($row[$idx['total']] ?? 0)),
                'costo'    => (float) str_replace(',', '', (string) ($row[$idx['costo']] ?? 0)),
            ];
        }
        fclose($fh);

        $this->info("Filas: {$filas} · mapeados: " . count($mapeados) . " · sin match: " . count($sinMatch));
        if ($sinMatch) {
            $this->warn('Sin match (revisar nombre en la BD): ' . implode(' | ', array_slice($sinMatch, 0, 20))
                . (count($sinMatch) > 20 ? ' …' : ''));
        }

        if ($dry) { $this->comment('DRY-RUN: no se escribió nada.'); return self::SUCCESS; }
        if (empty($mapeados)) { $this->warn('Nada que cargar.'); return self::SUCCESS; }

        $ahora = now();
        DB::transaction(function () use ($mapeados, $empresaId, $almacenId, $fecha, $ahora) {
            foreach ($mapeados as $pid => $d) {
                DB::table('stock_iniciales')->updateOrInsert(
                    ['almacen_id' => $almacenId, 'producto_id' => $pid],
                    ['empresa_id' => $empresaId, 'fecha' => $fecha,
                     'cantidad' => $d['cantidad'], 'costo' => $d['costo'], 'updated_at' => $ahora, 'created_at' => $ahora],
                );
            }
        });
        $this->info('Inventario inicial cargado en stock_iniciales.');

        // Reconstruir el stock de cada producto cargado (arranca del inicial +
        // movimientos posteriores al corte).
        $bar = $this->output->createProgressBar(count($mapeados));
        foreach (array_keys($mapeados) as $pid) {
            Stock::reconstruir($almacenId, (int) $pid);
            $bar->advance();
        }
        $bar->finish();
        $this->newLine();
        $this->info('Stock reconstruido desde el inventario inicial. ✔');

        return self::SUCCESS;
    }
}
