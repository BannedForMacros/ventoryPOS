<?php

namespace App\Http\Controllers\Reportes;

use App\Http\Controllers\Controller;
use App\Models\Entrada;
use App\Models\Gasto;
use App\Models\MetodoPago;
use App\Models\Venta;
use App\Models\VentaAbono;
use App\Models\VentaPago;
use App\Services\LocalScopeService;
use App\Services\UtilidadService;
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
 *
 * El mismo paquete de datos alimenta la pantalla (Inertia) y la vista de
 * impresión (ruta /reportes/cierre-mes/imprimir) con la que el navegador
 * genera el PDF que se le envía a los dueños.
 */
class ReporteCierreMesController extends Controller
{
    public function __construct(private LocalScopeService $scope) {}

    /** Costo por unidad base: snapshot congelado → precio_costo → costo_promedio. */
    /** Costo por unidad base de cada línea: regla única (CostoVentaService::sql). */
    private static function costoSql(): string
    {
        return \App\Services\CostoVentaService::sql('vi', 'p');
    }

    public function index(Request $request)
    {
        $datos = $this->recopilarDatos($request);

        return Inertia::render('Reportes/CierreMes', [
            ...$datos,
            'locales' => $this->scope->localesVisibles($request->user()),
        ]);
    }

    /** Vista de impresión: el navegador la convierte en PDF de alta calidad. */
    public function imprimir(Request $request)
    {
        $datos   = $this->recopilarDatos($request);
        $empresa = $request->user()->empresa;

        $local = $request->local_id
            ? \App\Models\Local::where('empresa_id', $request->user()->empresa_id)
                ->where('id', $request->local_id)->value('nombre')
            : null;

        return view('reportes.cierre-mes-print', [
            ...$datos,
            'empresa'  => $empresa,
            'generado' => now()->format('d/m/Y H:i'),
            'local'    => $local,
        ]);
    }

    /**
     * Toda la agregación del período, compartida por la pantalla y el PDF.
     *
     * @return array<string, mixed>
     */
    private function recopilarDatos(Request $request): array
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


        $abonosBase = fn (string $d, string $h) => VentaAbono::query()
            ->whereHas('venta', fn ($q) => $q->where('empresa_id', $user->empresa_id)
                ->when($localId, fn ($qq, $v) => $qq->where('local_id', $v)))
            ->whereBetween('fecha', [$d, $h]);

        // ── Estado de resultados del período ────────────────────────────
        $ventasTotal       = (float) (clone $completadas)->sum('total');
        $ventasCount       = (int)   (clone $completadas)->count();
        $igvTotal          = (float) (clone $completadas)->sum('igv');
        // Utilidad: misma regla que el reporte de utilidad y el dashboard
        // (ventas y costo netos de devoluciones; una devolución no es un gasto).
        $utilidad          = app(UtilidadService::class)->resumen($user->empresa_id, $desde, $hasta, $localId);

        // Créditos: otorgados en el período, cobrado (abonos) y saldo al corte
        $creditoOtorgado = (float) (clone $completadas)->where('es_credito', true)->sum('total');
        $creditoCount    = (int)   (clone $completadas)->where('es_credito', true)->count();
        $creditoCobrado  = (float) $abonosBase($desde, $hasta)->sum('monto');

