<?php

namespace App\Console\Commands;

use App\Models\Deuda;
use Illuminate\Console\Command;

class RecalcularSaldosDeudas extends Command
{
    protected $signature = 'deudas:recalcular-saldos {--dry-run : Muestra cambios sin aplicarlos}';

    protected $description = 'Recalcula los saldos de todas las deudas a partir de sus movimientos activos.';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $deudas = Deuda::where('estado', '!=', 'anulada')->get();

        $this->info("Revisando {$deudas->count()} deudas...");

        foreach ($deudas as $deuda) {
            $saldoAnterior = (float) $deuda->saldo;

            if (! $dryRun) {
                $deuda->recalcularSaldo();
            } else {
                // Simular el recálculo sin persistir.
                $incrementos = (float) $deuda->pagos()->where('tipo', 'incremento')->sum('monto');
                $amortizaciones = (float) $deuda->pagos()->where('tipo', 'amortizacion')->sum('monto');
                $deuda->saldo = max(0, (float) $deuda->monto_original + $incrementos - $amortizaciones);
            }

            $nuevo = round((float) $deuda->saldo, 2);

            if (round($saldoAnterior, 2) !== $nuevo) {
                $this->line("[FIX] Deuda #{$deuda->id} — {$deuda->nombre}: S/ {$saldoAnterior} → S/ {$nuevo}");
            }
        }

        $this->info($dryRun ? 'Simulación completada.' : 'Recálculo completado.');

        return self::SUCCESS;
    }
}
