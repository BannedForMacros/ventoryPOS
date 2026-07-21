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

        // El "documento" se resuelve EN VIVO según la referencia del movimiento:
        //   venta   → ventas.numero      (ej. V-0001)
        //   entrada → entradas.correlativo (ej. E-20260716-001)
        //   resto   → movimientos_inventario.documento (fallback ya guardado)
        // Los LEFT JOIN llevan el filtro de referencia_tipo en el ON para no cruzar
        // un referencia_id de entrada contra el id de una venta (y viceversa).
        // Seleccionamos movimientos_inventario.* explícito para no pisar columnas.
        $movimientos = (clone $base)
            ->leftJoin('ventas', function ($j) {
                $j->on('ventas.id', '=', 'movimientos_inventario.referencia_id')
                  ->where('movimientos_inventario.referencia_tipo', '=', 'venta');
            })
            ->leftJoin('entradas', function ($j) {
                $j->on('entradas.id', '=', 'movimientos_inventario.referencia_id')
                  ->where('movimientos_inventario.referencia_tipo', '=', 'entrada');
            })
            ->select('movimientos_inventario.*')
            ->selectRaw('COALESCE(ventas.numero, entradas.correlativo, movimientos_inventario.documento) as documento_resuelto')
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
                'documento'        => $m->documento_resuelto ?? $m->documento,
                'referencia_tipo'  => $m->referencia_tipo,
                'referencia_id'    => $m->referencia_id,
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

    /**
     * Historial DIARIO de un producto (JSON, para el modal del kardex).
     *
     * Devuelve, por cada día con movimiento en el rango, cuánto entró, cuánto
     * salió y el SALDO al cierre del día (cuánta cantidad quedó ese día). Si se
     * pasa `fecha`, agrega `punto`: el stock que el producto tenía al cierre de
     * ESA fecha exacta (aunque ese día no haya tenido movimiento), respondiendo
     * "¿cuánto tenía el día tal?".
     *
     * El saldo al cierre se toma del último movimiento de cada almacén con
     * fecha <= fin del día (DISTINCT ON) y se suma entre almacenes: así arrastra
     * bien el stock en los días sin movimiento y cubre productos multi-almacén.
     */
    public function historialDiario(Request $request)
    {
        $user       = $request->user();
        $almacenes  = $this->scope->almacenesVisibles($user);
        $almacenIds = $almacenes->pluck('id')->toArray();

        $productoId = (int) $request->producto_id;
        abort_unless($productoId, 422, 'Selecciona un producto para ver su historial diario.');

        $desde = $request->fecha_desde ?: now()->startOfMonth()->toDateString();
        $hasta = $request->fecha_hasta ?: now()->toDateString();

        // Alcance de almacenes: respeta el filtro opcional; si no, todos los visibles.
        $almIds = $request->almacen_id ? [(int) $request->almacen_id] : $almacenIds;
        if (empty($almIds)) {
            return response()->json(['dias' => [], 'punto' => null]);
        }
        $ph = implode(',', array_fill(0, count($almIds), '?'));

        // Saldo (cantidad y valorizado) al cierre de un instante: último
        // movimiento por almacén con fecha <= corte, sumado entre almacenes.
        $saldoAlCierre = "
            (SELECT COALESCE(SUM(s.saldo), 0) FROM (
                SELECT DISTINCT ON (m.almacen_id) m.saldo_cantidad AS saldo
                FROM movimientos_inventario m
                WHERE m.empresa_id = ? AND m.producto_id = ? AND m.almacen_id IN ($ph)
                  AND m.fecha <= %s
                ORDER BY m.almacen_id, m.fecha DESC, m.id DESC
            ) s)";
        $valorAlCierre = str_replace('m.saldo_cantidad AS saldo', 'm.saldo_valorizado AS saldo', $saldoAlCierre);

        $finDia = "(d.dia + INTERVAL '1 day' - INTERVAL '1 second')";

        $sql = "
            WITH dias AS (
                SELECT fecha::date AS dia,
                       COALESCE(SUM(CASE WHEN cantidad > 0 THEN cantidad ELSE 0 END), 0) AS entra,
                       COALESCE(SUM(CASE WHEN cantidad < 0 THEN -cantidad ELSE 0 END), 0) AS sale
                FROM movimientos_inventario
                WHERE empresa_id = ? AND producto_id = ? AND almacen_id IN ($ph)
                  AND fecha::date BETWEEN ? AND ?
                GROUP BY fecha::date
            )
            SELECT d.dia, d.entra, d.sale,
                   " . sprintf($saldoAlCierre, $finDia) . " AS saldo_cierre,
                   " . sprintf($valorAlCierre, $finDia) . " AS valor_cierre
            FROM dias d
            ORDER BY d.dia DESC";

        $bind = array_merge(
            [$user->empresa_id, $productoId], $almIds, [$desde, $hasta],   // CTE dias
            [$user->empresa_id, $productoId], $almIds,                     // saldo_cierre
            [$user->empresa_id, $productoId], $almIds,                     // valor_cierre
        );

        $dias = collect(DB::select($sql, $bind))->map(fn ($r) => [
            'dia'          => $r->dia,
            'entra'        => (float) $r->entra,
            'sale'         => (float) $r->sale,
            'saldo_cierre' => (float) $r->saldo_cierre,
            'valor_cierre' => (float) $r->valor_cierre,
        ]);

        // Punto exacto: stock al cierre de la fecha consultada (cualquier día).
        $punto = null;
        if ($request->fecha) {
            $sqlP = 'SELECT ' . sprintf($saldoAlCierre, '?') . ' AS saldo, '
                              . sprintf($valorAlCierre, '?') . ' AS valor';
            $row  = DB::selectOne($sqlP, array_merge(
                [$user->empresa_id, $productoId], $almIds, [$request->fecha . ' 23:59:59'],
                [$user->empresa_id, $productoId], $almIds, [$request->fecha . ' 23:59:59'],
            ));
            $punto = [
                'fecha' => $request->fecha,
                'saldo' => (float) $row->saldo,
                'valor' => (float) $row->valor,
            ];
        }

        return response()->json(['dias' => $dias, 'punto' => $punto]);
    }
}
