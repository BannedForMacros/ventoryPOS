<?php

namespace App\Http\Controllers\Reportes;

use App\Http\Controllers\Controller;
use App\Models\Caja;
use App\Models\Turno;
use App\Models\User;
use App\Services\LocalScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ReporteCajaController extends Controller
{
    public function __construct(private LocalScopeService $scope) {}

    public function index(Request $request)
    {
        $user = $request->user();

        $desde = $request->fecha_desde ?: now()->startOfMonth()->toDateString();
        $hasta = $request->fecha_hasta ?: now()->toDateString();

        $base = Turno::deEmpresa($user->empresa_id)
            ->whereBetween('fecha_apertura', [$desde . ' 00:00:00', $hasta . ' 23:59:59'])
            ->when($request->estado, fn ($q, $v) => $q->where('estado', $v))
            ->when($request->local_id, fn ($q, $v) => $q->where('local_id', $v))
            ->when($request->caja_id, fn ($q, $v) => $q->where('caja_id', $v))
            ->when($request->user_id, fn ($q, $v) => $q->where('user_id', $v))
            ->when($user->local_id, fn ($q) => $q->where('local_id', $user->local_id));

        $turnos = (clone $base)
            ->with([
                'user:id,name',
                'userCierre:id,name',
                'caja:id,nombre',
                'local:id,nombre',
            ])
            ->withCount(['ventas as ventas_count' => fn ($q) => $q->where('estado', 'completada')])
            ->withSum(['ventas as ventas_total' => fn ($q) => $q->where('estado', 'completada')], 'total')
            ->withSum('gastos as gastos_total', 'monto')
            ->orderByDesc('fecha_apertura')
            ->paginate(25)
            ->withQueryString()
            ->through(fn ($t) => [
                'id'                     => $t->id,
                'caja'                   => $t->caja,
                'local'                  => $t->local,
                'user'                   => $t->user,
                'user_cierre'            => $t->userCierre,
                'estado'                 => $t->estado,
                'fecha_apertura'         => $t->fecha_apertura,
                'fecha_cierre'           => $t->fecha_cierre,
                'monto_apertura'         => (float) $t->monto_apertura,
                'monto_cierre_declarado' => $t->monto_cierre_declarado !== null ? (float) $t->monto_cierre_declarado : null,
                'monto_cierre_esperado'  => $t->monto_cierre_esperado  !== null ? (float) $t->monto_cierre_esperado  : null,
                'diferencia'             => $t->diferencia !== null ? (float) $t->diferencia : null,
                'ventas_count'           => (int)   $t->ventas_count,
                'ventas_total'           => (float) ($t->ventas_total ?? 0),
                'gastos_total'           => (float) ($t->gastos_total ?? 0),
            ]);

        // KPIs
        $cerrados = (clone $base)->where('estado', 'cerrado');

        $kpis = [
            'total_turnos'    => (int) (clone $base)->count(),
            'turnos_abiertos' => (int) (clone $base)->where('estado', 'abierto')->count(),
            'turnos_cerrados' => (int) (clone $cerrados)->count(),
            'sobrantes'       => (float) (clone $cerrados)->where('diferencia', '>', 0)->sum('diferencia'),
            'faltantes'       => (float) (clone $cerrados)->where('diferencia', '<', 0)->sum(DB::raw('ABS(diferencia)')),
            'diferencia_neta' => (float) (clone $cerrados)->sum('diferencia'),
        ];

        $locales = $this->scope->localesVisibles($user);
        $cajas   = Caja::where('empresa_id', $user->empresa_id)
            ->when($user->local_id, fn ($q) => $q->where('local_id', $user->local_id))
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'local_id']);
        $usuarios = User::where('empresa_id', $user->empresa_id)->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Reportes/Caja', [
            'turnos'    => $turnos,
            'kpis'      => $kpis,
            'locales'   => $locales,
            'cajas'     => $cajas,
            'usuarios'  => $usuarios,
            'filters'   => [
                'fecha_desde' => $desde,
                'fecha_hasta' => $hasta,
                'estado'      => $request->estado,
                'local_id'    => $request->local_id,
                'caja_id'     => $request->caja_id,
                'user_id'     => $request->user_id,
            ],
        ]);
    }
}
