<?php

namespace App\Console\Commands;

use App\Models\BalanceDiario;
use App\Services\BalanceVerificacionService;
use Illuminate\Console\Command;

/**
 * Lista los balances confirmados cuyas líneas ya no coinciden con los
 * documentos (días sucios). SOLO LEE: no regenera ni escribe nada.
 *
 *   php artisan balance:verificar --empresa=1097
 *   php artisan balance:verificar --empresa=1097 --desde=2026-09-01 --detalle
 */
class BalanceVerificar extends Command
{
    protected $signature = 'balance:verificar
        {--empresa= : Empresa (obligatoria)}
        {--desde= : Fecha inicial (Y-m-d)}
        {--hasta= : Fecha final (Y-m-d)}
        {--detalle : Mostrar las líneas que cambiaron en cada día}';

    protected $description = 'Detecta días cerrados del balance que cambiaron después del cierre (solo lectura).';

    public function handle(BalanceVerificacionService $verificador): int
    {
        if (!$this->option('empresa')) {
            $this->error('Indica --empresa=ID.');

            return self::FAILURE;
        }

        $balances = BalanceDiario::deEmpresa((int) $this->option('empresa'))
            ->confirmado()
            ->when($this->option('desde'), fn ($q, $d) => $q->where('fecha', '>=', $d))
            ->when($this->option('hasta'), fn ($q, $h) => $q->where('fecha', '<=', $h))
            ->orderBy('fecha')
            ->get();

        $filas = [];
        $sucios = 0;
        $balances = $balances->filter(fn ($b) => $verificador->verificable($b))->values();

        foreach ($balances as $b) {
            $r = $verificador->verificar($b);
            if (!$r['sucio']) continue;
            $sucios++;

            $filas[] = [
                $r['fecha'],
                number_format($r['patrimonio_guardado'], 2),
                number_format($r['patrimonio_actual'], 2),
                ($r['diferencia_patrimonio'] >= 0 ? '+' : '') . number_format($r['diferencia_patrimonio'], 2),
                collect($r['categorias'])->take(3)
                    ->map(fn ($c) => $c['categoria'] . ' ' . ($c['efecto'] >= 0 ? '+' : '') . number_format($c['efecto'], 0))
                    ->implode(', '),
            ];

            if ($this->option('detalle')) {
                foreach ($r['lineas'] as $l) {
                    $this->line(sprintf('    %s  %-18s %-40s %12s → %12s (%s)', $r['fecha'], $l['categoria'],
                        mb_strimwidth($l['descripcion'], 0, 40, '…'),
                        number_format($l['guardado'], 2), number_format($l['actual'], 2),
                        ($l['diferencia'] >= 0 ? '+' : '') . number_format($l['diferencia'], 2)));
                }
                foreach ($r['metricas'] as $k => $m) {
                    $this->line(sprintf('    %s  %-18s %12s → %12s', $r['fecha'], $k,
                        number_format($m['guardado'], 2), number_format($m['actual'], 2)));
                }
            }
        }

        $this->info("Balances confirmados revisados: {$balances->count()} · con cambios después del cierre: {$sucios}");
        if ($filas) {
            $this->table(['Fecha', 'Patrimonio al cerrar', 'Patrimonio hoy', 'Diferencia', 'Principales líneas'], $filas);
        }

        return self::SUCCESS;
    }
}
