<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Actualiza el PRECIO DE VENTA de la unidad BASE de cada producto desde un CSV
 * (nombre, precio), mapeando por NOMBRE normalizado dentro de la empresa.
 *
 * Motivo: el precio_venta había quedado igual al costo. Este comando lo corrige
 * con la lista de precios real (columna PRE1 del Excel "Lista de Precios y Stock").
 *
 * - Solo toca la unidad base (`producto_unidades.es_base = true`).
 * - Ignora precios <= 0 (deja el actual) y los reporta.
 * - Reporta los productos donde el nuevo precio queda POR DEBAJO del costo.
 *
 *   php artisan precios:actualizar produccion/data/precios_venta_1307.csv --empresa=1097
 *   php artisan precios:actualizar ... --dry     (solo diagnóstico)
 */
class ActualizarPreciosVenta extends Command
{
    protected $signature = 'precios:actualizar
        {csv : Ruta al CSV con columnas nombre,precio}
        {--empresa= : ID de la empresa (obligatorio)}
        {--dry : No escribe; solo muestra qué se actualizaría}';

    protected $description = 'Actualiza el precio de venta (unidad base) de los productos desde un CSV.';

    public function handle(): int
    {
        $csv       = $this->argument('csv');
        $empresaId = (int) $this->option('empresa');
        $dry       = (bool) $this->option('dry');

        if (!$empresaId)      { $this->error('Falta --empresa'); return self::FAILURE; }
        if (!is_file($csv))   { $this->error("No existe el CSV: {$csv}"); return self::FAILURE; }

        // Índice de unidades BASE por nombre de producto normalizado.
        $norm = fn ($s) => strtoupper(trim(preg_replace('/\s+/', ' ', (string) $s)));
        $base = []; // nombreNorm => ['pu_id'=>, 'costo'=>, 'precio'=>]
        $filas = DB::table('productos as p')
            ->join('producto_unidades as pu', 'pu.producto_id', '=', 'p.id')
            ->where('p.empresa_id', $empresaId)
            ->where('pu.es_base', true)
            ->get(['p.nombre', 'pu.id as pu_id', 'pu.precio_costo', 'pu.precio_venta']);
        foreach ($filas as $r) {
            $base[$norm($r->nombre)] = ['pu_id' => $r->pu_id, 'costo' => (float) $r->precio_costo, 'precio' => (float) $r->precio_venta];
        }

        $fh = fopen($csv, 'r');
        $header = fgetcsv($fh);
        $idx = array_flip(array_map(fn ($h) => strtolower(trim($h)), $header));
        foreach (['nombre', 'precio'] as $req) {
            if (!isset($idx[$req])) { $this->error("El CSV no tiene la columna '{$req}'"); fclose($fh); return self::FAILURE; }
        }

        $updates = [];   // pu_id => nuevoPrecio
        $sinMatch = []; $cero = []; $bajoCosto = []; $leidas = 0;
        while (($row = fgetcsv($fh)) !== false) {
            $nombre = $row[$idx['nombre']] ?? '';
            if ($nombre === '') continue;
            $leidas++;
            $b = $base[$norm($nombre)] ?? null;
            if (!$b) { $sinMatch[] = $nombre; continue; }
            $precio = (float) str_replace(',', '', (string) ($row[$idx['precio']] ?? 0));
            if ($precio <= 0) { $cero[] = $nombre; continue; }
            if ($b['costo'] > 0 && $precio < $b['costo'] - 0.001) {
                $bajoCosto[] = "{$nombre} (precio {$precio} < costo {$b['costo']})";
            }
            $updates[$b['pu_id']] = $precio;
        }
        fclose($fh);

        $this->info("Empresa {$empresaId}" . ($dry ? ' · DRY-RUN' : ''));
        $this->info("Filas CSV: {$leidas} · a actualizar: " . count($updates)
            . " · sin match: " . count($sinMatch) . " · precio 0 (omitidos): " . count($cero));
        if ($bajoCosto) {
            $this->warn('OJO — quedarían BAJO COSTO (' . count($bajoCosto) . '): '
                . implode(' | ', array_slice($bajoCosto, 0, 15)) . (count($bajoCosto) > 15 ? ' …' : ''));
        }
        if ($sinMatch) {
            $this->line('Sin match (no están en la BD por ese nombre): ' . count($sinMatch)
                . ' — ej: ' . implode(' | ', array_slice($sinMatch, 0, 8)) . ' …');
        }

        if ($dry) { $this->comment('DRY-RUN: no se escribió nada.'); return self::SUCCESS; }
        if (empty($updates)) { $this->warn('Nada que actualizar.'); return self::SUCCESS; }

        $ahora = now();
        DB::transaction(function () use ($updates, $ahora) {
            foreach ($updates as $puId => $precio) {
                DB::table('producto_unidades')->where('id', $puId)
                    ->update(['precio_venta' => $precio, 'updated_at' => $ahora]);
            }
        });
        $this->info('Precios de venta actualizados. ✔');

        return self::SUCCESS;
    }
}
