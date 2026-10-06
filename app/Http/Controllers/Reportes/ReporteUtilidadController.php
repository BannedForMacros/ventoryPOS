<?php

namespace App\Http\Controllers\Reportes;

use App\Http\Controllers\Controller;
use App\Services\LocalScopeService;
use App\Services\UtilidadService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Reporte de UTILIDAD: cuánto deja el negocio en un período.
 *
 * La regla (ventas y costo netos de devoluciones, bruta, neta) vive en
 * UtilidadService, compartida con el dashboard y el cierre de mes.
 *
 * Arquitectura de datos: ventas y devoluciones se unen en UNA consulta por
 * producto (porProducto()). De ahí salen la tabla (filtrada, ordenada y
 * PAGINADA en la base de datos), los contadores y las categorías, así que
 * todo cuadra y escala igual con 50 o con 50,000 productos. Cada prop es
 * perezosa: paginar, buscar o filtrar solo recalcula la tabla.
 */
class ReporteUtilidadController extends Controller
{
    private const ORDENES = ['utilidad', 'ventas', 'margen', 'cantidad'];
    private const VISTAS  = ['perdida', 'bajo', 'sincosto'];

    public function __construct(private LocalScopeService $scope, private UtilidadService $utilidad) {}

    public function index(Request $request)
    {
        $user    = $request->user();
        $empresa = $user->empresa_id;
        $desde   = $request->fecha_desde ?: now()->startOfMonth()->toDateString();
        $hasta   = $request->fecha_hasta ?: now()->toDateString();
        $localId = $request->local_id ?: $user->local_id; // cajera con local fijo: solo el suyo

        $orden     = in_array($request->orden, self::ORDENES, true) ? $request->orden : 'utilidad';
        $vista     = in_array($request->vista, self::VISTAS, true) ? $request->vista : null;
        $categoria = $request->categoria ?: null; // id de categoría o 'sin' (sin categoría)
        $buscar    = trim((string) $request->buscar);

        $porProducto = fn () => $this->porProducto($empresa, $desde, $hasta, $localId);

        return Inertia::render('Reportes/Utilidad', [
            'kpis'       => fn () => $this->utilidad->resumen($empresa, $desde, $hasta, $localId),
            'productos'  => fn () => $this->tabla($porProducto(), $orden, $vista, $categoria, $buscar),
            'conteos'    => fn () => $this->conteos($porProducto()),
            'mas_vendidos' => fn () => $this->masVendidos($porProducto()),
            'categorias' => fn () => $this->categorias($porProducto()),
            'locales'    => fn () => $this->scope->localesVisibles($user),
            'filters'    => [
                'fecha_desde' => $desde,
                'fecha_hasta' => $hasta,
                'local_id'    => $request->local_id,
                'orden'       => $orden,
                'vista'       => $vista,
                'categoria'   => $categoria,
                'buscar'      => $buscar !== '' ? $buscar : null,
            ],
        ]);
    }

    /* ── Por producto: ventas − devoluciones, agregado en SQL ─────────── */

