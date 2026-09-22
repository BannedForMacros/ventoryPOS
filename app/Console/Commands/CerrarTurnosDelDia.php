<?php

namespace App\Console\Commands;

use App\Models\Empresa;
use App\Models\Turno;
use App\Services\AuditoriaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Cierra los turnos que quedaron abiertos de días anteriores, sin declaración.
 *
 * PARA QUÉ: en los negocios que no cuadran caja —una peluquería, una
 * veterinaria, un taller— nadie cierra nada al terminar la jornada: apagan la
 * luz y se van. Sin esto, el turno del lunes seguiría abierto el viernes y las
 * ventas de toda la semana caerían en el mismo turno, con lo que el reporte de
 * caja dejaría de decir nada útil.
 *
 * NO DECLARA NADA, y esa es la diferencia con el cierre de siempre: nadie contó
 * el cajón, así que `monto_cierre_declarado` y `diferencia` quedan en NULL. Se
 * guarda el esperado como dato informativo, pero afirmar que alguien declaró esa
 * cifra sería inventarse un arqueo que no ocurrió.
 *
 * NO TOCA DINERO: ni tesorería, ni retiros, ni caja grande. Solo pasa el turno a
 * cerrado para que el del día siguiente empiece limpio.
 *
 * Es OPT-IN por empresa (`turno_cierre_automatico`) y va aparte de `modo_turno`
 * a propósito: una empresa que abre turnos a mano también puede querer que no se
 * le queden abiertos de un día para otro.
 *
 *   php artisan turnos:cerrar-dia --simular
 *   php artisan turnos:cerrar-dia --empresa=1
 */
class CerrarTurnosDelDia extends Command
{
    protected $signature = 'turnos:cerrar-dia
        {--empresa= : Limitar a una empresa}
        {--simular : No cierra nada; solo lista lo que cerraría}';

    protected $description = 'Cierra sin declaración los turnos abiertos de días anteriores (empresas con cierre automático).';

    public function handle(): int
    {
        $simular = (bool) $this->option('simular');

        $empresas = Empresa::where('turno_cierre_automatico', true)
            ->when($this->option('empresa'), fn ($q, $v) => $q->where('id', (int) $v))
            ->pluck('id');

        if ($empresas->isEmpty()) {
            $this->info('Ninguna empresa tiene el cierre automático activado.');

            return self::SUCCESS;
        }

        // Solo los de días ANTERIORES: el de hoy sigue vivo, que para eso es el
        // turno del día. Correrlo a media mañana no puede cortar a nadie que
        // esté vendiendo.
        $turnos = Turno::whereIn('empresa_id', $empresas)
            ->where('estado', 'abierto')
            ->whereDate('fecha_apertura', '<', now()->toDateString())
            ->orderBy('fecha_apertura')
            ->get();

        if ($turnos->isEmpty()) {
            $this->info('No hay turnos de días anteriores abiertos.');

            return self::SUCCESS;
        }

        $this->info(($simular ? '[SIMULACIÓN] ' : '') . "Turnos a cerrar: {$turnos->count()}");
        $filas = [];

        foreach ($turnos as $turno) {
            $esperado = round($turno->calcularMontoEsperado(), 2);
            $ventas   = $turno->ventas()->where('estado', 'completada')->count();

            $filas[] = [
                $turno->id,
                $turno->empresa_id,
                $turno->fecha_apertura->format('d/m/Y H:i'),
                $ventas,
                number_format($esperado, 2),
            ];

            if ($simular) {
                continue;
            }

            DB::transaction(function () use ($turno, $esperado, $ventas) {
                $turno->update([
                    // Sin `user_cierre_id`: no lo cerró una persona.
                    'monto_cierre_esperado'  => $esperado,
                    // Declarado y diferencia se quedan en NULL A PROPÓSITO:
                    // nadie contó el cajón. Poner el esperado aquí simularía un
                    // arqueo cuadrado que nunca se hizo.
                    'monto_cierre_declarado' => null,
                    'diferencia'             => null,
                    'estado'                 => 'cerrado',
                    'fecha_cierre'           => now(),
                    'observacion_cierre'     => 'Cerrado automáticamente al terminar el día (sin declaración).',
                ]);

                AuditoriaService::logSistema($turno->empresa_id, 'turno.cerrado_automatico', [
                    'turno_id'       => $turno->id,
                    'fecha_apertura' => $turno->fecha_apertura->toDateTimeString(),
                    'ventas'         => $ventas,
                    'monto_esperado' => $esperado,
                    'aviso'          => 'Sin declaración: la empresa no cuadra caja.',
                ], $turno);
            });
        }

        $this->newLine();
        $this->table(['Turno', 'Empresa', 'Abierto', 'Ventas', 'Esperado S/'], $filas);

        if ($simular) {
            $this->warn('Simulación: no se cerró nada.');
        }

        return self::SUCCESS;
    }
}
