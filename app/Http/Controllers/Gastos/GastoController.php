<?php

namespace App\Http\Controllers\Gastos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gastos\StoreGastoRequest;
use App\Models\Gasto;
use App\Models\GastoTipo;
use App\Models\Local;
use App\Models\Turno;
use App\Services\LocalScopeService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class GastoController extends Controller
{
    public function __construct(private LocalScopeService $scope) {}

    public function index(Request $request)
    {
        $user  = $request->user();
        $scope = $request->input('scope', 'turno'); // 'turno' | 'administrativo'

        $query = Gasto::deEmpresa($user->empresa_id)
            ->with(['tipo', 'concepto', 'user', 'local', 'turno'])
            ->when($request->input('tipo_id'), fn($q, $v) => $q->where('gasto_tipo_id', $v))
            ->when($request->input('concepto_id'), fn($q, $v) => $q->where('gasto_concepto_id', $v))
            ->when($request->input('fecha_desde'), fn($q, $v) => $q->where('fecha', '>=', $v))
            ->when($request->input('fecha_hasta'), fn($q, $v) => $q->where('fecha', '<=', $v));

        if ($scope === 'turno') {
            if (!$user->rol->es_admin) {
                $turnoIds = Turno::where('user_id', $user->id)->pluck('id');
                $query->whereIn('turno_id', $turnoIds);
            }
            $query->whereNotNull('turno_id');
        } else {
            $query->whereNull('turno_id');
            if ($user->local_id) {
                $query->where('local_id', $user->local_id);
            } elseif ($request->input('local_id')) {
                $query->where('local_id', $request->input('local_id'));
            }
        }

        $gastos = $query->orderByDesc('fecha')->orderByDesc('id')->paginate(25);

        $tipos = GastoTipo::deEmpresa($user->empresa_id)->activo()
            ->with(['conceptos' => fn($q) => $q->activo()->orderBy('nombre')])
            ->orderBy('nombre')->get();

        $locales = $this->scope->localesVisibles($user);

        return Inertia::render('Gastos/Index', [
            'gastos'  => $gastos,
            'tipos'   => $tipos,
            'scope'   => $scope,
            'locales' => $locales,
        ]);
    }

    public function store(StoreGastoRequest $request)
    {
        $user = $request->user();

        // Solo admins pueden crear gastos administrativos
        if (!$request->input('turno_id') && !$user->rol->es_admin) {
            abort(403, 'Solo administradores pueden registrar gastos administrativos.');
        }

        Gasto::create([
            'empresa_id'        => $user->empresa_id,
            'local_id'          => $user->local_id ?? $request->input('local_id'),
            'user_id'           => $user->id,
            'turno_id'          => $request->input('turno_id'),
            'gasto_tipo_id'     => $request->input('gasto_tipo_id'),
            'gasto_concepto_id' => $request->input('gasto_concepto_id'),
            'monto'             => $request->input('monto'),
            'fecha'             => $request->input('fecha'),
            'comentario'        => $request->input('comentario'),
        ]);

        return redirect()->back()->with('success', 'Gasto registrado correctamente.');
    }

    public function destroy(Request $request, Gasto $gasto)
    {
        abort_if($gasto->empresa_id !== $request->user()->empresa_id, 403);

        if ($gasto->esDelTurno()) {
            $turno = $gasto->turno;
            if (!$turno || $turno->estado !== 'abierto') {
                return back()->withErrors(['gasto' => 'Solo se pueden eliminar gastos de un turno abierto.']);
            }
        } else {
            abort_if(!$request->user()->rol->es_admin, 403);
        }

        $gasto->delete();

        return redirect()->back()->with('success', 'Gasto eliminado correctamente.');
    }
}
