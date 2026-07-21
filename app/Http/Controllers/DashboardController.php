<?php

namespace App\Http\Controllers;

use App\Models\Almacen;
use App\Models\Devolucion;
use App\Models\Gasto;
use App\Models\Producto;
use App\Models\Stock;
use App\Models\Turno;
use App\Models\Venta;
use App\Models\VentaItem;
use App\Models\VentaPago;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    /**
     * Renderiza un dashboard distinto según el rol del usuario:
     *   - es_admin → vista global con KPIs, top productos, ventas por método, alertas.
     *   - resto    → vista operativa centrada en su turno actual + ventas del día.
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $user->loadMissing('rol');

        return $user->rol?->es_admin
            ? $this->admin($user)
            : $this->cajero($user);
    }

    private function admin($user)
    {
        $empresaId = $user->empresa_id;
        $hoyIni    = Carbon::today();
        $hoyFin    = Carbon::today()->endOfDay();
        $mesIni    = Carbon::today()->startOfMonth();

        // KPIs
        $ventasHoy = Venta::where('empresa_id', $empresaId)
            ->where('estado', 'completada')
            ->whereBetween('fecha_venta', [$hoyIni, $hoyFin])
            ->selectRaw('COUNT(*) as cant, COALESCE(SUM(total),0) as total')
            ->first();

        $ventasMes = Venta::where('empresa_id', $empresaId)
            ->where('estado', 'completada')
            ->where('fecha_venta', '>=', $mesIni)
            ->selectRaw('COUNT(*) as cant, COALESCE(SUM(total),0) as total')
            ->first();

        $devolucionesMes = Devolucion::where('empresa_id', $empresaId)
            ->whereIn('estado', ['aprobada', 'completada'])
            ->where('fecha', '>=', $mesIni)
            ->selectRaw('COUNT(*) as cant, COALESCE(SUM(monto_devolucion),0) as total')
            ->first();

        $gastosMes = (float) Gasto::where('empresa_id', $empresaId)
            ->where('fecha', '>=', $mesIni)
            ->sum('monto');

        // Stock valorizado total
        $almacenIds = Almacen::where('empresa_id', $empresaId)->pluck('id');
        $stockValor = (float) Stock::whereIn('almacen_id', $almacenIds)
            ->selectRaw('COALESCE(SUM(cantidad * costo_promedio),0) as v')
            ->value('v');

        // Productos en alerta de stock bajo (stock = 0 en algún almacén con producto activo)
        // Como el modelo no tiene stock_minimo, contamos los que están en 0
        $stockBajo = Stock::whereIn('stock.almacen_id', $almacenIds)
            ->join('productos', 'productos.id', '=', 'stock.producto_id')
            ->where('productos.activo', true)
            ->where('productos.controla_stock', true)
            ->where('stock.cantidad', '<=', 5) // umbral configurable; 5 unidades como aviso
            ->select('productos.id', 'productos.nombre', 'productos.codigo',
                     'stock.cantidad', 'stock.almacen_id')
            ->orderBy('stock.cantidad')
            ->limit(8)
            ->get();

        // Top productos vendidos del mes
        $topProductos = VentaItem::join('ventas', 'ventas.id', '=', 'venta_items.venta_id')
            ->where('ventas.empresa_id', $empresaId)
            ->where('ventas.estado', 'completada')
            ->where('ventas.fecha_venta', '>=', $mesIni)
            ->select('venta_items.producto_id',
                     DB::raw('MIN(venta_items.producto_nombre) as nombre'),
                     DB::raw('SUM(venta_items.cantidad) as cantidad'),
                     DB::raw('SUM(venta_items.subtotal) as total'))
            ->groupBy('venta_items.producto_id')
            ->orderByDesc('total')
            ->limit(8)
            ->get();

        // Ventas por método de pago (mes). El `tipo` (slug) ahora viene del
        // catálogo tipos_metodo_pago vía join — antes era enum nativo.
        $ventasPorMetodo = VentaPago::join('ventas', 'ventas.id', '=', 'venta_pagos.venta_id')
            ->join('metodos_pago', 'metodos_pago.id', '=', 'venta_pagos.metodo_pago_id')
            ->join('tipos_metodo_pago', 'tipos_metodo_pago.id', '=', 'metodos_pago.tipo_id')
            ->where('ventas.empresa_id', $empresaId)
            ->where('ventas.estado', 'completada')
            ->where('ventas.fecha_venta', '>=', $mesIni)
            ->select('metodos_pago.nombre',
                     DB::raw('tipos_metodo_pago.slug as tipo'),
                     DB::raw('SUM(venta_pagos.monto) as total'))
            ->groupBy('metodos_pago.id', 'metodos_pago.nombre', 'tipos_metodo_pago.slug')
            ->orderByDesc('total')
            ->get();

        // Turnos abiertos AHORA
        $turnosAbiertos = Turno::where('empresa_id', $empresaId)
            ->where('estado', 'abierto')
            ->with(['user:id,name', 'caja:id,nombre', 'local:id,nombre'])
            ->orderBy('fecha_apertura')
            ->get();

        // Últimas operaciones (últimas 6 ventas + 3 devoluciones, ordenadas)
        $ultimasVentas = Venta::where('empresa_id', $empresaId)
            ->where('estado', 'completada')
            ->with(['user:id,name', 'cliente:id,nombres,apellidos,razon_social'])
            ->orderByDesc('fecha_venta')
            ->limit(6)
            ->get(['id', 'numero', 'total', 'fecha_venta', 'user_id', 'cliente_id', 'tipo_comprobante']);

        // Diferencia acumulada en cierres del mes (control de caja)
        $diferenciaMes = (float) Turno::where('empresa_id', $empresaId)
            ->where('estado', 'cerrado')
            ->where('fecha_cierre', '>=', $mesIni)
            ->sum('diferencia');

        // ── Tendencia: ventas de los últimos 30 días (para el gráfico) ──
        $serieIni = Carbon::today()->subDays(29);
        $ventasPorDia = Venta::where('empresa_id', $empresaId)
            ->where('estado', 'completada')
            ->where('fecha_venta', '>=', $serieIni)
            ->selectRaw('DATE(fecha_venta) as dia, SUM(total) as total, COUNT(*) as ventas')
            ->groupBy('dia')
            ->pluck('total', 'dia');
        $serie30 = [];
        for ($d = $serieIni->copy(); $d->lte(Carbon::today()); $d->addDay()) {
            $k = $d->toDateString();
            $serie30[] = ['dia' => $k, 'total' => round((float) ($ventasPorDia[$k] ?? 0), 2)];
        }

        // Comparativa: hoy vs ayer.
        $ventasAyer = (float) Venta::where('empresa_id', $empresaId)
            ->where('estado', 'completada')
            ->whereBetween('fecha_venta', [Carbon::yesterday(), Carbon::yesterday()->endOfDay()])
            ->sum('total');

        // ── Utilidad del mes (costo congelado por ítem, criterio del reporte) ──
        $costoSql = "COALESCE(NULLIF(venta_items.costo_unitario_base, 0), NULLIF(productos.precio_costo, 0),
            (SELECT s.costo_promedio FROM stock s WHERE s.producto_id = productos.id AND s.costo_promedio > 0 ORDER BY s.id LIMIT 1), 0)";
        $cogsMes = (float) VentaItem::join('ventas', 'ventas.id', '=', 'venta_items.venta_id')
            ->join('productos', 'productos.id', '=', 'venta_items.producto_id')
            ->where('ventas.empresa_id', $empresaId)
            ->where('ventas.estado', 'completada')
            ->where('ventas.fecha_venta', '>=', $mesIni)
            ->selectRaw("COALESCE(SUM(venta_items.cantidad_base * {$costoSql}), 0) as c")
            ->value('c');
        $utilidadBrutaMes = round((float) $ventasMes->total - $cogsMes, 2);
        $utilidadNetaMes  = round($utilidadBrutaMes - $gastosMes - (float) $devolucionesMes->total, 2);

        // ── Por cobrar (créditos con saldo) y pendientes por entregar ──
        $cxc = Venta::where('empresa_id', $empresaId)->conSaldoPendiente()
            ->selectRaw('COUNT(*) as cant, COALESCE(SUM(saldo_pendiente),0) as total')->first();

        $pendientesEntrega = \App\Models\ClienteAnticipo::deEmpresa($empresaId)->activo()
            ->where('tipo_valorizacion', 'material')
            ->with(['producto', 'items.unidad'])->get();
        $pendEntrega = [
            'cant'  => $pendientesEntrega->count(),
            'valor' => round($pendientesEntrega->sum(fn ($a) => $a->valorPasivo()), 2),
        ];

        // ── Cotizaciones: seguimiento comercial ─────────────────────────
        // Por vencer (≤3 días) y vencidas sin respuesta del cliente: son las
        // que hay que llamar HOY para no perder la venta.
        $hoy = Carbon::today()->toDateString();
        $cotizaciones = [
            'vigentes'      => (int) \App\Models\Cotizacion::deEmpresa($empresaId)->where('estado', 'vigente')->count(),
            'monto_vigente' => round((float) \App\Models\Cotizacion::deEmpresa($empresaId)->where('estado', 'vigente')->sum('total'), 2),
            'por_vencer'    => \App\Models\Cotizacion::deEmpresa($empresaId)
                ->where('estado', 'vigente')
                ->whereBetween('fecha_vencimiento', [$hoy, Carbon::today()->addDays(3)->toDateString()])
                ->with('cliente:id,nombres,apellidos,razon_social,telefono')
                ->orderBy('fecha_vencimiento')
                ->limit(6)
                ->get(['id', 'numero', 'cliente_id', 'referencia', 'total', 'fecha_vencimiento', 'ultimo_contacto']),
            'vencidas_sin_respuesta' => \App\Models\Cotizacion::deEmpresa($empresaId)
                ->where('estado', 'vencida')
                ->where(fn ($q) => $q->whereNull('ultimo_contacto')->orWhereColumn('ultimo_contacto', '<', 'fecha_vencimiento'))
                ->with('cliente:id,nombres,apellidos,razon_social,telefono')
                ->orderByDesc('fecha_vencimiento')
                ->limit(6)
                ->get(['id', 'numero', 'cliente_id', 'referencia', 'total', 'fecha_vencimiento', 'ultimo_contacto']),
        ];

        return Inertia::render('Dashboard/Admin', [
            'kpis' => [
                'ventas_hoy'        => ['cant' => (int) $ventasHoy->cant,    'total' => (float) $ventasHoy->total],
                'ventas_ayer'       => $ventasAyer,
                'ventas_mes'        => ['cant' => (int) $ventasMes->cant,    'total' => (float) $ventasMes->total],
                'ticket_promedio'   => (int) $ventasMes->cant > 0 ? round((float) $ventasMes->total / (int) $ventasMes->cant, 2) : 0,
                'utilidad_bruta_mes'=> $utilidadBrutaMes,
                'utilidad_neta_mes' => $utilidadNetaMes,
                'margen_bruto_mes'  => (float) $ventasMes->total > 0 ? round($utilidadBrutaMes / (float) $ventasMes->total * 100, 1) : null,
                'devoluciones_mes'  => ['cant' => (int) $devolucionesMes->cant, 'total' => (float) $devolucionesMes->total],
                'gastos_mes'        => $gastosMes,
                'stock_valorizado'  => $stockValor,
                'diferencia_caja_mes' => $diferenciaMes,
                'cxc'               => ['cant' => (int) $cxc->cant, 'total' => (float) $cxc->total],
                'pendientes_entrega'=> $pendEntrega,
            ],
            'serie30'          => $serie30,
            'cotizaciones'     => $cotizaciones,
            'stockBajo'        => $stockBajo,
            'topProductos'     => $topProductos,
            'ventasPorMetodo'  => $ventasPorMetodo,
            'turnosAbiertos'   => $turnosAbiertos,
            'ultimasVentas'    => $ultimasVentas,
        ]);
    }

    private function cajero($user)
    {
        $hoyIni = Carbon::today();
        $hoyFin = Carbon::today()->endOfDay();

        // Carga el local completo: calcularMontoEsperado() consulta config (usa_fondos_iniciales,
        // fondos_iniciales_en_declaracion) que cae a la empresa si están null en el local.
        $turnoActivo = Turno::where('user_id', $user->id)
            ->where('estado', 'abierto')
            ->with(['caja:id,nombre,local_id', 'local'])
            ->first();

        $turnoStats = null;
        if ($turnoActivo) {
            $ventasTurno = Venta::where('turno_id', $turnoActivo->id)
                ->where('estado', 'completada')
                ->selectRaw('COUNT(*) as cant, COALESCE(SUM(total),0) as total')
                ->first();

            $gastosTurno = (float) Gasto::where('turno_id', $turnoActivo->id)->sum('monto');
            $devolucionesTurno = Devolucion::where('turno_id', $turnoActivo->id)
                ->whereIn('estado', ['aprobada', 'completada'])
                ->selectRaw('COUNT(*) as cant, COALESCE(SUM(monto_devolucion),0) as total')
                ->first();

            $montoEsperado = $turnoActivo->calcularMontoEsperado();

            // Ventas por método del turno
            $porMetodo = VentaPago::join('ventas', 'ventas.id', '=', 'venta_pagos.venta_id')
                ->join('metodos_pago', 'metodos_pago.id', '=', 'venta_pagos.metodo_pago_id')
                ->join('tipos_metodo_pago', 'tipos_metodo_pago.id', '=', 'metodos_pago.tipo_id')
                ->where('ventas.turno_id', $turnoActivo->id)
                ->where('ventas.estado', 'completada')
                ->select('metodos_pago.nombre',
                         DB::raw('tipos_metodo_pago.slug as tipo'),
                         DB::raw('SUM(venta_pagos.monto) as total'))
                ->groupBy('metodos_pago.id', 'metodos_pago.nombre', 'tipos_metodo_pago.slug')
                ->orderByDesc('total')
                ->get();

            $turnoStats = [
                'ventas_cant'        => (int) $ventasTurno->cant,
                'ventas_total'       => (float) $ventasTurno->total,
                'gastos_total'       => $gastosTurno,
                'devoluciones_cant'  => (int) $devolucionesTurno->cant,
                'devoluciones_total' => (float) $devolucionesTurno->total,
                'monto_esperado'     => $montoEsperado,
                'por_metodo'         => $porMetodo,
            ];
        }

        // Mis ventas del día (independiente del turno: incluye turnos pasados de hoy)
        $misVentasHoy = Venta::where('user_id', $user->id)
            ->where('estado', 'completada')
            ->whereBetween('fecha_venta', [$hoyIni, $hoyFin])
            ->selectRaw('COUNT(*) as cant, COALESCE(SUM(total),0) as total')
            ->first();

        // Mis últimas 5 ventas
        $ultimasVentas = Venta::where('user_id', $user->id)
            ->where('estado', 'completada')
            ->with(['cliente:id,nombres,apellidos,razon_social'])
            ->orderByDesc('fecha_venta')
            ->limit(5)
            ->get(['id', 'numero', 'total', 'fecha_venta', 'cliente_id', 'tipo_comprobante']);

        return Inertia::render('Dashboard/Cajero', [
            'turnoActivo'  => $turnoActivo,
            'turnoStats'   => $turnoStats,
            'misVentasHoy' => [
                'cant'  => (int) $misVentasHoy->cant,
                'total' => (float) $misVentasHoy->total,
            ],
            'ultimasVentas' => $ultimasVentas,
        ]);
    }
}
