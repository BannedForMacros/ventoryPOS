<?php

namespace App\Http\Controllers\Finanzas;

use App\Http\Controllers\Controller;
use App\Models\PlanillaDescuento;
use App\Models\Turno;
use App\Models\TurnoConsolidacion;
use App\Services\AuditoriaService;
use App\Services\TesoreriaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * F8 — Consolidación de caja: el supervisor cuenta el efectivo de cada
 * turno cerrado (viendo lo que declaró la cajera) y SU conteo es el que
 * asienta el sobrante/faltante en tesorería. El balance diario, que lee
 * tesorería, termina reflejando el monto consolidado — con trazabilidad
 * completa de quién contó qué y cuánto faltó.
 */
class ConsolidacionController extends Controller
{
    public function __construct(private TesoreriaService $tesoreria) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $empresa = $user->empresa;

        $estado = $request->input('estado', 'pendientes'); // pendientes | consolidados

        $query = Turno::deEmpresa($user->empresa_id)
            ->cerrado()
            ->with(['caja', 'user', 'userCierre', 'consolidacion.user', 'arqueoMetodos.metodoPago'])
            ->when($request->input('fecha_desde'), fn ($q, $v) => $q->whereDate('fecha_cierre', '>=', $v))
            ->when($request->input('fecha_hasta'), fn ($q, $v) => $q->whereDate('fecha_cierre', '<=', $v));

        if ($estado === 'pendientes') {
            $query->whereDoesntHave('consolidacion');
        } else {
            $query->whereHas('consolidacion');
        }

        $turnos = $query->orderByDesc('fecha_cierre')->paginate(20)->withQueryString();

        return Inertia::render('Finanzas/Consolidacion', [
            'turnos'               => $turnos,
            'estado'               => $estado,
            'requiereConsolidacion'=> (bool) $empresa->requiere_consolidacion_caja,
        ]);
    }

    /**
     * Registra el conteo del consolidador para un turno cerrado.
     */
    public function consolidar(Request $request, Turno $turno)
    {
        $user = $request->user();
        abort_if($turno->empresa_id !== $user->empresa_id, 403);
        abort_unless($turno->estado === 'cerrado', 422, 'Solo se consolidan turnos cerrados.');
        abort_if($turno->consolidacion()->exists(), 422, 'Este turno ya fue consolidado.');

        $data = $request->validate([
            'efectivo_contado'  => ['required', 'numeric', 'min:0'],
            'observacion'       => ['nullable', 'string', 'max:500'],
            'generar_descuento' => ['nullable', 'boolean'],
        ]);

        DB::transaction(function () use ($turno, $user, $data) {
            $declarado = $turno->monto_cierre_declarado !== null ? (float) $turno->monto_cierre_declarado : null;
            $esperado  = $turno->monto_cierre_esperado !== null ? (float) $turno->monto_cierre_esperado : null;
            $contado   = round((float) $data['efectivo_contado'], 2);

            $consolidacion = TurnoConsolidacion::create([
                'turno_id'                => $turno->id,
                'empresa_id'              => $turno->empresa_id,
                'user_id'                 => $user->id,
                'fecha'                   => now()->toDateString(),
                'efectivo_declarado'      => $declarado,
                'efectivo_esperado'       => $esperado,
                'caja_chica'              => (float) $turno->monto_caja_chica,
                'efectivo_contado'        => $contado,
                'diferencia_vs_declarado' => $declarado !== null ? round($contado - $declarado, 2) : null,
                'diferencia_vs_esperado'  => $esperado !== null ? round($contado - $esperado, 2) : null,
                'observacion'             => $data['observacion'] ?? null,
            ]);

            // El conteo del consolidador MANDA: si el cierre ya había asentado
            // el sobrante/faltante de la cajera (config antes apagada, o
            // cambió), se revierte para no duplicar.
            $this->tesoreria->revertir('cierre_turno', $turno->id);

            $difReal = $esperado !== null ? round($contado - $esperado, 2) : 0.0;
            if (abs($difReal) >= 0.01) {
                $this->tesoreria->registrar(
                    $turno->empresa_id,
                    null, // efectivo
                    $user,
                    now()->toDateString(),
                    $difReal > 0 ? 'ingreso' : 'egreso',
                    abs($difReal),
                    ($difReal > 0 ? 'Sobrante' : 'Faltante')
                        . " consolidado — turno #{$turno->id} (cajera: {$turno->user?->name})",
                    'turno_consolidacion',
                    $consolidacion->id,
                );
            }

            // Faltante → descuento de planilla opcional a la cajera del turno.
            if (!empty($data['generar_descuento']) && $difReal < -0.01) {
                PlanillaDescuento::create([
                    'empresa_id'     => $turno->empresa_id,
                    'user_id'        => $turno->user_id, // la cajera dueña de la caja
                    'registrado_por' => $user->id,
                    'fecha'          => now()->toDateString(),
                    'monto'          => abs($difReal),
                    'motivo'         => "Faltante de caja — turno #{$turno->id} del " . $turno->fecha_cierre?->format('d/m/Y'),
                    'ref_tipo'       => 'turno_consolidacion',
                    'ref_id'         => $consolidacion->id,
                    'estado'         => 'pendiente',
                ]);
            }

            AuditoriaService::log('caja.consolidada', $consolidacion, [
                'turno_id'      => $turno->id,
                'cajera'        => $turno->user?->name,
                'declarado'     => $declarado,
                'esperado'      => $esperado,
                'contado'       => $contado,
                'dif_declarado' => $consolidacion->diferencia_vs_declarado,
                'dif_esperado'  => $consolidacion->diferencia_vs_esperado,
                'genero_descuento' => !empty($data['generar_descuento']) && $difReal < -0.01,
            ], $user);
        });

        return back()->with('success', 'Turno consolidado correctamente.');
    }
}
