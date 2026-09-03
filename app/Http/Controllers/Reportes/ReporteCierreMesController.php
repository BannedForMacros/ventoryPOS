<?php

namespace App\Http\Controllers\Reportes;

use App\Http\Controllers\Controller;
use App\Models\Devolucion;
use App\Models\Entrada;
use App\Models\Gasto;
use App\Models\MetodoPago;
use App\Models\Venta;
use App\Models\VentaAbono;
use App\Models\VentaPago;
use App\Services\LocalScopeService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Cierre de mes — estado consolidado para los dueños.
 *
 * Un solo reporte que junta TODO lo que pasa en un período seleccionable
 * (por defecto el mes en curso): ventas, comprobantes (internos, electrónicos
 * SUNAT y externos), cobros por método de pago, créditos y sus abonos,
 * gastos, devoluciones, compras y la utilidad bruta/neta, con comparativa
 * contra el período anterior de la misma duración.
 */
class ReporteCierreMesController extends Controller
{
    public function __construct(private LocalScopeService $scope) {}

    /** Costo por unidad base: snapshot congelado → precio_costo → costo_promedio. */
    private const COSTO_SQL = "COALESCE(NULLIF(vi.costo_unitario_base, 0), NULLIF(p.precio_costo, 0),
        (SELECT s.costo_promedio FROM stock s WHERE s.producto_id = p.id AND s.costo_promedio > 0 ORDER BY s.id LIMIT 1), 0)";

    public function index(Request $request)
    {
        $user = $request->user();

        $desde   = $request->fecha_desde ?: now()->startOfMonth()->toDateString();
        $hasta   = $request->fecha_hasta ?: now()->toDateString();
        $localId = $request->local_id ?: $user->local_id; // cajera con local fijo: solo el suyo

        $rangoDT = [$desde . ' 00:00:00', $hasta . ' 23:59:59'];

        // ── Bases reutilizables ─────────────────────────────────────────
        $ventasBase = fn (string $d, string $h) => Venta::deEmpresa($user->empresa_id)
            ->whereBetween('fecha_venta', [$d . ' 00:00:00', $h . ' 23:59:59'])
            ->when($localId, fn ($q, $v) => $q->where('local_id', $v));

        $completadas = $ventasBase($desde, $hasta)->where('estado', 'completada');

        $itemsBase = fn (string $d, string $h) => DB::table('venta_items as vi')
            ->join('ventas as v', 'v.id', '=', 'vi.venta_id')
            ->join('productos as p', 'p.id', '=', 'vi.producto_id')
            ->where('v.empresa_id', $user->empresa_id)
            ->where('v.estado', 'completada')
            ->whereBetween('v.fecha_venta', [$d . ' 00:00:00', $h . ' 23:59:59'])
            ->when($localId, fn ($q, $v) => $q->where('v.local_id', $v));

        $gastosBase = fn (string $d, string $h) => Gasto::deEmpresa($user->empresa_id)
            ->whereBetween('fecha', [$d, $h])
            ->when($localId, fn ($q, $v) => $q->where('local_id', $v));

        $devolucionesBase = fn (string $d, string $h) => Devolucion::deEmpresa($user->empresa_id)
            ->whereIn('estado', ['aprobada', 'completada'])
            ->whereBetween('fecha', [$d . ' 00:00:00', $h . ' 23:59:59'])
            ->when($localId, fn ($q, $v) => $q->where('local_id', $v));

        $abonosBase = fn (string $d, string $h) => VentaAbono::query()
            ->whereHas('venta', fn ($q) => $q->where('empresa_id', $user->empresa_id)
                ->when($localId, fn ($qq, $v) => $qq->where('local_id', $v)))
            ->whereBetween('fecha', [$d, $h]);

        // ── Estado de resultados del período ────────────────────────────
        $ventasTotal       = (float) (clone $completadas)->sum('total');
        $ventasCount       = (int)   (clone $completadas)->count();
        $descuentosTotal   = (float) (clone $completadas)->sum('descuento_total');
        $igvTotal          = (float) (clone $completadas)->sum('igv');
        $cogsTotal         = (float) (clone $itemsBase($desde, $hasta))
            ->selectRaw('COALESCE(SUM(vi.cantidad_base * ' . self::COSTO_SQL . '), 0) as c')
            ->value('c');
        $gastosTotal       = (float) $gastosBase($desde, $hasta)->sum('monto');
        $devolucionesTotal = (float) $devolucionesBase($desde, $hasta)->sum('monto_devolucion');
        $utilidadBruta     = round($ventasTotal - $cogsTotal, 2);
        $utilidadNeta      = round($utilidadBruta - $gastosTotal - $devolucionesTotal, 2);

        // Créditos: otorgados en el período, cobrado (abonos) y saldo al corte
        $creditoOtorgado = (float) (clone $completadas)->where('es_credito', true)->sum('total');
        $creditoCount    = (int)   (clone $completadas)->where('es_credito', true)->count();
        $creditoCobrado  = (float) $abonosBase($desde, $hasta)->sum('monto');

        $porCobrarCorte = Venta::deEmpresa($user->empresa_id)
            ->where('estado', 'completada')->where('es_credito', true)
            ->where('fecha_venta', '<=', $hasta . ' 23:59:59')
            ->when($localId, fn ($q, $v) => $q->where('local_id', $v));

        // Compras del período (borradores no cuentan: aún no son compra real)
        $comprasBase = Entrada::deEmpresa($user->empresa_id)
            ->whereIn('estado', [Entrada::ESTADO_CONFIRMADO, Entrada::ESTADO_EN_TRANSITO])
            ->whereBetween('fecha', [$desde, $hasta]);
        $comprasTotal   = (float) (clone $comprasBase)->sum('total');
        $comprasPagado  = (float) (clone $comprasBase)->sum('monto_pagado');

        // ── Comparativa vs período anterior de la misma duración ────────
        $dias      = Carbon::parse($desde)->diffInDays(Carbon::parse($hasta)) + 1;
        $prevDesde = Carbon::parse($desde)->subDays($dias)->toDateString();
        $prevHasta = Carbon::parse($desde)->subDay()->toDateString();

        $prevVentas = (float) $ventasBase($prevDesde, $prevHasta)->where('estado', 'completada')->sum('total');
        $prevGastos = (float) $gastosBase($prevDesde, $prevHasta)->sum('monto');

        $kpis = [
            'ventas'            => round($ventasTotal, 2),
            'ventas_count'      => $ventasCount,
            'ticket_promedio'   => $ventasCount > 0 ? round($ventasTotal / $ventasCount, 2) : 0,
            'anuladas_count'    => (int)   $ventasBase($desde, $hasta)->where('estado', 'anulada')->count(),
            'anuladas_monto'    => (float) $ventasBase($desde, $hasta)->where('estado', 'anulada')->sum('total'),
            'descuentos'        => round($descuentosTotal, 2),
            'igv'               => round($igvTotal, 2),
            'costo'             => round($cogsTotal, 2),
            'utilidad_bruta'    => $utilidadBruta,
            'margen_bruto'      => $ventasTotal > 0 ? round($utilidadBruta / $ventasTotal * 100, 1) : null,
            'gastos'            => round($gastosTotal, 2),
            'gastos_count'      => (int) $gastosBase($desde, $hasta)->count(),
            'devoluciones'      => round($devolucionesTotal, 2),
            'devoluciones_count'=> (int) $devolucionesBase($desde, $hasta)->count(),
            'utilidad_neta'     => $utilidadNeta,
            'margen_neto'       => $ventasTotal > 0 ? round($utilidadNeta / $ventasTotal * 100, 1) : null,
            'credito_otorgado'  => round($creditoOtorgado, 2),
            'credito_count'     => $creditoCount,
            'credito_cobrado'   => round($creditoCobrado, 2),
            'por_cobrar'        => round((float) (clone $porCobrarCorte)->sum('saldo_pendiente'), 2),
            'por_cobrar_count'  => (int) (clone $porCobrarCorte)->where('saldo_pendiente', '>', 0)->count(),
            'compras'           => round($comprasTotal, 2),
            'compras_count'     => (int) (clone $comprasBase)->count(),
            'compras_pagado'    => round($comprasPagado, 2),
            'compras_pendiente' => round($comprasTotal - $comprasPagado, 2),
            'prev_ventas'       => round($prevVentas, 2),
            'prev_gastos'       => round($prevGastos, 2),
            'variacion_ventas'  => $prevVentas > 0 ? round(($ventasTotal - $prevVentas) / $prevVentas * 100, 1) : null,
            'rango_anterior'    => ['desde' => $prevDesde, 'hasta' => $prevHasta],
        ];

        // ── Serie diaria: ventas vs gastos vs utilidad neta ─────────────
        $ventasDia = (clone $completadas)
            ->selectRaw('DATE(fecha_venta) as dia, SUM(total) as total')
            ->groupBy('dia')->pluck('total', 'dia');
        $cogsDia = (clone $itemsBase($desde, $hasta))
            ->selectRaw('DATE(v.fecha_venta) as dia, SUM(vi.cantidad_base * ' . self::COSTO_SQL . ') as costo')
            ->groupBy('dia')->pluck('costo', 'dia');
        $gastosDia = $gastosBase($desde, $hasta)
            ->selectRaw('fecha as dia, SUM(monto) as total')
            ->groupBy('dia')->get()
            ->mapWithKeys(fn ($r) => [substr((string) $r->dia, 0, 10) => (float) $r->total]);
        $devolucionesDia = $devolucionesBase($desde, $hasta)
            ->selectRaw('DATE(fecha) as dia, SUM(monto_devolucion) as total')
            ->groupBy('dia')->pluck('total', 'dia');

        $serieDiaria = [];
        for ($d = Carbon::parse($desde); $d->lte(Carbon::parse($hasta)); $d->addDay()) {
            $k     = $d->toDateString();
            $venta = (float) ($ventasDia[$k] ?? 0);
            $costo = (float) ($cogsDia[$k] ?? 0);
            $gasto = (float) ($gastosDia[$k] ?? 0);
            $dev   = (float) ($devolucionesDia[$k] ?? 0);
            $serieDiaria[] = [
                'dia'    => $k,
                'ventas' => round($venta, 2),
                'gastos' => round($gasto, 2),
                'neta'   => round($venta - $costo - $gasto - $dev, 2),
            ];
        }

        // ── Comprobantes por tipo (con rango de numeración) ─────────────
        $porComprobante = $ventasBase($desde, $hasta)
            ->select(
                'tipo_comprobante',
                DB::raw("COUNT(*) FILTER (WHERE estado = 'completada') as emitidos"),
                DB::raw("COALESCE(SUM(total) FILTER (WHERE estado = 'completada'), 0) as total"),
                DB::raw("COUNT(*) FILTER (WHERE estado = 'anulada') as anulados"),
                DB::raw("MIN(numero_comprobante) FILTER (WHERE numero_comprobante IS NOT NULL AND numero_comprobante <> '') as primer_numero"),
                DB::raw("MAX(numero_comprobante) FILTER (WHERE numero_comprobante IS NOT NULL AND numero_comprobante <> '') as ultimo_numero"),
            )
            ->groupBy('tipo_comprobante')->orderByDesc('total')->get()
            ->map(fn ($r) => [
                'tipo'          => $r->tipo_comprobante,
                'emitidos'      => (int)   $r->emitidos,
                'total'         => (float) $r->total,
                'anulados'      => (int)   $r->anulados,
                'primer_numero' => $r->primer_numero,
                'ultimo_numero' => $r->ultimo_numero,
            ]);

        // ── Comprobantes electrónicos SUNAT (estado en el emisor) ───────
        $electronicos = DB::table('venta_comprobantes as vc')
            ->join('ventas as v', 'v.id', '=', 'vc.venta_id')
            ->where('v.empresa_id', $user->empresa_id)
            ->whereBetween('v.fecha_venta', $rangoDT)
            ->when($localId, fn ($q, $v) => $q->where('v.local_id', $v))
            ->select('vc.estado', DB::raw('COUNT(*) as count'))
            ->groupBy('vc.estado')->get()
            ->map(fn ($r) => ['estado' => $r->estado, 'count' => (int) $r->count]);

        // ── Cobros por método de pago (ventas directas + abonos CxC) ────
        $pagosVenta = VentaPago::query()
            ->select('metodo_pago_id', DB::raw('SUM(monto - COALESCE(vuelto, 0)) as total'))
            ->whereIn('venta_id', (clone $completadas)->select('id'))
            ->groupBy('metodo_pago_id')->pluck('total', 'metodo_pago_id');

        $pagosAbono = $abonosBase($desde, $hasta)
            ->select('metodo_pago_id', DB::raw('SUM(monto) as total'))
            ->groupBy('metodo_pago_id')->pluck('total', 'metodo_pago_id');

        $metodos = MetodoPago::where('empresa_id', $user->empresa_id)->orderBy('nombre')->get(['id', 'nombre']);
        $porMetodo = $metodos
            ->map(fn ($m) => [
                'metodo_pago_id' => $m->id,
                'nombre'         => $m->nombre,
                'ventas'         => round((float) ($pagosVenta[$m->id] ?? 0), 2),
                'abonos'         => round((float) ($pagosAbono[$m->id] ?? 0), 2),
                'total'          => round((float) ($pagosVenta[$m->id] ?? 0) + (float) ($pagosAbono[$m->id] ?? 0), 2),
            ])
            ->filter(fn ($r) => $r['total'] > 0)->sortByDesc('total')->values();

        // ── Gastos por tipo y por cuenta ────────────────────────────────
        $gastosPorTipo = $gastosBase($desde, $hasta)
            ->select('gasto_tipo_id', DB::raw('SUM(monto) as total'), DB::raw('COUNT(*) as count'))
            ->groupBy('gasto_tipo_id')
            ->with('tipo:id,nombre,categoria')
            ->orderByDesc('total')->get()
            ->map(fn ($r) => [
                'nombre'    => $r->tipo?->nombre ?? '—',
                'categoria' => $r->tipo?->categoria ?? '',
                'total'     => (float) $r->total,
                'count'     => (int)   $r->count,
            ]);

        $gastosPorCuenta = $gastosBase($desde, $hasta)
            ->select('cuenta_id', DB::raw('SUM(monto) as total'), DB::raw('COUNT(*) as count'))
            ->groupBy('cuenta_id')
            ->with('cuenta:id,nombre')
            ->orderByDesc('total')->get()
            ->map(fn ($r) => [
                'nombre' => $r->cuenta?->nombre ?? '—',
                'total'  => (float) $r->total,
                'count'  => (int)   $r->count,
            ]);

        // ── Clientes con mayor deuda al corte ───────────────────────────
        $topDeudores = (clone $porCobrarCorte)
            ->where('saldo_pendiente', '>', 0)
            ->select('cliente_id', DB::raw('SUM(saldo_pendiente) as saldo'), DB::raw('COUNT(*) as ventas'))
            ->groupBy('cliente_id')
            ->with('cliente:id,nombres,apellidos,razon_social')
            ->orderByDesc('saldo')->limit(6)->get()
            ->map(fn ($r) => [
                'nombre' => $r->cliente?->razon_social
                    ?: trim(($r->cliente?->nombres ?? '') . ' ' . ($r->cliente?->apellidos ?? '')) ?: '—',
                'saldo'  => (float) $r->saldo,
                'ventas' => (int)   $r->ventas,
            ]);

        // ── Compras por proveedor ───────────────────────────────────────
        $comprasPorProveedor = (clone $comprasBase)
            ->select('proveedor_id', DB::raw('SUM(total) as total'), DB::raw('SUM(monto_pagado) as pagado'), DB::raw('COUNT(*) as count'))
            ->groupBy('proveedor_id')
            ->with('proveedorRel:id,razon_social,nombre_comercial')
            ->orderByDesc('total')->limit(6)->get()
            ->map(fn ($r) => [
                'nombre'    => $r->proveedorRel?->razon_social ?: ($r->proveedorRel?->nombre_comercial ?? '—'),
                'total'     => (float) $r->total,
                'pagado'    => (float) $r->pagado,
                'pendiente' => (float) $r->total - (float) $r->pagado,
                'count'     => (int)   $r->count,
            ]);

        return Inertia::render('Reportes/CierreMes', [
            'kpis'                  => $kpis,
            'serie_diaria'          => $serieDiaria,
            'por_comprobante'       => $porComprobante,
            'electronicos'          => $electronicos,
            'por_metodo'            => $porMetodo,
            'gastos_por_tipo'       => $gastosPorTipo,
            'gastos_por_cuenta'     => $gastosPorCuenta,
            'top_deudores'          => $topDeudores,
            'compras_por_proveedor' => $comprasPorProveedor,
            'locales'               => $this->scope->localesVisibles($user),
            'filters'               => [
                'fecha_desde' => $desde,
                'fecha_hasta' => $hasta,
                'local_id'    => $request->local_id,
            ],
        ]);
    }
}
