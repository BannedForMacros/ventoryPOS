<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Models\DescuentoConcepto;
use App\Models\DescuentoLog;
use App\Models\User;
use App\Models\Venta;
use App\Services\LocalScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DescuentoLogController extends Controller
{
    public function __construct(private LocalScopeService $scope) {}

    public function index(Request $request)
    {
        $user = $request->user();

        $desde = $request->fecha_desde ?: now()->startOfMonth()->toDateString();
        $hasta = $request->fecha_hasta ?: now()->toDateString();

        $base = DescuentoLog::where('empresa_id', $user->empresa_id)
            ->whereBetween('created_at', [$desde . ' 00:00:00', $hasta . ' 23:59:59'])
            ->when($request->concepto_id, fn ($q, $v) => $q->where('descuento_concepto_id', $v))
            ->when($request->user_id, fn ($q, $v) => $q->where('user_id', $v))
            ->when($request->aprobacion === '1', fn ($q) => $q->where('requeria_aprobacion', true))
            ->when($user->local_id, fn ($q) => $q->whereHas('venta', fn ($vq) => $vq->where('local_id', $user->local_id)));

        $logs = (clone $base)
            ->with([
                'user:id,name',
                'cliente:id,nombres,apellidos,razon_social',
                'concepto:id,nombre,requiere_aprobacion',
                'venta:id,numero,total,estado',
                'aprobadoPor:id,name',
            ])
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        // KPIs
        $totalDescontado = (float) (clone $base)->sum('monto_descuento');

        // Ventas completadas del mismo rango/scope, para el % de descuento
        $ventasRango = (float) Venta::deEmpresa($user->empresa_id)
            ->where('estado', 'completada')
            ->whereBetween('fecha_venta', [$desde . ' 00:00:00', $hasta . ' 23:59:59'])
            ->when($user->local_id, fn ($q) => $q->where('local_id', $user->local_id))
            ->sum('total');

        $kpis = [
            'total_descontado'  => $totalDescontado,
            'count'             => (int) (clone $base)->count(),
            'ventas_rango'      => $ventasRango,
            'pct_sobre_ventas'  => $ventasRango > 0
                ? round(($totalDescontado / ($ventasRango + $totalDescontado)) * 100, 2)
                : null,
            'con_aprobacion'       => (int)   (clone $base)->where('requeria_aprobacion', true)->count(),
            'monto_con_aprobacion' => (float) (clone $base)->where('requeria_aprobacion', true)->sum('monto_descuento'),
        ];

        // Serie diaria
        $serieDiaria = (clone $base)
            ->select(DB::raw('DATE(created_at) as dia'), DB::raw('SUM(monto_descuento) as total'), DB::raw('COUNT(*) as descuentos'))
            ->groupBy('dia')->orderBy('dia')->get()
            ->map(fn ($r) => [
                'dia'        => $r->dia,
                'total'      => (float) $r->total,
                'descuentos' => (int) $r->descuentos,
            ]);

        // Dona por concepto
        $porConcepto = (clone $base)
            ->select('descuento_concepto_id', DB::raw('SUM(monto_descuento) as total'), DB::raw('COUNT(*) as count'))
            ->groupBy('descuento_concepto_id')
            ->with('concepto:id,nombre')
            ->orderByDesc('total')->get()
            ->map(fn ($r) => [
                'nombre' => $r->concepto?->nombre ?? '—',
                'total'  => (float) $r->total,
                'count'  => (int)   $r->count,
            ]);

        // Quién descuenta más
        $porUsuario = (clone $base)
            ->select('user_id', DB::raw('SUM(monto_descuento) as total'), DB::raw('COUNT(*) as count'))
            ->groupBy('user_id')
            ->with('user:id,name')
            ->orderByDesc('total')->get()
            ->map(fn ($r) => [
                'nombre' => $r->user?->name ?? '—',
                'total'  => (float) $r->total,
                'count'  => (int)   $r->count,
            ]);

        // Clientes que más descuento reciben
        $porCliente = (clone $base)
            ->whereNotNull('cliente_id')
            ->select('cliente_id', DB::raw('SUM(monto_descuento) as total'), DB::raw('COUNT(*) as count'))
            ->groupBy('cliente_id')
            ->with('cliente:id,nombres,apellidos,razon_social')
            ->orderByDesc('total')->limit(8)->get()
            ->map(fn ($r) => [
                'nombre' => $r->cliente?->razon_social
                    ?: trim(($r->cliente?->nombres ?? '') . ' ' . ($r->cliente?->apellidos ?? '')) ?: '—',
                'total'  => (float) $r->total,
                'count'  => (int)   $r->count,
            ]);

        $conceptos = DescuentoConcepto::deEmpresa($user->empresa_id)->orderBy('nombre')->get(['id', 'nombre']);
        $usuarios  = User::where('empresa_id', $user->empresa_id)->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Reportes/Descuentos', [
            'logs'         => $logs,
            'kpis'         => $kpis,
            'serie_diaria' => $serieDiaria,
            'por_concepto' => $porConcepto,
            'por_usuario'  => $porUsuario,
            'por_cliente'  => $porCliente,
            'conceptos'    => $conceptos,
            'usuarios'     => $usuarios,
            'filters'      => [
                'fecha_desde' => $desde,
                'fecha_hasta' => $hasta,
                'concepto_id' => $request->concepto_id,
                'user_id'     => $request->user_id,
                'aprobacion'  => $request->aprobacion,
            ],
        ]);
    }
}
