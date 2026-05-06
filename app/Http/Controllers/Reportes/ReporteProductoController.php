<?php

namespace App\Http\Controllers\Reportes;

use App\Http\Controllers\Controller;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\VentaItem;
use App\Services\LocalScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ReporteProductoController extends Controller
{
    public function __construct(private LocalScopeService $scope) {}

    public function index(Request $request)
    {
        $user = $request->user();

        $desde = $request->fecha_desde ?: now()->startOfMonth()->toDateString();
        $hasta = $request->fecha_hasta ?: now()->toDateString();

        // Subquery: ids de ventas COMPLETADAS de la empresa en el rango y con scope
        $ventasIds = \App\Models\Venta::deEmpresa($user->empresa_id)
            ->where('estado', 'completada')
            ->whereBetween('fecha_venta', [$desde . ' 00:00:00', $hasta . ' 23:59:59'])
            ->when($request->local_id, fn ($q, $v) => $q->where('local_id', $v))
            ->when($user->local_id, fn ($q) => $q->where('local_id', $user->local_id))
            ->select('id');

        $query = VentaItem::query()
            ->select(
                'venta_items.producto_id',
                DB::raw('MIN(venta_items.producto_nombre) as producto_nombre'),
                DB::raw('SUM(venta_items.cantidad) as cantidad_total'),
                DB::raw('SUM(venta_items.subtotal) as monto_total'),
                DB::raw('COUNT(DISTINCT venta_items.venta_id) as ventas_distintas'),
                DB::raw('AVG(venta_items.precio_unitario) as precio_promedio'),
                'productos.categoria_id',
                DB::raw('MIN(categorias.nombre) as categoria_nombre'),
            )
            ->leftJoin('productos', 'productos.id', '=', 'venta_items.producto_id')
            ->leftJoin('categorias', 'categorias.id', '=', 'productos.categoria_id')
            ->whereIn('venta_items.venta_id', $ventasIds)
            ->when($request->categoria_id, fn ($q, $v) => $q->where('productos.categoria_id', $v))
            ->when($request->buscar, fn ($q, $v) => $q->where('venta_items.producto_nombre', 'ilike', "%{$v}%"))
            ->groupBy('venta_items.producto_id', 'productos.categoria_id');

        $orden = match ($request->orden) {
            'cantidad'  => 'cantidad_total',
            'ventas'    => 'ventas_distintas',
            'precio'    => 'precio_promedio',
            default     => 'monto_total',
        };

        $productos = (clone $query)
            ->orderByDesc($orden)
            ->paginate(30)
            ->withQueryString()
            ->through(fn ($r) => [
                'producto_id'       => $r->producto_id,
                'producto_nombre'   => $r->producto_nombre,
                'cantidad_total'    => (float) $r->cantidad_total,
                'monto_total'       => (float) $r->monto_total,
                'ventas_distintas'  => (int)   $r->ventas_distintas,
                'precio_promedio'   => round((float) $r->precio_promedio, 2),
                'categoria_id'      => $r->categoria_id,
                'categoria_nombre'  => $r->categoria_nombre,
            ]);

        // Totales globales (no paginados) — copia de la query, pero sumando agregados
        $totales = DB::query()
            ->fromSub($query->getQuery(), 't')
            ->selectRaw('SUM(cantidad_total) as cantidad, SUM(monto_total) as monto, COUNT(*) as productos')
            ->first();

        $kpis = [
            'productos_distintos' => (int)   ($totales->productos ?? 0),
            'cantidad_vendida'    => (float) ($totales->cantidad  ?? 0),
            'monto_total'         => (float) ($totales->monto     ?? 0),
        ];

        $categorias = Categoria::where('empresa_id', $user->empresa_id)
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        $locales = $this->scope->localesVisibles($user);

        return Inertia::render('Reportes/Productos', [
            'productos'  => $productos,
            'kpis'       => $kpis,
            'categorias' => $categorias,
            'locales'    => $locales,
            'filters'    => [
                'fecha_desde'  => $desde,
                'fecha_hasta'  => $hasta,
                'local_id'     => $request->local_id,
                'categoria_id' => $request->categoria_id,
                'buscar'       => $request->buscar,
                'orden'        => $orden === 'monto_total' ? null : $request->orden,
            ],
        ]);
    }
}
