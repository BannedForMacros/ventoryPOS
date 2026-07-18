<?php

namespace App\Console\Commands;

use App\Models\BalanceDiario;
use App\Services\BalanceDiarioService;
use Illuminate\Console\Command;

/**
 * Regenera TODOS los balances (borradores y confirmados) a la estructura
 * actual, sin tener que reabrirlos uno por uno. Útil tras un cambio de modelo
 * (p. ej. F11: a favor neto por entidad, en contra solo deudas, utilidad
 * operativa). El patrimonio (balance_neto) no cambia: solo se limpian las
 * líneas y se completan ventas/costo/utilidad del día.
 *
 * Recomendado correr ANTES: `kardex:reconstruir` (para que el stock del balance
 * quede al día) y luego `balance:regenerar`.
 */
class BalanceRegenerar extends Command
{
    protected $signature = 'balance:regenerar {--empresa= : Limitar a una empresa}';

    protected $description = 'Regenera todos los balances (incluso confirmados) a la estructura actual, sin reabrir.';

    public function handle(BalanceDiarioService $svc): int
    {
        $q = BalanceDiario::query()->orderBy('empresa_id')->orderBy('fecha');
        if ($this->option('empresa')) {
            $q->where('empresa_id', (int) $this->option('empresa'));
        }

        $balances = $q->get();
        if ($balances->isEmpty()) {
            $this->warn('No hay balances para regenerar.');
            return self::SUCCESS;
        }

        $this->info("Regenerando {$balances->count()} balance(s)...");
        $bar = $this->output->createProgressBar($balances->count());
        $bar->start();

        foreach ($balances as $b) {
            $svc->regenerarForzado($b);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info('Listo. Todos los balances quedaron en la estructura actual.');

        return self::SUCCESS;
    }
}
