<?php

namespace App\Http\Controllers\Reportes;

use App\Http\Controllers\Controller;
use App\Models\MetodoPago;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaItem;
use App\Models\VentaPago;
use App\Services\LocalScopeService;
use App\Services\ReporteVentasPdfService;
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

        // Query base con TODOS los filtros menos fechas (para reusar en la comparativa).
        // El buscador NO entra aquí: vive en la lista "Venta por venta" y solo la filtra a ella.
        $filtrada = $this->consulta($request);

        $base        = $filtrada($desde, $hasta);
        $completadas = (clone $base)->where('estado', 'completada');

        // Periodo anterior de la misma duración (para la comparativa).
        $d1 = Carbon::parse($desde); $d2 = Carbon::parse($hasta);
        $dias = $d1->diffInDays($d2) + 1;
        $prevDesde = $d1->copy()->subDays($dias)->toDateString();
        $prevHasta = $d1->copy()->subDay()->toDateString();

        // Props perezosas: buscar o paginar la lista pide solo `ventas` y
        // `filters` (partial reload) y nada de lo demás se recalcula.

        // ── KPIs ──────────────────────────────────────────────────────────
        $kpis = function () use ($base, $completadas, $filtrada, $prevDesde, $prevHasta) {
            $kpis = [
                'total_ventas'      => (int)   (clone $completadas)->count(),
                'total_anuladas'    => (int)   (clone $base)->where('estado', 'anulada')->count(),
                'monto_anuladas'    => (float) (clone $base)->where('estado', 'anulada')->sum('total'),
                'monto_total'       => (float) (clone $completadas)->sum('total'),
                // Descuento global de cada venta + el de cada línea (por unidad × cantidad).
                'monto_descuento'   => (float) (clone $completadas)->sum('descuento_total')
                    + (float) VentaItem::whereIn('venta_id', (clone $completadas)->select('id'))->sum(DB::raw('descuento_item * cantidad')),
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

            $prev = $filtrada($prevDesde, $prevHasta)->where('estado', 'completada');
            $kpis['prev_monto']  = (float) (clone $prev)->sum('total');
            $kpis['prev_ventas'] = (int)   (clone $prev)->count();
            $kpis['variacion']   = $kpis['prev_monto'] > 0
                ? round((($kpis['monto_total'] - $kpis['prev_monto']) / $kpis['prev_monto']) * 100, 1)
                : null;
            return $kpis;
        };

        // ── Serie diaria ──────────────────────────────────────────────────
        $serieDiaria = fn () => (clone $completadas)
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
        $porHora = fn () => (clone $completadas)
            ->select(DB::raw('EXTRACT(HOUR FROM fecha_venta)::int as hora'), DB::raw('SUM(total) as total'), DB::raw('COUNT(*) as ventas'))
            ->groupBy('hora')->orderBy('hora')->get()
            ->map(fn ($r) => ['hora' => (int) $r->hora, 'total' => (float) $r->total, 'ventas' => (int) $r->ventas]);

        // ── Distribución por método de pago (pagos de ventas completadas) ─
        $porMetodo = fn () => VentaPago::query()
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
        $porVendedor = fn () => (clone $completadas)
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
        $porComprobante = fn () => (clone $completadas)
            ->select('tipo_comprobante', DB::raw('SUM(total) as total'), DB::raw('COUNT(*) as ventas'))
            ->groupBy('tipo_comprobante')->orderByDesc('total')->get()
            ->map(fn ($r) => [
                'tipo'   => $r->tipo_comprobante,
                'total'  => (float) $r->total,
                'ventas' => (int) $r->ventas,
            ]);

        // ── Top productos ─────────────────────────────────────────────────
        $topProductos = fn () => VentaItem::query()
            ->select(
                'producto_id',
                DB::raw('MIN(producto_nombre) as producto_nombre'),
                // Unidad base (no mezcla presentaciones) y neto del descuento global.
                DB::raw('SUM(cantidad_base) as cantidad'),
                DB::raw('SUM(' . \App\Services\UtilidadService::lineaNeta('venta_items') . ') as total'),
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
        $topClientes = fn () => (clone $completadas)
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
        // Única parte que filtra el buscador.
        $ventas = fn () => (clone $base)
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
            ->with([
                'user:id,name',
                'cliente:id,nombres,apellidos,razon_social,numero_documento',
                'local:id,nombre',
                'pagos.metodoPago:id,nombre',
                'items:id,venta_id,producto_id,producto_nombre,unidad_nombre,cantidad,precio_unitario,precio_original,descuento_item,subtotal',
            ])
            ->orderByDesc('fecha_venta')->orderByDesc('id')
            ->paginate(25)->withQueryString();

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
            'locales'         => fn () => $this->scope->localesVisibles($user),
            'usuarios'        => fn () => User::where('empresa_id', $user->empresa_id)->orderBy('name')->get(['id', 'name']),
            'metodos_pago'    => fn () => MetodoPago::where('empresa_id', $user->empresa_id)->orderBy('nombre')->get(['id', 'nombre']),
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

    /**
     * Reporte para el dueño en PDF: resumen, cobro, cuándo vende, qué y a quién
     * vende, quién vende y descuentos. Mismos filtros que la pantalla (menos
     * Estado y buscador: el PDF siempre resume las completadas y muestra las
     * anuladas aparte).
     */
    public function pdf(Request $request, ReporteVentasPdfService $reporte)
    {
        $user  = $request->user();
        $desde = $request->fecha_desde ?: now()->startOfMonth()->toDateString();
        $hasta = $request->fecha_hasta ?: now()->toDateString();
        if ($hasta < $desde) [$desde, $hasta] = [$hasta, $desde];

        $request->merge(['estado' => null]);
        $datos = $reporte->armar($user->empresa, $desde, $hasta, $this->consulta($request), [
            'filtros' => $this->filtrosLegibles($request),
        ]);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.reporte-ventas', $datos)->setPaper('a4')
            ->setOption('enable_font_subsetting', true); // solo los glifos usados: ~10x más liviano para WhatsApp

        return $pdf->stream("Reporte de ventas {$desde} al {$hasta}.pdf");
    }

    /** fn(desde, hasta): ventas de la empresa con los filtros de la pantalla (sin buscador). */
    private function consulta(Request $request): \Closure
    {
        $user = $request->user();

        return function (string $d, string $h) use ($request, $user) {
            return Venta::deEmpresa($user->empresa_id)
                ->whereBetween('fecha_venta', [$d . ' 00:00:00', $h . ' 23:59:59'])
                ->when($request->estado, fn ($q, $v) => $q->where('estado', $v))
                ->when($request->local_id, fn ($q, $v) => $q->where('local_id', $v))
                ->when($request->user_id, fn ($q, $v) => $q->where('user_id', $v))
                ->when($request->tipo === 'contado', fn ($q) => $q->where('es_credito', false))
                ->when($request->tipo === 'credito', fn ($q) => $q->where('es_credito', true))
                ->when($request->comprobante, fn ($q, $v) => $q->where('tipo_comprobante', $v))
                ->when($request->metodo_pago_id, fn ($q, $v) => $q->whereHas('pagos', fn ($p) => $p->where('metodo_pago_id', $v)))
                ->when($user->local_id, fn ($q) => $q->where('local_id', $user->local_id));
        };
    }

    /** Los filtros activos en palabras, para la cabecera del PDF. */
    private function filtrosLegibles(Request $request): array
    {
        $empresaId = $request->user()->empresa_id;
        $f = [];
        if ($request->local_id)       $f[] = 'Local: ' . (\App\Models\Local::where('empresa_id', $empresaId)->find($request->local_id)?->nombre ?? '—');
        if ($request->user_id)        $f[] = 'Vendedor: ' . (User::where('empresa_id', $empresaId)->find($request->user_id)?->name ?? '—');
        if ($request->metodo_pago_id) $f[] = 'Método de pago: ' . (MetodoPago::where('empresa_id', $empresaId)->find($request->metodo_pago_id)?->nombre ?? '—');
        if ($request->tipo)           $f[] = $request->tipo === 'credito' ? 'Solo crédito' : 'Solo contado';
        if ($request->comprobante)    $f[] = 'Comprobante: ' . (ReporteVentasPdfService::COMPROBANTES[$request->comprobante] ?? $request->comprobante);

        return $f;
    }
}
