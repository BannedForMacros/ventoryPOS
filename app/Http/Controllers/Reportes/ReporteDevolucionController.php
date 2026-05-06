<?php

namespace App\Http\Controllers\Reportes;

use App\Http\Controllers\Controller;
use App\Models\Devolucion;
use App\Models\DevolucionMotivo;
use App\Services\LocalScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ReporteDevolucionController extends Controller
{
    public function __construct(private LocalScopeService $scope) {}

    public function index(Request $request)
    {
        $user = $request->user();

        $desde = $request->fecha_desde ?: now()->startOfMonth()->toDateString();
        $hasta = $request->fecha_hasta ?: now()->toDateString();

        $base = Devolucion::deEmpresa($user->empresa_id)
            ->whereBetween('fecha', [$desde . ' 00:00:00', $hasta . ' 23:59:59'])
            ->when($request->estado, fn ($q, $v) => $q->where('estado', $v))
            ->when($request->motivo_id, fn ($q, $v) => $q->where('motivo_id', $v))
            ->when($request->local_id, fn ($q, $v) => $q->where('local_id', $v))
            ->when($request->user_id, fn ($q, $v) => $q->where('user_id', $v))
            ->when($user->local_id, fn ($q) => $q->where('local_id', $user->local_id));

        $devoluciones = (clone $base)
            ->with([
                'user:id,name',
                'userAprobacion:id,name',
                'motivo:id,nombre',
                'local:id,nombre',
                'venta:id,numero,fecha_venta,total',
            ])
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $kpis = [
            'total_devoluciones' => (int)   (clone $base)->count(),
            'monto_devuelto'     => (float) (clone $base)->whereIn('estado', ['aprobada', 'completada'])->sum('monto_devolucion'),
            'monto_reembolsado'  => (float) (clone $base)->whereIn('estado', ['aprobada', 'completada'])->sum('monto_reembolso'),
            'pendientes'         => (int)   (clone $base)->where('estado', 'pendiente')->count(),
            'completadas'        => (int)   (clone $base)->where('estado', 'completada')->count(),
            'rechazadas'         => (int)   (clone $base)->where('estado', 'rechazada')->count(),
            'anuladas'           => (int)   (clone $base)->where('estado', 'anulada')->count(),
        ];

        // Top motivos
        $porMotivo = (clone $base)
            ->select('motivo_id', DB::raw('COUNT(*) as count'), DB::raw('SUM(monto_devolucion) as total'))
            ->groupBy('motivo_id')
            ->with('motivo:id,nombre')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($r) => [
                'motivo_id' => $r->motivo_id,
                'nombre'    => $r->motivo?->nombre ?? '—',
                'count'     => (int)   $r->count,
                'total'     => (float) $r->total,
            ]);

        $motivos = DevolucionMotivo::deEmpresa($user->empresa_id)
            ->activo()
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        $locales  = $this->scope->localesVisibles($user);
        $usuarios = \App\Models\User::where('empresa_id', $user->empresa_id)->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Reportes/Devoluciones', [
            'devoluciones' => $devoluciones,
            'kpis'         => $kpis,
            'por_motivo'   => $porMotivo,
            'motivos'      => $motivos,
            'locales'      => $locales,
            'usuarios'     => $usuarios,
            'filters'      => [
                'fecha_desde' => $desde,
                'fecha_hasta' => $hasta,
                'estado'      => $request->estado,
                'motivo_id'   => $request->motivo_id,
                'local_id'    => $request->local_id,
                'user_id'     => $request->user_id,
            ],
        ]);
    }
}
