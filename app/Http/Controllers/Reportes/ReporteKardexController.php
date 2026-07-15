<?php

namespace App\Http\Controllers\Reportes;

use App\Http\Controllers\Controller;
use App\Models\MovimientoInventario;
use App\Models\Producto;
use App\Services\LocalScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Reporte de Kardex: listado general de TODOS los movimientos de inventario
 * (entradas, salidas, ventas, devoluciones, transferencias, cierres y ajustes),
 * filtrable por fecha, almacén y producto. Solo lectura sobre el ledger
 * `movimientos_inventario`.
 */
class ReporteKardexController extends Controller
{
    public function __construct(private LocalScopeService $scope) {}

    public function index(Request $request)
    {
        $user       = $request->user();
        $almacenes  = $this->scope->almacenesVisibles($user);
        $almacenIds = $almacenes->pluck('id')->toArray();

        $desde = $request->fecha_desde ?: now()->startOfMonth()->toDateString();
        $hasta = $request->fecha_hasta ?: now()->toDateString();

        $base = MovimientoInventario::query()
            ->where('movimientos_inventario.empresa_id', $user->empresa_id)
            ->whereIn('movimientos_inventario.almacen_id', $almacenIds)
            ->whereBetween('movimientos_inventario.fecha', [$desde . ' 00:00:00', $hasta . ' 23:59:59'])
            ->when($request->almacen_id, fn ($q, $v) => $q->where('movimientos_inventario.almacen_id', $v))
            ->when($request->producto_id, fn ($q, $v) => $q->where('movimientos_inventario.producto_id', $v))
            ->when($request->tipo, fn ($q, $v) => $q->where('movimientos_inventario.tipo', $v))
            ->when($request->buscar, fn ($q, $v) => $q->whereExists(fn ($sub) =>
                $sub->select(DB::raw(1))->from('productos')
                    ->whereColumn('productos.id', 'movimientos_inventario.producto_id')
                    ->where(fn ($p) => $p->where('productos.nombre', 'ilike', "%{$v}%")
                                         ->orWhere('productos.codigo', 'ilike', "%{$v}%"))
            ));

        $movimientos = (clone $base)
            ->with([
                'producto:id,nombre,codigo',
                'almacen:id,nombre',
                'usuario:id,name',
            ])
            ->orderByDesc('movimientos_inventario.fecha')
            ->orderByDesc('movimientos_inventario.id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (MovimientoInventario $m) => [
                'id'               => $m->id,
                'fecha'            => optional($m->fecha)->format('Y-m-d H:i'),
                'tipo'             => $m->tipo,
                'tipo_label'       => MovimientoInventario::etiquetaTipo($m->tipo),
                'documento'        => $m->documento,
                'almacen'          => $m->almacen?->nombre ?? '—',
                'producto'         => $m->producto?->nombre ?? '—',
                'producto_codigo'  => $m->producto?->codigo,
                'entra'            => (float) $m->cantidad > 0 ? (float) $m->cantidad : null,
                'sale'             => (float) $m->cantidad < 0 ? abs((float) $m->cantidad) : null,
                'costo_unitario'   => (float) $m->costo_unitario,
                'costo_promedio'   => (float) $m->costo_promedio,
                'saldo_cantidad'   => (float) $m->saldo_cantidad,
                'saldo_valorizado' => (float) $m->saldo_valorizado,
                'usuario'          => $m->usuario?->name,
            ]);

        // KPIs del rango filtrado (no paginados)
        $tot = (clone $base)
            ->selectRaw("
                COUNT(*) as movimientos,
                COALESCE(SUM(CASE WHEN cantidad > 0 THEN cantidad ELSE 0 END), 0) as total_entra,
                COALESCE(SUM(CASE WHEN cantidad < 0 THEN -cantidad ELSE 0 END), 0) as total_sale
            ")->first();

        // Stock actual (de la tabla `stock`, no del rango): mismos filtros de
        // producto/almacén. Con un producto seleccionado = su saldo real hoy.
        $stockActual = (float) \App\Models\Stock::whereIn('almacen_id', $almacenIds)
            ->when($request->almacen_id, fn ($q, $v) => $q->where('almacen_id', $v))
            ->when($request->producto_id, fn ($q, $v) => $q->where('producto_id', $v))
            ->sum('cantidad');

        $kpis = [
            'movimientos'  => (int)   ($tot->movimientos ?? 0),
            'total_entra'  => (float) ($tot->total_entra ?? 0),
            'total_sale'   => (float) ($tot->total_sale ?? 0),
            'stock_actual' => $stockActual,
        ];

        // Producto seleccionado (para mostrar su nombre cuando se entra desde el ojo)
        $productoSel = $request->producto_id
            ? Producto::where('empresa_id', $user->empresa_id)
                ->where('id', $request->producto_id)
                ->first(['id', 'nombre', 'codigo'])
            : null;

        return Inertia::render('Reportes/Kardex', [
            'movimientos'     => $movimientos,
            'kpis'            => $kpis,
            'almacenes'       => $almacenes->map(fn ($a) => ['id' => $a->id, 'nombre' => $a->nombre]),
            'mostrarSelector' => $this->scope->mostrarSelectorLocal($user),
            'productoSel'     => $productoSel,
            'tipos'           => [
                ['value' => 'inventario_inicial',         'label' => 'Inventario inicial'],
                ['value' => 'entrada',                    'label' => 'Entrada'],
                ['value' => 'entrada_reverso',            'label' => 'Entrada (reverso)'],
                ['value' => 'entrada_edicion',            'label' => 'Entrada (edición)'],
                ['value' => 'salida',                     'label' => 'Salida'],
                ['value' => 'venta',                      'label' => 'Venta'],
                ['value' => 'venta_anulacion',            'label' => 'Venta (anulación)'],
                ['value' => 'devolucion',                 'label' => 'Devolución'],
                ['value' => 'devolucion_reverso',         'label' => 'Devolución (reverso)'],
                ['value' => 'transferencia_envio',        'label' => 'Transferencia (envío)'],
                ['value' => 'transferencia_recepcion',    'label' => 'Transferencia (recepción)'],
                ['value' => 'transferencia_reverso',      'label' => 'Transferencia (reverso)'],
                ['value' => 'transferencia_reaplicacion', 'label' => 'Transferencia (reaplicación)'],
                ['value' => 'cierre',                     'label' => 'Cierre de inventario'],
                ['value' => 'entrega_pendiente',          'label' => 'Entrega de pendiente'],
                ['value' => 'ajuste',                     'label' => 'Ajuste'],
            ],
            'filters' => [
                'fecha_desde' => $desde,
                'fecha_hasta' => $hasta,
                'almacen_id'  => $request->almacen_id,
                'producto_id' => $request->producto_id,
                'tipo'        => $request->tipo,
                'buscar'      => $request->buscar,
            ],
        ]);
    }
}