        // Por cobrar AL CORTE (como el balance): el saldo de hoy + los abonos
        // con fecha posterior al corte. Un cobro de la semana siguiente no puede
        // borrar la deuda del cierre del mes.
        $corte = Carbon::parse($hasta)->toDateString(); // normalizada: va en el SQL
        $porCobrarCorte = DB::query()->fromSub(
            Venta::deEmpresa($user->empresa_id)
                ->where('estado', 'completada')->where('es_credito', true)
                ->where('fecha_venta', '<=', $corte . ' 23:59:59')
                ->when($localId, fn ($q, $v) => $q->where('local_id', $v))
                ->selectRaw("ventas.id, ventas.cliente_id, ventas.saldo_pendiente
                    + COALESCE((SELECT SUM(a.monto) FROM venta_abonos a WHERE a.venta_id = ventas.id AND a.fecha > '{$corte}'), 0) AS saldo_corte"),
            'cc')->where('cc.saldo_corte', '>', 0.005);

        // Compras del período: recibidas o en tránsito (como CxP y el balance;
        // borradores no cuentan). Lo pagado es AL CORTE: los pagos posteriores
        // vuelven a ser saldo pendiente, igual que en el balance.
        $comprasBase = Entrada::deEmpresa($user->empresa_id)
            ->whereIn('estado', [Entrada::ESTADO_CONFIRMADO, Entrada::ESTADO_EN_TRANSITO])
            ->whereBetween('fecha', [$desde, $hasta]);
        $pagadoCorteSql = "(entradas.monto_pagado - COALESCE((SELECT SUM(ep.monto) FROM entrada_pagos ep
            WHERE ep.entrada_id = entradas.id AND ep.fecha > '{$corte}'), 0))";
        $comprasTotal   = (float) (clone $comprasBase)->sum('total');
        $comprasPagado  = (float) (clone $comprasBase)->sum(DB::raw($pagadoCorteSql));

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
            'descuentos'        => $utilidad['descuentos'],       // globales + por línea
            'igv'               => round($igvTotal, 2),
            // Estado de resultados (netos de devoluciones):
            'ventas_netas'      => $utilidad['ventas'],           // ventas − dinero devuelto
            'costo'             => $utilidad['costo'],            // costo − lo que volvió al stock
            'utilidad_bruta'    => $utilidad['utilidad_bruta'],
            'margen_bruto'      => $utilidad['margen_bruto'],
            'gastos'            => $utilidad['gastos'],
            'gastos_count'      => (int) $gastosBase($desde, $hasta)->count(),
            'devuelto'          => $utilidad['devuelto'],
            'recuperado'        => $utilidad['recuperado'],
            'costo_danado'      => $utilidad['costo_danado'],
            'devoluciones_count'=> $utilidad['devoluciones_count'],
            'utilidad_neta'     => $utilidad['utilidad_neta'],
            'margen_neto'       => $utilidad['margen_neto'],
            'credito_otorgado'  => round($creditoOtorgado, 2),
            'credito_count'     => $creditoCount,
            'credito_cobrado'   => round($creditoCobrado, 2),
            'por_cobrar'        => round((float) (clone $porCobrarCorte)->sum('cc.saldo_corte'), 2),
            'por_cobrar_count'  => (int) (clone $porCobrarCorte)->count(),
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
            ->selectRaw('DATE(v.fecha_venta) as dia, SUM(vi.cantidad_base * ' . self::costoSql() . ') as costo')
            ->groupBy('dia')->pluck('costo', 'dia');
        $gastosDia = $gastosBase($desde, $hasta)
            ->selectRaw('fecha as dia, SUM(monto) as total')
            ->groupBy('dia')->get()
            ->mapWithKeys(fn ($r) => [substr((string) $r->dia, 0, 10) => (float) $r->total]);
        // Devoluciones del día: se restan de lo vendido (dinero devuelto) y del
        // costo (lo que volvió al stock), no como un gasto aparte.
        $devolucionesDia = app(UtilidadService::class)->devolucionesPorDia($user->empresa_id, $desde, $hasta, $localId);

        $serieDiaria = [];
        for ($d = Carbon::parse($desde); $d->lte(Carbon::parse($hasta)); $d->addDay()) {
            $k     = $d->toDateString();
            $dev   = $devolucionesDia[$k] ?? null;
            $venta = (float) ($ventasDia[$k] ?? 0) - (float) ($dev->devuelto ?? 0);
            $costo = (float) ($cogsDia[$k] ?? 0) - (float) ($dev->recuperado ?? 0);
            $gasto = (float) ($gastosDia[$k] ?? 0);
            $serieDiaria[] = [
                'dia'    => $k,
                'ventas' => round($venta, 2),
                'gastos' => round($gasto, 2),
                'neta'   => round($venta - $costo - $gasto, 2),
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
            ->select('cc.cliente_id', DB::raw('SUM(cc.saldo_corte) as saldo'), DB::raw('COUNT(*) as ventas'))
            ->groupBy('cc.cliente_id')
            ->orderByDesc('saldo')->limit(6)->get();
        $clientesDeudores = \App\Models\Cliente::whereIn('id', $topDeudores->pluck('cliente_id')->filter())
            ->get(['id', 'nombres', 'apellidos', 'razon_social'])->keyBy('id');
        $topDeudores = $topDeudores
            ->map(fn ($r) => (object) ['cliente' => $clientesDeudores->get($r->cliente_id), 'saldo' => $r->saldo, 'ventas' => $r->ventas])
            ->map(fn ($r) => [
                'nombre' => $r->cliente?->razon_social
                    ?: trim(($r->cliente?->nombres ?? '') . ' ' . ($r->cliente?->apellidos ?? '')) ?: '—',
                'saldo'  => (float) $r->saldo,
                'ventas' => (int)   $r->ventas,
            ]);

        // ── Compras por proveedor ───────────────────────────────────────
        $comprasPorProveedor = (clone $comprasBase)
            ->select('proveedor_id', DB::raw('SUM(total) as total'), DB::raw("SUM({$pagadoCorteSql}) as pagado"), DB::raw('COUNT(*) as count'))
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

        return [
            'kpis'                  => $kpis,
            'serie_diaria'          => $serieDiaria,
            'por_comprobante'       => $porComprobante,
            'electronicos'          => $electronicos,
            'por_metodo'            => $porMetodo,
            'gastos_por_tipo'       => $gastosPorTipo,
            'gastos_por_cuenta'     => $gastosPorCuenta,
            'top_deudores'          => $topDeudores,
            'compras_por_proveedor' => $comprasPorProveedor,
            'filters'               => [
                'fecha_desde' => $desde,
                'fecha_hasta' => $hasta,
                'local_id'    => $request->local_id,
            ],
        ];
    }
}
