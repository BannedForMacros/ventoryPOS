<?php

namespace App\Http\Controllers\Reportes;

use App\Http\Controllers\Controller;
use App\Models\Devolucion;
use App\Models\Gasto;
use App\Models\Venta;
use App\Services\LocalScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Reporte de UTILIDAD: cuánto deja el negocio en un período.
 *
 *   UTILIDAD BRUTA = ventas (netas de descuentos) − costo de lo vendido
 *   UTILIDAD NETA  = bruta − gastos del período − devoluciones
 *
 * El costo de lo vendido usa el costo CONGELADO al momento de la venta
 * (venta_items.costo_unitario_base); para filas históricas sin snapshot cae
 * al criterio canónico del balance (precio_costo del producto, o el
 * costo_promedio real del stock). Todos los montos incluyen IGV, igual que
 * el Excel del cliente y el balance diario.
 */
class ReporteUtilidadController extends Controller
{
    public function __construct(private LocalScopeService $scope) {}

    /** Costo por unidad base: snapshot congelado → precio_costo → costo_promedio. */
    private const COSTO_SQL = "COALESCE(NULLIF(vi.costo_unitario_base, 0), NULLIF(p.precio_costo, 0),
        (SELECT s.costo_promedio FROM stock s WHERE s.producto_id = p.id AND s.costo_promedio > 0 ORDER BY s.id LIMIT 1), 0)";

    public function index(Request $request)
    {
        $user = $request->user();

        $desde = $request->fecha_desde ?: now()->startOfMonth()->toDateString();
        $hasta = $request->fecha_hasta ?: now()->toDateString();
        $localId = $request->local_id ?: $user->local_id; // cajera con local fijo: solo el suyo

        $ventasBase = Venta::deEmpresa($user->empresa_id)
            ->where('estado', 'completada')
            ->whereBetween('fecha_venta', [$desde . ' 00:00:00', $hasta . ' 23:59:59'])
            ->when($localId, fn ($q, $v) => $q->where('local_id', $v));

        // ── Ítems vendidos del rango, con costo congelado ────────────────
        $itemsBase = DB::table('venta_items as vi')
            ->join('ventas as v', 'v.id', '=', 'vi.venta_id')
            ->join('productos as p', 'p.id', '=', 'vi.producto_id')
            ->where('v.empresa_id', $user->empresa_id)
            ->where('v.estado', 'completada')
            ->whereBetween('v.fecha_venta', [$desde . ' 00:00:00', $hasta . ' 23:59:59'])
            ->when($localId, fn ($q, $v) => $q->where('v.local_id', $v));

        // ── KPIs globales ────────────────────────────────────────────────
        $ventasTotal = (float) (clone $ventasBase)->sum('total');
        $cogsTotal   = (float) (clone $itemsBase)
            ->selectRaw('COALESCE(SUM(vi.cantidad_base * ' . self::COSTO_SQL . '), 0) as c')
            ->value('c');

        $gastosTotal = (float) Gasto::deEmpresa($user->empresa_id)
            ->whereBetween('fecha', [$desde, $hasta])
            ->when($localId, fn ($q, $v) => $q->where('local_id', $v))
            ->sum('monto');

        $devolucionesTotal = (float) Devolucion::where('empresa_id', $user->empresa_id)
            ->whereIn('estado', ['aprobada', 'completada'])
            ->whereBetween('fecha', [$desde . ' 00:00:00', $hasta . ' 23:59:59'])
            ->when($localId, fn ($q, $v) => $q->where('local_id', $v))
            ->sum('monto_devolucion');

        $utilidadBruta = round($ventasTotal - $cogsTotal, 2);
        $utilidadNeta  = round($utilidadBruta - $gastosTotal - $devolucionesTotal, 2);

        $kpis = [
            'ventas'         => round($ventasTotal, 2),
            'costo'          => round($cogsTotal, 2),
            'utilidad_bruta' => $utilidadBruta,
            'margen_bruto'   => $ventasTotal > 0 ? round($utilidadBruta / $ventasTotal * 100, 1) : null,
            'gastos'         => round($gastosTotal, 2),
            'devoluciones'   => round($devolucionesTotal, 2),
            'utilidad_neta'  => $utilidadNeta,
            'margen_neto'    => $ventasTotal > 0 ? round($utilidadNeta / $ventasTotal * 100, 1) : null,
        ];

        // ── Serie diaria: ventas, costo, utilidad bruta y neta ──────────
        $ventasDia = (clone $ventasBase)
            ->selectRaw('DATE(fecha_venta) as dia, SUM(total) as total, COUNT(*) as ventas')
            ->groupBy('dia')->pluck('total', 'dia');

        $cogsDia = (clone $itemsBase)
            ->selectRaw('DATE(v.fecha_venta) as dia, SUM(vi.cantidad_base * ' . self::COSTO_SQL . ') as costo')
            ->groupBy('dia')->pluck('costo', 'dia');

        $gastosDia = Gasto::deEmpresa($user->empresa_id)
            ->whereBetween('fecha', [$desde, $hasta])
            ->when($localId, fn ($q, $v) => $q->where('local_id', $v))
            ->selectRaw('fecha as dia, SUM(monto) as total')
            ->groupBy('dia')->pluck('total', 'dia');

        $devolucionesDia = Devolucion::where('empresa_id', $user->empresa_id)
            ->whereIn('estado', ['aprobada', 'completada'])
            ->whereBetween('fecha', [$desde . ' 00:00:00', $hasta . ' 23:59:59'])
            ->when($localId, fn ($q, $v) => $q->where('local_id', $v))
            ->selectRaw('DATE(fecha) as dia, SUM(monto_devolucion) as total')
            ->groupBy('dia')->pluck('total', 'dia');

        $serie = [];
        for ($d = \Illuminate\Support\Carbon::parse($desde); $d->lte(\Illuminate\Support\Carbon::parse($hasta)); $d->addDay()) {
            $k = $d->toDateString();
            $venta  = (float) ($ventasDia[$k] ?? 0);
            $costo  = (float) ($cogsDia[$k] ?? 0);
            $gasto  = (float) ($gastosDia->first(fn ($v, $kk) => substr((string) $kk, 0, 10) === $k) ?? 0);
            $dev    = (float) ($devolucionesDia[$k] ?? 0);
            $bruta  = round($venta - $costo, 2);
            $serie[] = [
                'dia'     => $k,
                'ventas'  => round($venta, 2),
                'costo'   => round($costo, 2),
                'bruta'   => $bruta,
                'neta'    => round($bruta - $gasto - $dev, 2),
            ];
        }

        // ── Utilidad por producto (costo congelado por ítem) ────────────
        $orden = in_array($request->orden, ['utilidad', 'ventas', 'margen', 'cantidad'], true) ? $request->orden : 'utilidad';
        $productos = (clone $itemsBase)
            ->selectRaw('vi.producto_id,
                         MIN(vi.producto_nombre) as producto_nombre,
                         MIN(c.nombre) as categoria,
                         SUM(vi.cantidad) as cantidad,
                         SUM(vi.subtotal) as ventas,
                         SUM(vi.cantidad_base * ' . self::COSTO_SQL . ') as costo')
            ->leftJoin('categorias as c', 'c.id', '=', 'p.categoria_id')
            ->when($request->buscar, fn ($q, $v) => $q->where('vi.producto_nombre', 'ilike', "%{$v}%"))
            ->groupBy('vi.producto_id')
            ->get()
            ->map(function ($r) {
                $ventas   = (float) $r->ventas;
                $costo    = (float) $r->costo;
                $utilidad = round($ventas - $costo, 2);
                return [
                    'producto_id'     => $r->producto_id,
                    'producto_nombre' => $r->producto_nombre,
                    'categoria'       => $r->categoria ?? 'Sin categoría',
                    'cantidad'        => (float) $r->cantidad,
                    'ventas'          => round($ventas, 2),
                    'costo'           => round($costo, 2),
                    'utilidad'        => $utilidad,
                    'margen'          => $ventas > 0 ? round($utilidad / $ventas * 100, 1) : null,
                ];
            })
            ->sortByDesc(fn ($p) => match ($orden) {
                'ventas'   => $p['ventas'],
                'margen'   => $p['margen'] ?? -999,
                'cantidad' => $p['cantidad'],
                default    => $p['utilidad'],
            })
            ->values()
            ->take(150);

        // ── Utilidad por categoría (para la dona) ────────────────────────
        $categorias = $productos->groupBy('categoria')
            ->map(fn ($rows, $cat) => [
                'label'    => $cat,
                'ventas'   => round($rows->sum('ventas'), 2),
                'utilidad' => round($rows->sum('utilidad'), 2),
            ])
            ->sortByDesc('utilidad')
            ->values();

        return Inertia::render('Reportes/Utilidad', [
            'kpis'       => $kpis,
            'serie'      => $serie,
            'productos'  => $productos,
            'categorias' => $categorias,
            'locales'    => $this->scope->localesVisibles($user),
            'filters'    => [
                'fecha_desde' => $desde,
                'fecha_hasta' => $hasta,
                'local_id'    => $request->local_id,
                'orden'       => $orden,
                'buscar'      => $request->buscar,
            ],
        ]);
    }
}
