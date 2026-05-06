<?php

namespace App\Http\Controllers\Reportes;

use App\Http\Controllers\Controller;
use App\Models\Gasto;
use App\Models\GastoTipo;
use App\Models\User;
use App\Services\LocalScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ReporteGastoController extends Controller
{
    public function __construct(private LocalScopeService $scope) {}

    public function index(Request $request)
    {
        $user = $request->user();

        $desde = $request->fecha_desde ?: now()->startOfMonth()->toDateString();
        $hasta = $request->fecha_hasta ?: now()->toDateString();

        $base = Gasto::deEmpresa($user->empresa_id)
            ->whereBetween('fecha', [$desde, $hasta])
            ->when($request->local_id, fn ($q, $v) => $q->where('local_id', $v))
            ->when($request->tipo_id, fn ($q, $v) => $q->where('gasto_tipo_id', $v))
            ->when($request->concepto_id, fn ($q, $v) => $q->where('gasto_concepto_id', $v))
            ->when($request->user_id, fn ($q, $v) => $q->where('user_id', $v))
            ->when($request->origen === 'turno', fn ($q) => $q->whereNotNull('turno_id'))
            ->when($request->origen === 'admin', fn ($q) => $q->whereNull('turno_id'))
            ->when($user->local_id, fn ($q) => $q->where('local_id', $user->local_id));

        $gastos = (clone $base)
            ->with([
                'tipo:id,nombre,categoria',
                'concepto:id,nombre,gasto_tipo_id',
                'user:id,name',
                'local:id,nombre',
            ])
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $kpis = [
            'total'        => (float) (clone $base)->sum('monto'),
            'count'        => (int)   (clone $base)->count(),
            'admin'        => (float) (clone $base)->whereNull('turno_id')->sum('monto'),
            'turno'        => (float) (clone $base)->whereNotNull('turno_id')->sum('monto'),
        ];

        // Distribución por tipo
        $porTipo = (clone $base)
            ->select('gasto_tipo_id', DB::raw('SUM(monto) as total'), DB::raw('COUNT(*) as count'))
            ->groupBy('gasto_tipo_id')
            ->with('tipo:id,nombre,categoria')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => [
                'gasto_tipo_id' => $r->gasto_tipo_id,
                'nombre'        => $r->tipo?->nombre ?? '—',
                'categoria'     => $r->tipo?->categoria ?? '',
                'total'         => (float) $r->total,
                'count'         => (int)   $r->count,
            ]);

        $tipos = GastoTipo::where('empresa_id', $user->empresa_id)
            ->where('activo', true)
            ->with(['conceptos' => fn ($q) => $q->where('activo', true)->orderBy('nombre')])
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'categoria']);

        $locales  = $this->scope->localesVisibles($user);
        $usuarios = User::where('empresa_id', $user->empresa_id)->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Reportes/Gastos', [
            'gastos'    => $gastos,
            'kpis'      => $kpis,
            'por_tipo'  => $porTipo,
            'tipos'     => $tipos,
            'locales'   => $locales,
            'usuarios'  => $usuarios,
            'filters'   => [
                'fecha_desde'  => $desde,
                'fecha_hasta'  => $hasta,
                'tipo_id'      => $request->tipo_id,
                'concepto_id'  => $request->concepto_id,
                'local_id'     => $request->local_id,
                'user_id'      => $request->user_id,
                'origen'       => $request->origen,
            ],
        ]);
    }
}
