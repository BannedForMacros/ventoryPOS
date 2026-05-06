<?php

namespace App\Http\Controllers\Reportes;

use App\Http\Controllers\Controller;
use App\Models\MetodoPago;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaItem;
use App\Models\VentaPago;
use App\Services\LocalScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ReporteVentaController extends Controller
{
    public function __construct(private LocalScopeService $scope) {}

    public function index(Request $request)
    {
        $user = $request->user();

        $desde = $request->fecha_desde ?: now()->startOfMonth()->toDateString();
        $hasta = $request->fecha_hasta ?: now()->toDateString();

        $base = Venta::deEmpresa($user->empresa_id)
            ->whereBetween('fecha_venta', [$desde . ' 00:00:00', $hasta . ' 23:59:59'])
            ->when($request->estado, fn ($q, $v) => $q->where('estado', $v))
            ->when($request->local_id, fn ($q, $v) => $q->where('local_id', $v))
            ->when($request->user_id, fn ($q, $v) => $q->where('user_id', $v))
            ->when($user->local_id, fn ($q) => $q->where('local_id', $user->local_id));

        // KPIs sobre ventas COMPLETADAS del rango
        $completadas = (clone $base)->where('estado', 'completada');

        $kpis = [
            'total_ventas'    => (int)   (clone $completadas)->count(),
            'total_anuladas'  => (int)   (clone $base)->where('estado', 'anulada')->count(),
            'monto_total'     => (float) (clone $completadas)->sum('total'),
            'monto_subtotal'  => (float) (clone $completadas)->sum('subtotal'),
            'monto_descuento' => (float) (clone $completadas)->sum('descuento_total'),
            'monto_igv'       => (float) (clone $completadas)->sum('igv'),
            'ticket_promedio' => 0.0,
        ];
        if ($kpis['total_ventas'] > 0) {
            $kpis['ticket_promedio'] = round($kpis['monto_total'] / $kpis['total_ventas'], 2);
        }

        // Si se filtra por método de pago, restringir a ventas con ese método
        if ($request->metodo_pago_id) {
            $base = $base->whereHas('pagos', fn ($q) => $q->where('metodo_pago_id', $request->metodo_pago_id));
        }

        $ventas = (clone $base)
            ->with(['user:id,name', 'cliente:id,nombres,apellidos,razon_social', 'local:id,nombre', 'pagos.metodoPago:id,nombre,tipo'])
            ->orderByDesc('fecha_venta')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        // Distribución por método de pago (solo ventas completadas del rango)
        $porMetodo = VentaPago::query()
            ->select('metodo_pago_id', DB::raw('SUM(monto) as total'), DB::raw('COUNT(*) as ocurrencias'))
            ->whereHas('venta', function ($q) use ($completadas) {
                $q->whereIn('id', (clone $completadas)->select('id'));
            })
            ->groupBy('metodo_pago_id')
            ->with('metodoPago:id,nombre,tipo')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => [
                'metodo_pago_id' => $r->metodo_pago_id,
                'nombre'         => $r->metodoPago?->nombre ?? '—',
                'tipo'           => $r->metodoPago?->tipo ?? '',
                'total'          => (float) $r->total,
                'ocurrencias'    => (int) $r->ocurrencias,
            ]);

        // Top 10 productos del rango
        $topProductos = VentaItem::query()
            ->select(
                'producto_id',
                DB::raw('MIN(producto_nombre) as producto_nombre'),
                DB::raw('SUM(cantidad) as cantidad'),
                DB::raw('SUM(subtotal) as total'),
            )
            ->whereHas('venta', function ($q) use ($completadas) {
                $q->whereIn('id', (clone $completadas)->select('id'));
            })
            ->groupBy('producto_id')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn ($r) => [
                'producto_id'     => $r->producto_id,
                'producto_nombre' => $r->producto_nombre,
                'cantidad'        => (float) $r->cantidad,
                'total'           => (float) $r->total,
            ]);

        // Serie diaria para gráfico
        $serieDiaria = (clone $completadas)
            ->select(
                DB::raw("DATE(fecha_venta) as dia"),
                DB::raw('SUM(total) as total'),
                DB::raw('COUNT(*) as ventas'),
            )
            ->groupBy('dia')
            ->orderBy('dia')
            ->get()
            ->map(fn ($r) => [
                'dia'    => $r->dia,
                'total'  => (float) $r->total,
                'ventas' => (int) $r->ventas,
            ]);

        $locales      = $this->scope->localesVisibles($user);
        $usuarios     = User::where('empresa_id', $user->empresa_id)->orderBy('name')->get(['id', 'name']);
        $metodosPago  = MetodoPago::where('empresa_id', $user->empresa_id)->orderBy('nombre')->get(['id', 'nombre', 'tipo']);

        return Inertia::render('Reportes/Ventas', [
            'ventas'        => $ventas,
            'kpis'          => $kpis,
            'por_metodo'    => $porMetodo,
            'top_productos' => $topProductos,
            'serie_diaria'  => $serieDiaria,
            'locales'       => $locales,
            'usuarios'      => $usuarios,
            'metodos_pago'  => $metodosPago,
            'filters'       => [
                'fecha_desde'     => $desde,
                'fecha_hasta'     => $hasta,
                'estado'          => $request->estado,
                'local_id'        => $request->local_id,
                'user_id'         => $request->user_id,
                'metodo_pago_id'  => $request->metodo_pago_id,
            ],
        ]);
    }
}
