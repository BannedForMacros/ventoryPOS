<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Asigna el correlativo interno E-AAAAMMDD-NNN a las entradas que aún NO lo
 * tienen, por (empresa_id, día de la fecha). Recorre las entradas SIN correlativo
 * ordenadas por fecha ASC y luego id ASC, de forma determinística y repetible.
 *
 * IDEMPOTENTE: solo toca filas con correlativo NULL — si ya lo tienen, no las
 * modifica. Y SOLO escribe la columna `correlativo` (no toca ningún otro dato,
 * ni updated_at, porque usa el query builder directo).
 *
 *   php artisan entradas:correlativos
 *   php artisan entradas:correlativos --empresa=1097
 */
class GenerarCorrelativosEntradas extends Command
{
    protected $signature = 'entradas:correlativos {--empresa= : Limitar a una empresa}';

    protected $description = 'Asigna el correlativo interno E-AAAAMMDD-NNN a las entradas que no lo tengan.';

    public function handle(): int
    {
        $empresaId = $this->option('empresa') ? (int) $this->option('empresa') : null;

        $pendientes = DB::table('entradas')
            ->whereNull('correlativo')
            ->when($empresaId, fn ($q) => $q->where('empresa_id', $empresaId))
            ->orderBy('fecha')
            ->orderBy('id')
            ->get(['id', 'empresa_id', 'fecha']);

        if ($pendientes->isEmpty()) {
            $this->info('No hay entradas sin correlativo en el alcance indicado. Nada que hacer.');
            return self::SUCCESS;
        }

        $this->info("Asignando correlativo a {$pendientes->count()} entrada(s)...");
        $bar = $this->output->createProgressBar($pendientes->count());
        $bar->start();

        // Contador en memoria por "empresaId|AAAAMMDD" → última secuencia usada.
        // Se siembra (lazy, la primera vez que aparece un día) con el MAX ya
        // presente en la BD para esa empresa+día, para respetar correlativos
        // previos y ser seguro ante re-ejecuciones.
        $contadores = [];
        $asignados  = 0;

        foreach ($pendientes as $e) {
            $dia   = str_replace('-', '', substr((string) $e->fecha, 0, 10)); // AAAAMMDD
            $clave = $e->empresa_id . '|' . $dia;

            if (!array_key_exists($clave, $contadores)) {
                $prefijo = "E-{$dia}-";
                $contadores[$clave] = (int) DB::table('entradas')
                    ->where('empresa_id', $e->empresa_id)
                    ->where('correlativo', 'like', $prefijo . '%')
                    ->selectRaw("COALESCE(MAX(CAST(split_part(correlativo, '-', 3) AS INTEGER)), 0) as n")
                    ->value('n');
            }

            $secuencia = ++$contadores[$clave];
            $correlativo = "E-{$dia}-" . str_pad((string) $secuencia, 3, '0', STR_PAD_LEFT);

            DB::table('entradas')->where('id', $e->id)->update(['correlativo' => $correlativo]);
            $asignados++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Listo. Se asignó correlativo a {$asignados} entrada(s).");

        return self::SUCCESS;
    }
}