    /**
     * Una fila por producto con lo vendido y el costo NETOS de devoluciones.
     * Es la base de la tabla, los contadores y las categorías.
     */
    private function porProducto(int $empresa, string $desde, string $hasta, ?int $localId): Builder
    {
        $u = $this->utilidad;
        $vendido = $u->items($empresa, $desde, $hasta, $localId)
            // Unidades en la unidad BASE (sumar presentaciones distintas —caja
            // y unidad— daba un número sin sentido) y ventas netas del
            // descuento global prorrateado (así la tabla cuadra con el resumen).
            ->selectRaw('vi.producto_id, vi.cantidad_base as cantidad, ' . UtilidadService::lineaNeta('vi') . ' as ventas,
                         vi.cantidad_base * (' . $u->costo() . ') as costo');

        $devuelto = $u->devoluciones($empresa, $desde, $hasta, $localId)
            ->selectRaw("vi.producto_id, -dd.cantidad_base as cantidad, -({$u->devuelto()}) as ventas,
                         -({$u->recuperado()}) as costo");

        $agregado = DB::query()
            ->fromSub($vendido->unionAll($devuelto), 'm')
            ->join('productos as pr', 'pr.id', '=', 'm.producto_id')
            ->leftJoin('categorias as c', 'c.id', '=', 'pr.categoria_id')
            ->groupBy('m.producto_id', 'pr.nombre', 'pr.codigo', 'pr.categoria_id', 'c.nombre')
            ->selectRaw("m.producto_id, pr.nombre as producto_nombre, pr.codigo, pr.categoria_id,
                         COALESCE(c.nombre, 'Sin categoría') as categoria,
                         SUM(m.cantidad) as cantidad, SUM(m.ventas) as ventas, SUM(m.costo) as costo");

        // Envolver para poder filtrar y ordenar por utilidad y margen.
        return DB::query()->fromSub($agregado, 'x')->select('x.*')
            ->selectRaw('ROUND((x.ventas - x.costo)::numeric, 2) as utilidad')
            ->selectRaw('CASE WHEN x.ventas > 0 THEN ROUND(((x.ventas - x.costo) / x.ventas * 100)::numeric, 1) END as margen');
    }

    private function tabla(Builder $base, string $orden, ?string $vista, ?string $categoria, string $buscar)
    {
        $q = $this->enVista($base, $vista)
            ->when($categoria === 'sin', fn ($q) => $q->whereNull('x.categoria_id'))
            ->when($categoria && $categoria !== 'sin', fn ($q) => $q->where('x.categoria_id', (int) $categoria))
            ->when($buscar !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('x.producto_nombre', 'ilike', "%{$buscar}%")
                ->orWhere('x.codigo', 'ilike', "%{$buscar}%")));

        match ($orden) {
            'ventas'   => $q->orderByDesc('x.ventas'),
            'cantidad' => $q->orderByDesc('x.cantidad'),
            'margen'   => $q->orderByRaw('margen DESC NULLS LAST'),
            default    => $q->orderByDesc('utilidad'),
        };

        return $q->orderBy('x.producto_id')
            ->paginate(25)->withQueryString()
            ->through(fn ($r) => [
                'producto_id'     => $r->producto_id,
                'producto_nombre' => $r->producto_nombre,
                'categoria'       => $r->categoria,
                'cantidad'        => (float) $r->cantidad,
                'ventas'          => round((float) $r->ventas, 2),
                'costo'           => round((float) $r->costo, 2),
                'utilidad'        => (float) $r->utilidad,
                'margen'          => $r->margen !== null ? (float) $r->margen : null,
            ]);
    }

    /** Los 8 productos que más se vendieron (en soles), con lo que dejó cada uno. */
    private function masVendidos(Builder $base)
    {
        return $base->where('x.ventas', '>', 0)
            ->orderByDesc('x.ventas')->orderBy('x.producto_id')
            ->limit(8)->get()
            ->map(fn ($r) => [
                'producto_id'     => $r->producto_id,
                'producto_nombre' => $r->producto_nombre,
                'ventas'          => round((float) $r->ventas, 2),
                'costo'           => round((float) $r->costo, 2),
                'utilidad'        => (float) $r->utilidad,
                'margen'          => $r->margen !== null ? (float) $r->margen : null,
            ]);
    }

    /** Contadores del periodo completo: una sola consulta. */
    private function conteos(Builder $base): array
    {
        $margen = $this->margenPromedio(clone $base);
        $r = DB::query()->fromSub($base, 't')->selectRaw('
                COUNT(*) as total,
                COUNT(*) FILTER (WHERE t.ventas > 0 AND t.utilidad < 0) as perdida,
                COUNT(*) FILTER (WHERE t.ventas > 0 AND t.costo <= 0) as sincosto,
                COUNT(*) FILTER (WHERE t.margen >= 0 AND t.margen < ?) as bajo', [$margen ?? -1])
            ->first();

        return [
            'total'    => (int) $r->total,
            'perdida'  => (int) $r->perdida,
            'bajo'     => $margen === null ? 0 : (int) $r->bajo,
            'sincosto' => (int) $r->sincosto,
            'margen'   => $margen,
        ];
    }

    /** Utilidad por categoría del periodo completo (GROUP BY en SQL). */
    private function categorias(Builder $base)
    {
        return DB::query()->fromSub($base, 't')
            ->groupBy('t.categoria_id', 't.categoria')
            ->selectRaw('t.categoria_id, t.categoria as label, COUNT(*) as productos,
                         ROUND(SUM(t.ventas)::numeric, 2) as ventas, ROUND(SUM(t.utilidad)::numeric, 2) as utilidad')
            ->orderByDesc('utilidad')
            ->get()
            ->map(fn ($c) => [
                'id'        => $c->categoria_id === null ? 'sin' : (string) $c->categoria_id,
                'label'     => $c->label,
                'productos' => (int) $c->productos,
                'ventas'    => (float) $c->ventas,
                'utilidad'  => (float) $c->utilidad,
            ]);
    }

    private function enVista(Builder $q, ?string $vista): Builder
    {
        if ($vista === 'bajo') {
            $m = $this->margenPromedio(clone $q);
            return $m === null
                ? $q->whereRaw('false')
                : $q->whereRaw('x.ventas > 0 AND (x.ventas - x.costo) >= 0 AND (x.ventas - x.costo) / x.ventas * 100 < ?', [$m]);
        }

        return match ($vista) {
            'perdida'  => $q->where('x.ventas', '>', 0)->whereRaw('x.ventas - x.costo < 0'),
            'sincosto' => $q->where('x.ventas', '>', 0)->where('x.costo', '<=', 0),
            default    => $q,
        };
    }

    /** Margen de los productos del periodo, para "bajo tu promedio". */
    private function margenPromedio(Builder $base): ?float
    {
        $r = DB::query()->fromSub($base, 'mp')->selectRaw('SUM(mp.ventas) as v, SUM(mp.utilidad) as u')->first();
        return (float) $r->v > 0 ? round((float) $r->u / (float) $r->v * 100, 1) : null;
    }
}
