<?php

namespace App\Http\Controllers\Reportes;

use App\Http\Controllers\Controller;
use App\Models\MetodoPago;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaItem;
use App\Models\VentaPago;
use App\Services\LocalScopeService;
use Carbon\Carbon;
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

        // Query base con TODOS los filtros menos fechas (para reusar en la comparativa)
        $filtrada = function (string $d, string $h) use ($request, $user) {
            return Venta::deEmpresa($user->empresa_id)
                ->whereBetween('fecha_venta', [$d . ' 00:00:00', $h . ' 23:59:59'])
                ->when($request->estado, fn ($q, $v) => $q->where('estado', $v))
                ->when($request->local_id, fn ($q, $v) => $q->where('local_id', $v))
                ->when($request->user_id, fn ($q, $v) => $q->where('user_id', $v))
                ->when($request->tipo === 'contado', fn ($q) => $q->where('es_credito', false))
                ->when($request->tipo === 'credito', fn ($q) => $q->where('es_credito', true))
                ->when($request->comprobante, fn ($q, $v) => $q->where('tipo_comprobante', $v))
                ->when($request->buscar, function ($q, $v) {
                    $q->where(function ($qq) use ($v) {
                        $qq->where('numero', 'ilike', "%{$v}%")
                           ->orWhereHas('cliente', fn ($c) => $c
                               ->where('nombres', 'ilike', "%{$v}%")
                               ->orWhere('apellidos', 'ilike', "%{$v}%")
                               ->orWhere('razon_social', 'ilike', "%{$v}%")
                               ->orWhere('numero_documento', 'ilike', "%{$v}%"));
                    });
                })
                ->when($request->metodo_pago_id, fn ($q, $v) => $q->whereHas('pagos', fn ($p) => $p->where('metodo_pago_id', $v)))
                ->when($user->local_id, fn ($q) => $q->where('local_id', $user->local_id));
        };

        $base        = $filtrada($desde, $hasta);
        $completadas = (clone $base)->where('estado', 'completada');

        // ── KPIs ──────────────────────────────────────────────────────────
        $kpis = [
            'total_ventas'      => (int)   (clone $completadas)->count(),
            'total_anuladas'    => (int)   (clone $base)->where('estado', 'anulada')->count(),
            'monto_anuladas'    => (float) (clone $base)->where('estado', 'anulada')->sum('total'),
            'monto_total'       => (float) (clone $completadas)->sum('total'),
            'monto_descuento'   => (float) (clone $completadas)->sum('descuento_total'),
            'monto_igv'         => (float) (clone $completadas)->sum('igv'),
            'monto_contado'     => (float) (clone $completadas)->where('es_credito', false)->sum('total'),
            'monto_credito'     => (float) (clone $completadas)->where('es_credito', true)->sum('total'),
            'credito_pendiente' => (float) (clone $completadas)->where('es_credito', true)->sum('saldo_pendiente'),
            'clientes_distintos'=> (int)   (clone $completadas)->whereNotNull('cliente_id')->distinct('cliente_id')->count('cliente_id'),
            'ticket_promedio'   => 0.0,
        ];
        if ($kpis['total_ventas'] > 0) {
            $kpis['ticket_promedio'] = round($kpis['monto_total'] / $kpis['total_ventas'], 2);
        }

        // Comparativa vs período anterior de la misma duración
        $d1 = Carbon::parse($desde); $d2 = Carbon::parse($hasta);
        $dias = $d1->diffInDays($d2) + 1;
        $prevDesde = $d1->copy()->subDays($dias)->toDateString();
        $prevHasta = $d1->copy()->subDay()->toDateString();
        $prev = $filtrada($prevDesde, $prevHasta)->where('estado', 'completada');
        $kpis['prev_monto']  = (float) (clone $prev)->sum('total');
        $kpis['prev_ventas'] = (int)   (clone $prev)->count();
        $kpis['variacion']   = $kpis['prev_monto'] > 0
            ? round((($kpis['monto_total'] - $kpis['prev_monto']) / $kpis['prev_monto']) * 100, 1)
            : null;

        // ── Serie diaria ──────────────────────────────────────────────────
        $serieDiaria = (clone $completadas)
            ->select(
                DB::raw('DATE(fecha_venta) as dia'),
                DB::raw('SUM(total) as total'),
                DB::raw('COUNT(*) as ventas'),
                DB::raw('SUM(descuento_total) as descuento'),
            )
            ->groupBy('dia')->orderBy('dia')->get()
            ->map(fn ($r) => [
                'dia'       => $r->dia,
                'total'     => (float) $r->total,
                'ventas'    => (int)   $r->ventas,
                'descuento' => (float) $r->descuento,
            ]);

        // ── Ventas por hora del día ───────────────────────────────────────
        $porHora = (clone $completadas)
            ->select(DB::raw('EXTRACT(HOUR FROM fecha_venta)::int as hora'), DB::raw('SUM(total) as total'), DB::raw('COUNT(*) as ventas'))
            ->groupBy('hora')->orderBy('hora')->get()
            ->map(fn ($r) => ['hora' => (int) $r->hora, 'total' => (float) $r->total, 'ventas' => (int) $r->ventas]);

        // ── Distribución por método de pago (pagos de ventas completadas) ─
        $porMetodo = VentaPago::query()
            ->select('metodo_pago_id', DB::raw('SUM(monto - COALESCE(vuelto, 0)) as total'), DB::raw('COUNT(*) as ocurrencias'))
            ->whereIn('venta_id', (clone $completadas)->select('id'))
            ->groupBy('metodo_pago_id')
            ->with('metodoPago:id,nombre')
            ->orderByDesc('total')->get()
            ->map(fn ($r) => [
                'metodo_pago_id' => $r->metodo_pago_id,
                'nombre'         => $r->metodoPago?->nombre ?? '—',
                'total'          => (float) $r->total,
                'ocurrencias'    => (int) $r->ocurrencias,
            ]);

        // ── Por vendedor ──────────────────────────────────────────────────
        $porVendedor = (clone $completadas)
            ->select('user_id', DB::raw('SUM(total) as total'), DB::raw('COUNT(*) as ventas'))
            ->groupBy('user_id')
            ->with('user:id,name')
            ->orderByDesc('total')->get()
            ->map(fn ($r) => [
                'user_id' => $r->user_id,
                'nombre'  => $r->user?->name ?? '—',
                'total'   => (float) $r->total,
                'ventas'  => (int) $r->ventas,
            ]);

        // ── Por tipo de comprobante ───────────────────────────────────────
        $porComprobante = (clone $completadas)
            ->select('tipo_comprobante', DB::raw('SUM(total) as total'), DB::raw('COUNT(*) as ventas'))
            ->groupBy('tipo_comprobante')->orderByDesc('total')->get()
            ->map(fn ($r) => [
                'tipo'   => $r->tipo_comprobante,
                'total'  => (float) $r->total,
                'ventas' => (int) $r->ventas,
            ]);

        // ── Top productos ─────────────────────────────────────────────────
        $topProductos = VentaItem::query()
            ->select(
                'producto_id',
                DB::raw('MIN(producto_nombre) as producto_nombre'),
                DB::raw('SUM(cantidad) as cantidad'),
                DB::raw('SUM(subtotal) as total'),
            )
            ->whereIn('venta_id', (clone $completadas)->select('id'))
            ->groupBy('producto_id')
            ->orderByDesc('total')->limit(10)->get()
            ->map(fn ($r) => [
                'producto_id'     => $r->producto_id,
                'producto_nombre' => $r->producto_nombre,
                'cantidad'        => (float) $r->cantidad,
                'total'           => (float) $r->total,
            ]);

        // ── Top clientes ──────────────────────────────────────────────────
        $topClientes = (clone $completadas)
            ->whereNotNull('cliente_id')
            ->select('cliente_id', DB::raw('SUM(total) as total'), DB::raw('COUNT(*) as ventas'))
            ->groupBy('cliente_id')
            ->with('cliente:id,nombres,apellidos,razon_social,es_cliente_general')
            ->orderByDesc('total')->limit(8)->get()
            ->map(fn ($r) => [
                'cliente_id' => $r->cliente_id,
                'nombre'     => $r->cliente?->razon_social
                    ?: trim(($r->cliente?->nombres ?? '') . ' ' . ($r->cliente?->apellidos ?? '')) ?: '—',
                'total'      => (float) $r->total,
                'ventas'     => (int) $r->ventas,
            ]);

        // ── Listado con detalle (items + pagos para fila expandible) ──────
        $ventas = (clone $base)
            ->with([
                'user:id,name',
                'cliente:id,nombres,apellidos,razon_social,numero_documento',
                'local:id,nombre',
                'pagos.metodoPago:id,nombre',
                'items:id,venta_id,producto_id,producto_nombre,unidad_nombre,cantidad,precio_unitario,precio_original,descuento_item,subtotal',
            ])
            ->orderByDesc('fecha_venta')->orderByDesc('id')
            ->paginate(25)->withQueryString();

        $locales     = $this->scope->localesVisibles($user);
        $usuarios    = User::where('empresa_id', $user->empresa_id)->orderBy('name')->get(['id', 'name']);
        $metodosPago = MetodoPago::where('empresa_id', $user->empresa_id)->orderBy('nombre')->get(['id', 'nombre']);

        return Inertia::render('Reportes/Ventas', [
            'ventas'          => $ventas,
            'kpis'            => $kpis,
            'serie_diaria'    => $serieDiaria,
            'por_hora'        => $porHora,
            'por_metodo'      => $porMetodo,
            'por_vendedor'    => $porVendedor,
            'por_comprobante' => $porComprobante,
            'top_productos'   => $topProductos,
            'top_clientes'    => $topClientes,
            'locales'         => $locales,
            'usuarios'        => $usuarios,
            'metodos_pago'    => $metodosPago,
            'rango_anterior'  => ['desde' => $prevDesde, 'hasta' => $prevHasta],
            'filters'         => [
                'fecha_desde'    => $desde,
                'fecha_hasta'    => $hasta,
                'estado'         => $request->estado,
                'local_id'       => $request->local_id,
                'user_id'        => $request->user_id,
                'metodo_pago_id' => $request->metodo_pago_id,
                'tipo'           => $request->tipo,
                'comprobante'    => $request->comprobante,
                'buscar'         => $request->buscar,
            ],
        ]);
    }
}
