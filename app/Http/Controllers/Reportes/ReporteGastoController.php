<?php

namespace App\Http\Controllers\Reportes;

use App\Http\Controllers\Controller;
use App\Models\Gasto;
use App\Models\GastoTipo;
use App\Models\User;
use App\Services\LocalScopeService;
use Carbon\Carbon;
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

        // Base SIN estado de borrado (los KPIs/desgloses van siempre sobre activos)
        $filtros = function () use ($request, $user, $desde, $hasta) {
            return Gasto::deEmpresa($user->empresa_id)
                ->whereBetween('fecha', [$desde, $hasta])
                ->when($request->local_id, fn ($q, $v) => $q->where('local_id', $v))
                ->when($request->tipo_id, fn ($q, $v) => $q->where('gasto_tipo_id', $v))
                ->when($request->concepto_id, fn ($q, $v) => $q->where('gasto_concepto_id', $v))
                ->when($request->user_id, fn ($q, $v) => $q->where('user_id', $v))
                ->when($request->origen === 'turno', fn ($q) => $q->whereNotNull('turno_id'))
                ->when($request->origen === 'admin', fn ($q) => $q->whereNull('turno_id'))
                ->when($user->local_id, fn ($q) => $q->where('local_id', $user->local_id));
        };

        // Listado según filtro de estado (activos | eliminados | todos)
        $estado = in_array($request->estado, ['eliminados', 'todos'], true) ? $request->estado : 'activos';
        $lista = $filtros();
        if ($estado === 'eliminados') $lista->onlyTrashed();
        if ($estado === 'todos')      $lista->withTrashed();

        $gastos = $lista
            ->with([
                'tipo:id,nombre,categoria',
                'concepto:id,nombre,gasto_tipo_id',
                'user:id,name',
                'local:id,nombre',
                'cuenta:id,nombre',
                'turno:id,fecha_apertura',
            ])
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        // KPIs y desgloses: SOLO gastos activos
        $activos = $filtros();

        $dias = max(1, Carbon::parse($desde)->diffInDays(Carbon::parse($hasta)) + 1);
        $totalActivo = (float) (clone $activos)->sum('monto');

        $kpis = [
            'total'           => $totalActivo,
            'count'           => (int)   (clone $activos)->count(),
            'admin'           => (float) (clone $activos)->whereNull('turno_id')->sum('monto'),
            'turno'           => (float) (clone $activos)->whereNotNull('turno_id')->sum('monto'),
            'promedio_diario' => round($totalActivo / $dias, 2),
            'eliminados'      => (int)   $filtros()->onlyTrashed()->count(),
            'monto_eliminado' => (float) $filtros()->onlyTrashed()->sum('monto'),
        ];

        // Serie diaria
        $serieDiaria = (clone $activos)
            ->select(DB::raw('fecha as dia'), DB::raw('SUM(monto) as total'), DB::raw('COUNT(*) as gastos'))
            ->groupBy('dia')->orderBy('dia')->get()
            ->map(fn ($r) => [
                'dia'    => $r->dia instanceof \Carbon\CarbonInterface ? $r->dia->toDateString() : (string) $r->dia,
                'total'  => (float) $r->total,
                'gastos' => (int) $r->gastos,
            ]);

        // Distribución por tipo (dona)
        $porTipo = (clone $activos)
            ->select('gasto_tipo_id', DB::raw('SUM(monto) as total'), DB::raw('COUNT(*) as count'))
            ->groupBy('gasto_tipo_id')
            ->with('tipo:id,nombre,categoria')
            ->orderByDesc('total')->get()
            ->map(fn ($r) => [
                'gasto_tipo_id' => $r->gasto_tipo_id,
                'nombre'        => $r->tipo?->nombre ?? '—',
                'categoria'     => $r->tipo?->categoria ?? '',
                'total'         => (float) $r->total,
                'count'         => (int)   $r->count,
            ]);

        // Top conceptos
        $porConcepto = (clone $activos)
            ->select('gasto_concepto_id', DB::raw('SUM(monto) as total'), DB::raw('COUNT(*) as count'))
            ->groupBy('gasto_concepto_id')
            ->with('concepto:id,nombre,gasto_tipo_id', 'concepto.tipo:id,nombre')
            ->orderByDesc('total')->limit(10)->get()
            ->map(fn ($r) => [
                'nombre' => $r->concepto?->nombre ?? '—',
                'tipo'   => $r->concepto?->tipo?->nombre ?? '',
                'total'  => (float) $r->total,
                'count'  => (int)   $r->count,
            ]);

        // Por usuario
        $porUsuario = (clone $activos)
            ->select('user_id', DB::raw('SUM(monto) as total'), DB::raw('COUNT(*) as count'))
            ->groupBy('user_id')
            ->with('user:id,name')
            ->orderByDesc('total')->get()
            ->map(fn ($r) => [
                'nombre' => $r->user?->name ?? '—',
                'total'  => (float) $r->total,
                'count'  => (int)   $r->count,
            ]);

        $tipos = GastoTipo::where('empresa_id', $user->empresa_id)
            ->where('activo', true)
            ->with(['conceptos' => fn ($q) => $q->where('activo', true)->orderBy('nombre')])
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'categoria']);

        $locales  = $this->scope->localesVisibles($user);
        $usuarios = User::where('empresa_id', $user->empresa_id)->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Reportes/Gastos', [
            'gastos'       => $gastos,
            'kpis'         => $kpis,
            'serie_diaria' => $serieDiaria,
            'por_tipo'     => $porTipo,
            'por_concepto' => $porConcepto,
            'por_usuario'  => $porUsuario,
            'tipos'        => $tipos,
            'locales'      => $locales,
            'usuarios'     => $usuarios,
            'filters'      => [
                'fecha_desde' => $desde,
                'fecha_hasta' => $hasta,
                'tipo_id'     => $request->tipo_id,
                'concepto_id' => $request->concepto_id,
                'local_id'    => $request->local_id,
                'user_id'     => $request->user_id,
                'origen'      => $request->origen,
                'estado'      => $estado === 'activos' ? null : $estado,
            ],
        ]);
    }
}
