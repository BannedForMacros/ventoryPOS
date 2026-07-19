<?php

namespace App\Http\Controllers\Reportes;

use App\Http\Controllers\Controller;
use App\Models\Devolucion;
use App\Models\DevolucionMotivo;
use App\Models\Venta;
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
            ->when($request->forma, fn ($q, $v) => $q->where('forma_reembolso', $v))
            ->when($request->local_id, fn ($q, $v) => $q->where('local_id', $v))
            ->when($request->user_id, fn ($q, $v) => $q->where('user_id', $v))
            ->when($user->local_id, fn ($q) => $q->where('local_id', $user->local_id));

        $kpis = [
            'total_devoluciones' => (int)   (clone $base)->count(),
            'monto_devuelto'     => (float) (clone $base)->whereIn('estado', ['aprobada', 'completada'])->sum('monto_devolucion'),
            'monto_reembolsado'  => (float) (clone $base)->whereIn('estado', ['aprobada', 'completada'])->sum('monto_reembolso'),
            'pendientes'         => (int)   (clone $base)->where('estado', 'pendiente')->count(),
            'completadas'        => (int)   (clone $base)->where('estado', 'completada')->count(),
            'rechazadas'         => (int)   (clone $base)->where('estado', 'rechazada')->count(),
            'anuladas'           => (int)   (clone $base)->where('estado', 'anulada')->count(),
        ];

        // Tasa de devolución: monto devuelto vs ventas completadas del rango
        $ventasRango = (float) Venta::deEmpresa($user->empresa_id)
            ->where('estado', 'completada')
            ->whereBetween('fecha_venta', [$desde . ' 00:00:00', $hasta . ' 23:59:59'])
            ->when($request->local_id, fn ($q, $v) => $q->where('local_id', $v))
            ->when($user->local_id, fn ($q) => $q->where('local_id', $user->local_id))
            ->sum('total');
        $kpis['ventas_rango'] = $ventasRango;
        $kpis['tasa']         = $ventasRango > 0 ? round(($kpis['monto_devuelto'] / $ventasRango) * 100, 2) : null;

        // Serie diaria
        $serieDiaria = (clone $base)
            ->select(
                DB::raw('DATE(fecha) as dia'),
                DB::raw('COUNT(*) as devoluciones'),
                DB::raw('SUM(monto_devolucion) as monto'),
            )
            ->groupBy('dia')->orderBy('dia')->get()
            ->map(fn ($r) => [
                'dia'          => $r->dia,
                'devoluciones' => (int)   $r->devoluciones,
                'monto'        => (float) $r->monto,
            ]);

        // Por motivo
        $porMotivo = (clone $base)
            ->select('motivo_id', DB::raw('COUNT(*) as count'), DB::raw('SUM(monto_devolucion) as total'))
            ->groupBy('motivo_id')
            ->with('motivo:id,nombre')
            ->orderByDesc('total')->get()
            ->map(fn ($r) => [
                'motivo_id' => $r->motivo_id,
                'nombre'    => $r->motivo?->nombre ?? '—',
                'count'     => (int)   $r->count,
                'total'     => (float) $r->total,
            ]);

        // Por estado
        $porEstado = (clone $base)
            ->select('estado', DB::raw('COUNT(*) as count'), DB::raw('SUM(monto_devolucion) as total'))
            ->groupBy('estado')->orderByDesc('count')->get()
            ->map(fn ($r) => [
                'estado' => $r->estado,
                'count'  => (int)   $r->count,
                'total'  => (float) $r->total,
            ]);

        // Por forma de reembolso
        $porForma = (clone $base)
            ->select('forma_reembolso', DB::raw('COUNT(*) as count'), DB::raw('SUM(monto_reembolso) as total'))
            ->groupBy('forma_reembolso')->orderByDesc('count')->get()
            ->map(fn ($r) => [
                'forma' => $r->forma_reembolso,
                'count' => (int)   $r->count,
                'total' => (float) $r->total,
            ]);

        $devoluciones = (clone $base)
            ->with([
                'user:id,name',
                'userAprobacion:id,name',
                'motivo:id,nombre',
                'local:id,nombre',
                'venta:id,numero,cliente_id,total,fecha_venta',
                'venta.cliente:id,nombres,apellidos,razon_social',
                'detalles',
                'detalles.producto:id,nombre',
                'detalles.ventaItem:id,producto_nombre,unidad_nombre',
                'pagos.metodoPago:id,nombre',
            ])
            ->orderByDesc('fecha')->orderByDesc('id')
            ->paginate(25)->withQueryString();

        $motivos = DevolucionMotivo::deEmpresa($user->empresa_id)
            ->activo()
            ->orderBy('orden')->orderBy('nombre')
            ->get(['id', 'nombre']);

        $locales  = $this->scope->localesVisibles($user);
        $usuarios = \App\Models\User::where('empresa_id', $user->empresa_id)->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Reportes/Devoluciones', [
            'devoluciones' => $devoluciones,
            'kpis'         => $kpis,
            'serie_diaria' => $serieDiaria,
            'por_motivo'   => $porMotivo,
            'por_estado'   => $porEstado,
            'por_forma'    => $porForma,
            'motivos'      => $motivos,
            'locales'      => $locales,
            'usuarios'     => $usuarios,
            'filters'      => [
                'fecha_desde' => $desde,
                'fecha_hasta' => $hasta,
                'estado'      => $request->estado,
                'motivo_id'   => $request->motivo_id,
                'forma'       => $request->forma,
                'local_id'    => $request->local_id,
                'user_id'     => $request->user_id,
            ],
        ]);
    }
}
