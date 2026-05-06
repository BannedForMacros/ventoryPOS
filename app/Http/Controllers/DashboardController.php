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

        // Ventas por método de pago (mes)
        $ventasPorMetodo = VentaPago::join('ventas', 'ventas.id', '=', 'venta_pagos.venta_id')
            ->join('metodos_pago', 'metodos_pago.id', '=', 'venta_pagos.metodo_pago_id')
            ->where('ventas.empresa_id', $empresaId)
            ->where('ventas.estado', 'completada')
            ->where('ventas.fecha_venta', '>=', $mesIni)
            ->select('metodos_pago.nombre', 'metodos_pago.tipo',
                     DB::raw('SUM(venta_pagos.monto) as total'))
            ->groupBy('metodos_pago.id', 'metodos_pago.nombre', 'metodos_pago.tipo')
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

        return Inertia::render('Dashboard/Admin', [
            'kpis' => [
                'ventas_hoy'        => ['cant' => (int) $ventasHoy->cant,    'total' => (float) $ventasHoy->total],
                'ventas_mes'        => ['cant' => (int) $ventasMes->cant,    'total' => (float) $ventasMes->total],
                'devoluciones_mes'  => ['cant' => (int) $devolucionesMes->cant, 'total' => (float) $devolucionesMes->total],
                'gastos_mes'        => $gastosMes,
                'stock_valorizado'  => $stockValor,
                'diferencia_caja_mes' => $diferenciaMes,
            ],
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
                ->where('ventas.turno_id', $turnoActivo->id)
                ->where('ventas.estado', 'completada')
                ->select('metodos_pago.nombre', 'metodos_pago.tipo',
                         DB::raw('SUM(venta_pagos.monto) as total'))
                ->groupBy('metodos_pago.id', 'metodos_pago.nombre', 'metodos_pago.tipo')
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
