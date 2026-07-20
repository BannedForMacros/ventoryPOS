<?php

namespace App\Http\Controllers\Finanzas;

use App\Http\Controllers\Controller;
use App\Models\PlanillaDescuento;
use App\Models\User;
use App\Services\AuditoriaService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * F8 — Descuentos de planilla: cargos al trabajador (faltantes de caja
 * detectados en consolidación, u otros) que se descuentan cuando se paga
 * la planilla. NO mueven tesorería: el pago de planilla en sí es un gasto;
 * el descuento solo reduce cuánto se le paga al trabajador.
 */
class PlanillaDescuentoController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = PlanillaDescuento::deEmpresa($user->empresa_id)
            ->with(['trabajador', 'registradoPor', 'aplicadoPor'])
            ->when($request->input('user_id'), fn ($q, $v) => $q->where('user_id', $v))
            // Búsqueda server-side sobre TODA la base (no solo la página visible).
            ->when($request->input('buscar'), function ($q, $texto) {
                $t = trim($texto);
                $q->where(fn ($sub) => $sub
                    ->where('motivo', 'ilike', "%{$t}%")
                    ->orWhereHas('trabajador', fn ($u) => $u->where('name', 'ilike', "%{$t}%")));
            });

        // Filtro de estado: 'pendientes' (default), 'aplicados', 'anulados' o 'todos'.
        $estado = $request->input('estado', 'pendientes');
        if ($estado === 'pendientes') {
            $query->pendiente();
        } elseif ($estado === 'aplicados') {
            $query->where('estado', 'aplicado');
        } elseif ($estado === 'anulados') {
            $query->where('estado', 'anulado');
        }

        $descuentos = $query->orderByDesc('fecha')->orderByDesc('id')->paginate(25)->withQueryString();

        // Total pendiente por trabajador (para descontar al pagar planilla).
        $porTrabajador = PlanillaDescuento::deEmpresa($user->empresa_id)->pendiente()
            ->selectRaw('user_id, SUM(monto) as total')
            ->groupBy('user_id')
            ->with('trabajador:id,name')
            ->get()
            ->map(fn ($r) => ['user_id' => $r->user_id, 'nombre' => $r->trabajador?->name, 'total' => (float) $r->total]);

        return Inertia::render('Finanzas/DescuentosPlanilla', [
            'descuentos'    => $descuentos,
            'porTrabajador' => $porTrabajador,
            // Acciones visibles según la matriz de permisos del rol.
            'puede'         => [
                'editar'   => $user->tienePermiso('finanzas.planilla-descuentos', 'editar'),
                'eliminar' => $user->tienePermiso('finanzas.planilla-descuentos', 'eliminar'),
            ],
            'estado'        => $request->input('estado', 'pendientes'),
            'buscar'        => $request->input('buscar', ''),
            'trabajadores'  => User::where('empresa_id', $user->empresa_id)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /** Descuento manual (no nacido de una consolidación). */
    public function store(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('empresa_id', $user->empresa_id)],
            'fecha'   => ['required', 'date'],
            'monto'   => ['required', 'numeric', 'min:0.01'],
            'motivo'  => ['required', 'string', 'min:5', 'max:250'],
        ]);

        $descuento = PlanillaDescuento::create($data + [
            'empresa_id'     => $user->empresa_id,
            'registrado_por' => $user->id,
            'estado'         => 'pendiente',
        ]);

        AuditoriaService::log('planilla_descuento.creado', $descuento, [
            'trabajador_id' => $descuento->user_id,
            'monto'         => (float) $descuento->monto,
            'motivo'        => $descuento->motivo,
        ], $user);

        return back()->with('success', 'Descuento registrado.');
    }

    /** Marca el descuento como aplicado (ya se descontó en la planilla pagada). */
    public function aplicar(Request $request, PlanillaDescuento $descuento)
    {
        $user = $request->user();
        abort_if($descuento->empresa_id !== $user->empresa_id, 403);
        abort_unless($descuento->estado === 'pendiente', 422, 'El descuento no está pendiente.');

        $descuento->update([
            'estado'           => 'aplicado',
            'aplicado_por'     => $user->id,
            'fecha_aplicacion' => $request->input('fecha_aplicacion', now()->toDateString()),
        ]);

        AuditoriaService::log('planilla_descuento.aplicado', $descuento, [
            'trabajador_id' => $descuento->user_id,
            'monto'         => (float) $descuento->monto,
        ], $user);

        return back()->with('success', 'Descuento marcado como aplicado en planilla.');
    }

    public function anular(Request $request, PlanillaDescuento $descuento)
    {
        $user = $request->user();
        abort_if($descuento->empresa_id !== $user->empresa_id, 403);
        abort_unless($descuento->estado === 'pendiente', 422, 'Solo se anulan descuentos pendientes.');

        $data = $request->validate(['motivo' => ['required', 'string', 'min:5', 'max:500']]);

        $descuento->update(['estado' => 'anulado', 'observacion' => $data['motivo']]);

        AuditoriaService::log('planilla_descuento.anulado', $descuento, [
            'trabajador_id' => $descuento->user_id,
            'monto'         => (float) $descuento->monto,
            'motivo'        => $data['motivo'],
        ], $user);

        return back()->with('success', 'Descuento anulado.');
    }

    /** Edita un descuento PENDIENTE (monto, motivo, fecha, trabajador). */
    public function update(Request $request, PlanillaDescuento $descuento)
    {
        $user = $request->user();
        abort_if($descuento->empresa_id !== $user->empresa_id, 403);
        abort_unless($descuento->estado === 'pendiente', 422, 'Solo se editan descuentos pendientes (desaplica primero si ya se aplicó).');

        $data = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('empresa_id', $user->empresa_id)],
            'fecha'   => ['required', 'date'],
            'monto'   => ['required', 'numeric', 'min:0.01'],
            'motivo'  => ['required', 'string', 'min:5', 'max:250'],
        ]);

        $antes = ['user_id' => $descuento->user_id, 'monto' => (float) $descuento->monto, 'motivo' => $descuento->motivo];
        $descuento->update($data);

        AuditoriaService::log('planilla_descuento.editado', $descuento, [
            'antes'   => $antes,
            'despues' => ['user_id' => $descuento->user_id, 'monto' => (float) $descuento->monto, 'motivo' => $descuento->motivo],
        ], $user);

        return back()->with('success', 'Descuento actualizado.');
    }

    /**
     * Desaplica un descuento (se marcó como aplicado por error): vuelve a
     * PENDIENTE y sigue sumando en lo por descontar del trabajador.
     */
    public function desaplicar(Request $request, PlanillaDescuento $descuento)
    {
        $user = $request->user();
        abort_if($descuento->empresa_id !== $user->empresa_id, 403);
        abort_unless($descuento->estado === 'aplicado', 422, 'El descuento no está aplicado.');

        $data = $request->validate(['motivo' => ['required', 'string', 'min:5', 'max:500']]);

        $descuento->update([
            'estado'           => 'pendiente',
            'aplicado_por'     => null,
            'fecha_aplicacion' => null,
        ]);

        AuditoriaService::log('planilla_descuento.desaplicado', $descuento, [
            'trabajador_id' => $descuento->user_id,
            'monto'         => (float) $descuento->monto,
            'motivo'        => $data['motivo'],
        ], $user);

        return back()->with('success', 'Descuento desaplicado: vuelve a estar pendiente.');
    }

    /** Reactiva un descuento anulado por error: vuelve a PENDIENTE. */
    public function reactivar(Request $request, PlanillaDescuento $descuento)
    {
        $user = $request->user();
        abort_if($descuento->empresa_id !== $user->empresa_id, 403);
        abort_unless($descuento->estado === 'anulado', 422, 'Solo se reactivan descuentos anulados.');

        $data = $request->validate(['motivo' => ['required', 'string', 'min:5', 'max:500']]);

        $descuento->update(['estado' => 'pendiente']);

        AuditoriaService::log('planilla_descuento.reactivado', $descuento, [
            'trabajador_id' => $descuento->user_id,
            'monto'         => (float) $descuento->monto,
            'motivo'        => $data['motivo'],
        ], $user);

        return back()->with('success', 'Descuento reactivado.');
    }
}
