<?php

namespace App\Http\Controllers\Reportes;

use App\Http\Controllers\Controller;
use App\Models\Gasto;
use App\Models\Venta;
use App\Services\CostoVentaService;
use App\Services\LocalScopeService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Reporte de UTILIDAD: cuánto deja el negocio en un período.
 *
 *   VENTAS NETAS   = ventas (ya sin descuentos) − dinero devuelto a clientes
 *   COSTO NETO     = costo de lo vendido − costo de lo que volvió al stock
 *   UTILIDAD BRUTA = ventas netas − costo neto
 *   UTILIDAD NETA  = bruta − gastos del período
 *
 * Una devolución NO es un gasto: deshace (parte de) una venta. Si el producto
 * vuelve al stock y se devuelve el dinero, venta y costo se anulan y el
 * patrimonio queda igual. Solo hay pérdida real cuando la mercadería vuelve
 * dañada (sin restock): se devuelve el dinero pero su costo no se recupera.
 * Solo cuentan devoluciones COMPLETADAS; "sin reembolso" no resta dinero.
 *
 * El costo usa el costo CONGELADO al vender (CostoVentaService::sql). Todos
 * los montos incluyen IGV, igual que el balance diario.
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

    public function __construct(private LocalScopeService $scope) {}

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
            'kpis'       => fn () => $this->kpis($empresa, $desde, $hasta, $localId),
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

    /* ── KPIs del periodo ─────────────────────────────────────────────── */

    private function kpis(int $empresa, string $desde, string $hasta, ?int $localId): array
    {
        $ventas = Venta::deEmpresa($empresa)
            ->where('estado', 'completada')
            ->whereBetween('fecha_venta', [$desde . ' 00:00:00', $hasta . ' 23:59:59'])
            ->when($localId, fn ($q, $v) => $q->where('local_id', $v));
        $items = $this->items($empresa, $desde, $hasta, $localId);

        $ventasTotal = (float) (clone $ventas)->sum('total');
        $cogsTotal   = (float) (clone $items)->selectRaw('COALESCE(SUM(vi.cantidad_base * ' . $this->costo() . '), 0) as c')->value('c');
        $descuentos  = (float) (clone $ventas)->sum('descuento_total')
            + (float) (clone $items)->selectRaw('COALESCE(SUM(vi.descuento_item * vi.cantidad), 0) as d')->value('d');

        $gastos = (float) Gasto::deEmpresa($empresa)
            ->whereBetween('fecha', [$desde, $hasta])
            ->when($localId, fn ($q, $v) => $q->where('local_id', $v))
            ->sum('monto');

        $dev = $this->devoluciones($empresa, $desde, $hasta, $localId)
            ->selectRaw("COALESCE(SUM({$this->devuelto()}), 0) as devuelto,
                         COALESCE(SUM({$this->recuperado()}), 0) as recuperado,
                         COALESCE(SUM(CASE WHEN NOT dd.restock AND d.forma_reembolso <> 'sin_reembolso'
                                           THEN dd.cantidad_base * ({$this->costo()}) ELSE 0 END), 0) as danado")
            ->first();

        $ventasNeta = $ventasTotal - (float) $dev->devuelto;
        $costoNeto  = $cogsTotal - (float) $dev->recuperado;
        $bruta      = round($ventasNeta - $costoNeto, 2);
        $neta       = round($bruta - $gastos, 2);

        return [
            'ventas'         => round($ventasNeta, 2),
            'costo'          => round($costoNeto, 2),
            'utilidad_bruta' => $bruta,
            'margen_bruto'   => $ventasNeta > 0 ? round($bruta / $ventasNeta * 100, 1) : null,
            'descuentos'     => round($descuentos, 2),
            'gastos'         => round($gastos, 2),
            // Devoluciones del periodo, ya restadas arriba (solo informativo):
            'devuelto'       => round((float) $dev->devuelto, 2),   // dinero devuelto a clientes
            'recuperado'     => round((float) $dev->recuperado, 2), // costo de lo que volvió al stock
            'costo_danado'   => round((float) $dev->danado, 2),     // devuelto dañado: pérdida real
            'utilidad_neta'  => $neta,
            'margen_neto'    => $ventasNeta > 0 ? round($neta / $ventasNeta * 100, 1) : null,
        ];
    }

    /* ── Por producto: ventas − devoluciones, agregado en SQL ─────────── */

    /**
     * Una fila por producto con lo vendido y el costo NETOS de devoluciones.
     * Es la base de la tabla, los contadores y las categorías.
     */
    private function porProducto(int $empresa, string $desde, string $hasta, ?int $localId): Builder
    {
        $vendido = $this->items($empresa, $desde, $hasta, $localId)
            ->selectRaw('vi.producto_id, vi.cantidad as cantidad, vi.subtotal as ventas,
                         vi.cantidad_base * (' . $this->costo() . ') as costo');

        $devuelto = $this->devoluciones($empresa, $desde, $hasta, $localId)
            ->selectRaw("vi.producto_id, -dd.cantidad as cantidad, -({$this->devuelto()}) as ventas,
                         -({$this->recuperado()}) as costo");

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

    /* ── Piezas de consulta ───────────────────────────────────────────── */

    /** Ítems vendidos del rango (ventas completadas). */
    private function items(int $empresa, string $desde, string $hasta, ?int $localId): Builder
    {
        return DB::table('venta_items as vi')
            ->join('ventas as v', 'v.id', '=', 'vi.venta_id')
            ->join('productos as p', 'p.id', '=', 'vi.producto_id')
            ->where('v.empresa_id', $empresa)
            ->where('v.estado', 'completada')
            ->whereBetween('v.fecha_venta', [$desde . ' 00:00:00', $hasta . ' 23:59:59'])
            ->when($localId, fn ($q, $v) => $q->where('v.local_id', $v));
    }

    /** Líneas de devoluciones completadas del rango, con su ítem de venta original. */
    private function devoluciones(int $empresa, string $desde, string $hasta, ?int $localId): Builder
    {
        return DB::table('devoluciones_detalle as dd')
            ->join('devoluciones as d', 'd.id', '=', 'dd.devolucion_id')
            ->join('venta_items as vi', 'vi.id', '=', 'dd.venta_item_id')
            ->join('ventas as v', 'v.id', '=', 'vi.venta_id')
            ->join('productos as p', 'p.id', '=', 'vi.producto_id')
            ->where('d.empresa_id', $empresa)
            ->where('d.estado', 'completada')
            ->whereBetween('d.fecha', [$desde . ' 00:00:00', $hasta . ' 23:59:59'])
            ->when($localId, fn ($q, $v) => $q->where('d.local_id', $v));
    }

    /** Costo por unidad base de cada línea: regla única. */
    private function costo(): string
    {
        return CostoVentaService::sql('vi', 'p');
    }

    /** Dinero devuelto por una línea de devolución (0 si fue sin reembolso). */
    private function devuelto(): string
    {
        return "CASE WHEN d.forma_reembolso <> 'sin_reembolso' THEN dd.subtotal ELSE 0 END";
    }

    /** Costo de lo que volvió al stock en una línea de devolución. */
    private function recuperado(): string
    {
        return 'CASE WHEN dd.restock THEN dd.cantidad_base * (' . $this->costo() . ') ELSE 0 END';
    }
}
