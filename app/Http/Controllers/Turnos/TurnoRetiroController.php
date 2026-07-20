<?php

namespace App\Http\Controllers\Turnos;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\Turno;
use App\Models\TurnoRetiro;
use App\Services\AuditoriaService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Retiros de efectivo de un turno (sangría / "Entrega a administración").
 * Solo disponibles si la empresa activó `usa_retiros_caja`. No son gastos ni
 * tocan tesorería: el neto de Efectivo no cambia, solo cambia de custodia.
 */
class TurnoRetiroController extends Controller
{
    public function store(Request $request, Turno $turno)
    {
        $user = $request->user();

        abort_if($turno->empresa_id !== $user->empresa_id, 403);
        // La dueña del turno registra sus retiros; el admin puede registrar en cualquiera.
        abort_if($turno->user_id !== $user->id && !$user->rol->es_admin, 403);
        abort_if($turno->estado !== 'abierto', 422, 'El turno ya está cerrado.');

        $empresa = Empresa::find($user->empresa_id);
        abort_unless((bool) ($empresa?->usa_retiros_caja ?? false), 403,
            'La empresa no tiene activados los retiros de caja.');

        $data = $request->validate([
            'monto'       => ['required', 'numeric', 'min:0.01'],
            'concepto'    => ['nullable', 'string', 'max:100'],
            'observacion' => ['nullable', 'string', 'max:500'],
        ]);

        // No se puede retirar más efectivo del que el sistema espera en el cajón
        $disponible = $turno->calcularMontoEsperado();
        if ((float) $data['monto'] > $disponible + 0.001) {
            return back()->withErrors([
                'monto' => 'El retiro (S/ ' . number_format((float) $data['monto'], 2)
                    . ') supera el efectivo esperado en caja (S/ ' . number_format($disponible, 2) . ').',
            ]);
        }

        $esAdmin = (bool) $user->rol->es_admin;
        $requiereAprobacion = (bool) ($empresa->retiro_requiere_aprobacion ?? true);

        $retiro = TurnoRetiro::create([
            'empresa_id'   => $turno->empresa_id,
            'turno_id'     => $turno->id,
            'user_id'      => $user->id,
            'concepto'     => $data['concepto'] ?: TurnoRetiro::CONCEPTO_ENTREGA_ADMIN,
            'monto'        => $data['monto'],
            'momento'      => 'turno',
            // Si lo registra un admin (o la empresa no exige aprobación) nace aprobado
            'estado'       => (!$requiereAprobacion || $esAdmin) ? 'aprobado' : 'registrado',
            'aprobado_por' => (!$requiereAprobacion || $esAdmin) ? $user->id : null,
            'observacion'  => $data['observacion'] ?? null,
        ]);

        AuditoriaService::log('turno.retiro_registrado', $retiro, [
            'turno_id' => $turno->id,
            'caja'     => $turno->caja?->nombre,
            'concepto' => $retiro->concepto,
            'monto'    => (float) $retiro->monto,
            'estado'   => $retiro->estado,
        ], $user);

        return back()->with('success', 'Retiro de efectivo registrado.');
    }

    public function aprobar(Request $request, TurnoRetiro $retiro)
    {
        $user = $request->user();

        abort_if($retiro->empresa_id !== $user->empresa_id, 403);
        abort_unless($user->rol->es_admin, 403, 'Solo un administrador puede aprobar retiros.');
        abort_if($retiro->estado !== 'registrado', 422, 'El retiro ya fue aprobado.');

        $retiro->update([
            'estado'       => 'aprobado',
            'aprobado_por' => $user->id,
        ]);

        AuditoriaService::log('turno.retiro_aprobado', $retiro, [
            'turno_id' => $retiro->turno_id,
            'monto'    => (float) $retiro->monto,
            'registrado_por' => $retiro->user?->name,
        ], $user);

        return back()->with('success', 'Retiro aprobado.');
    }
}
