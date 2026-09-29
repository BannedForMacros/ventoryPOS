<?php

namespace App\Http\Controllers\Turnos;

use App\Http\Controllers\Controller;
use App\Models\Turno;
use App\Services\PlanillaCajaService;
use App\Support\Xlsx;
use Illuminate\Http\Request;

/**
 * Reporte de caja del turno (función opcional: empresas.usa_planilla_caja).
 * Es un reporte para DESCARGAR (Excel) o IMPRIMIR / guardar en PDF; en el
 * detalle del turno solo están los botones. La columna "Revisado" sale como
 * casilla vacía para que el gerente revise venta por venta en el papel.
 */
class TurnoPlanillaController extends Controller
{
    public function __construct(private PlanillaCajaService $planilla) {}

    public function excel(Request $request, Turno $turno)
    {
        $this->autorizar($request, $turno);
        $p = $this->planilla->deTurno($turno);

        $cols    = $p['columnas'];
        $headers = ['Sección', 'Vale', 'Cliente / concepto', ...array_column($cols, 'nombre'), 'Revisado'];
        $filas   = [];
        foreach ($p['secciones'] as $s) {
            foreach ($s['filas'] as $f) {
                $anulada = $f['anulada'] ?? false;
                $filas[] = [
                    $s['titulo'],
                    $f['vale'] . ($anulada ? ' (ANULADA)' : ''),
                    $f['cliente'] ?? '',
                    ...array_map(fn ($c) => $anulada ? 0 : ($f['montos'][$c['clave']] ?? null), $cols),
                    isset($f['venta_id']) ? '☐' : '',   // casilla para marcar a mano
                ];
            }
        }
        $filas[] = ['TOTALES', '', '', ...array_map(fn ($c) => $p['totales'][$c['clave']] ?? 0, $cols), ''];

        $numCols = [];
        foreach (array_keys($cols) as $i) {
            $numCols[3 + $i] = true;
        }

        return Xlsx::descargar($headers, $filas, "planilla-caja-turno-{$turno->id}", $numCols, 'Planilla de caja');
    }

    public function imprimir(Request $request, Turno $turno)
    {
        $this->autorizar($request, $turno);
        $turno->load(['caja', 'user', 'local']);

        return view('turnos.planilla-print', [
            'turno'    => $turno,
            'planilla' => $this->planilla->deTurno($turno),
            'empresa'  => $turno->empresa,
            'generado' => now()->format('d/m/Y H:i'),
        ]);
    }

    /** Mismo criterio que el detalle del turno: la dueña del turno o un admin. */
    private function autorizar(Request $request, Turno $turno): void
    {
        $user = $request->user();
        abort_if($turno->empresa_id !== $user->empresa_id, 403);
        abort_if(!$user->rol->es_admin && $turno->user_id !== $user->id, 403);
        $this->exigirFuncion($user);
    }

    private function exigirFuncion($user): void
    {
        abort_unless((bool) ($user->empresa?->usa_planilla_caja ?? false), 403,
            'Esta empresa no tiene activada la planilla de caja.');
    }
}
