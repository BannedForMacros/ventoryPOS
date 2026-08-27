<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Controller;
use App\Models\Almacen;
use App\Models\Categoria;
use App\Models\Entrada;
use App\Models\Stock;
use App\Services\LocalScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class StockController extends Controller
{
    public function __construct(private LocalScopeService $scope) {}

    /** Umbral de "stock bajo" (no hay mínimo por producto en el catálogo). */
    private const UMBRAL_BAJO = 5;

    /**
     * Query base de stock para los almacenes visibles del usuario, aplicando
     * los filtros comunes de almacén, categoría y búsqueda. Se reutiliza en
     * index(), exportar() y los KPIs.
     */
    private function queryBase(Request $request, ?array $almacenIds = null): \Illuminate\Database\Eloquent\Builder
    {
        $ids = $almacenIds ?? $this->scope->almacenIdsVisibles($request->user());

        return Stock::whereIn('almacen_id', $ids)
            ->when($request->almacen_id, fn ($q, $id) => $q->where('almacen_id', $id))
            ->when($request->categoria_id, fn ($q, $id) =>
                $q->whereHas('producto', fn ($p) => $p->where('categoria_id', $id)))
            ->when($request->busqueda, fn ($q, $s) =>
                $q->whereHas('producto', fn ($p) =>
                    $p->where('nombre', 'ilike', "%{$s}%")->orWhere('codigo', 'ilike', "%{$s}%")));
    }

    public function index(Request $request)
    {
        $user       = $request->user();
        $empresaId  = $user->empresa_id;
        $almacenes  = $this->scope->almacenesVisibles($user);
        $almacenIds = $almacenes->pluck('id')->toArray();

        // Base: alcance de almacenes + filtros que también afectan los KPIs
        // (almacén, categoría, búsqueda). El filtro de "estado" NO va aquí para
        // que los KPIs muestren siempre el desglose completo del ámbito.
        $base = $this->queryBase($request, $almacenIds);

        // KPIs de una sola consulta agregada (nada de traer todas las filas).
        $umbral = self::UMBRAL_BAJO;
        $kpis = (clone $base)->selectRaw("
            COALESCE(SUM(cantidad * costo_promedio), 0) as valor,
            COUNT(*) as total,
            COUNT(*) FILTER (WHERE cantidad = 0)                          as agotados,
            COUNT(*) FILTER (WHERE cantidad > 0 AND cantidad <= {$umbral}) as bajos,
            COUNT(*) FILTER (WHERE cantidad < 0)                          as negativos
        ")->first();

        // Orden server-side (default: nombre del producto).
        $dir  = $request->input('dir') === 'desc' ? 'desc' : 'asc';
        $sort = $request->input('sort', 'nombre');

        $lista = (clone $base)
            ->with(['producto.unidadBase.unidadMedida', 'producto.categoria:id,nombre', 'almacen.local'])
            ->when($request->estado, function ($q, $e) use ($umbral) {
                match ($e) {
                    'con_stock' => $q->where('cantidad', '>', 0),
                    'agotado'   => $q->where('cantidad', 0),
                    'bajo'      => $q->where('cantidad', '>', 0)->where('cantidad', '<=', $umbral),
                    'negativo'  => $q->where('cantidad', '<', 0),
                    default     => $q,
                };
            });

        match ($sort) {
            'cantidad' => $lista->orderBy('cantidad', $dir),
            'costo'    => $lista->orderBy('costo_promedio', $dir),
            'valor'    => $lista->orderByRaw("(cantidad * costo_promedio) {$dir}"),
            default    => $lista->orderBy(
                DB::table('productos')->select('nombre')->whereColumn('productos.id', 'stock.producto_id'),
                $dir,
            ),
        };

        $stocks = $lista->paginate(50)->withQueryString()->through(fn ($s) => [
            'id'             => $s->id,
            'almacen_id'     => $s->almacen_id,
            'almacen'        => $s->almacen,
            'producto_id'    => $s->producto_id,
            'producto'       => $s->producto,
            'cantidad'       => (float) $s->cantidad,
            'costo_promedio' => (float) $s->costo_promedio,
            'valor_total'    => round((float) $s->cantidad * (float) $s->costo_promedio, 2),
            'es_negativo'    => (float) $s->cantidad < 0,
        ]);

        // Stocks negativos en los almacenes visibles (SIN el filtro de búsqueda:
        // el banner debe listar SIEMPRE todos los negativos, exactos, con su
        // cantidad y almacén, para que el usuario sepa cuáles son sin buscarlos).
        $stocksNegativos = Stock::whereIn('almacen_id', $almacenIds)
            ->negativo()
            ->with(['producto:id,nombre,codigo', 'almacen:id,nombre'])
            ->orderBy('cantidad') // el más negativo primero
            ->get()
            ->map(fn ($s) => [
                'producto_id' => $s->producto_id,
                'producto'    => $s->producto?->nombre ?? '—',
                'codigo'      => $s->producto?->codigo,
                'almacen'     => $s->almacen?->nombre ?? '—',
                'cantidad'    => (float) $s->cantidad,
            ])
            ->values();

        return Inertia::render('Inventario/Stock', [
            'stocks'               => $stocks,
            'almacenes'            => $almacenes,
            'categorias'           => Categoria::deEmpresa($empresaId)->activo()
                                        ->orderBy('nombre')->get(['id', 'nombre']),
            'kpis'                 => [
                'valor'     => round((float) $kpis->valor, 2),
                'total'     => (int) $kpis->total,
                'agotados'  => (int) $kpis->agotados,
                'bajos'     => (int) $kpis->bajos,
                'negativos' => (int) $kpis->negativos,
            ],
            'umbralBajo'           => self::UMBRAL_BAJO,
            'mostrarSelector'      => $this->scope->mostrarSelectorLocal($user),
            'filters'              => $request->only(['almacen_id', 'busqueda', 'categoria_id', 'estado', 'sort', 'dir']),
            'stocksNegativosCount' => $stocksNegativos->count(),
            'stocksNegativos'      => $stocksNegativos,
            // Habilita el botón "Ajustar" por fila (permiso inventario.ajustes → editar).
            'puede'                => ['ajustar' => $user->tienePermiso('inventario.ajustes', 'editar')],
        ]);
    }

    /**
     * Exporta el stock actual a CSV (Excel) respetando los filtros activos.
     * Se descarga TODO el resultado filtrado, sin paginar.
     */
    public function exportar(Request $request)
    {
        $user       = $request->user();
        $almacenes  = $this->scope->almacenesVisibles($user);
        $almacenIds = $almacenes->pluck('id')->toArray();
        $umbral     = self::UMBRAL_BAJO;

        $dir  = $request->input('dir') === 'desc' ? 'desc' : 'asc';
        $sort = $request->input('sort', 'nombre');

        $query = $this->queryBase($request, $almacenIds)
            ->with(['producto.unidadBase.unidadMedida', 'producto.categoria:id,nombre', 'almacen.local'])
            ->when($request->estado, function ($q, $e) use ($umbral) {
                match ($e) {
                    'con_stock' => $q->where('cantidad', '>', 0),
                    'agotado'   => $q->where('cantidad', 0),
                    'bajo'      => $q->where('cantidad', '>', 0)->where('cantidad', '<=', $umbral),
                    'negativo'  => $q->where('cantidad', '<', 0),
                    default     => $q,
                };
            });

        match ($sort) {
            'cantidad' => $query->orderBy('cantidad', $dir),
            'costo'    => $query->orderBy('costo_promedio', $dir),
            'valor'    => $query->orderByRaw("(cantidad * costo_promedio) {$dir}"),
            default    => $query->orderBy(
                DB::table('productos')->select('nombre')->whereColumn('productos.id', 'stock.producto_id'),
                $dir,
            ),
        };

        $items = $query->get();

        $csv = "\xEF\xBB\xBF"; // BOM UTF-8
        $headers = ['Almacén', 'Producto', 'Código', 'Categoría', 'Unidad base', 'Cantidad', 'Costo promedio', 'Valor total', 'Estado'];
        $csv .= implode(',', array_map($this->escaparCsv(...), $headers)) . "\n";

        foreach ($items as $s) {
            $cantidad = (float) $s->cantidad;
            $estado   = $cantidad < 0 ? 'Negativo' : ($cantidad === 0.0 ? 'Agotado' : ($cantidad <= $umbral ? 'Bajo' : 'Con stock'));
            $row = [
                $s->almacen?->nombre ?? '—',
                $s->producto?->nombre ?? '—',
                $s->producto?->codigo ?? '—',
                $s->producto?->categoria?->nombre ?? '—',
                $s->producto?->unidadBase?->unidadMedida?->abreviatura ?? '—',
                number_format($cantidad, 2, '.', ''),
                number_format((float) $s->costo_promedio, 4, '.', ''),
                number_format(round($cantidad * (float) $s->costo_promedio, 2), 2, '.', ''),
                $estado,
            ];
            $csv .= implode(',', array_map($this->escaparCsv(...), $row)) . "\n";
        }

        $filename = 'stock_' . now()->format('Ymd_His') . '.csv';

        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function escaparCsv(string $value): string
    {
        $escaped = str_replace('"', '""', $value);
        return '"' . $escaped . '"';
    }

    /**
     * Reconstruye el stock de TODOS los productos en los almacenes visibles
     * sumando todos los movimientos confirmados del sistema (entradas, salidas,
     * transferencias, ventas, devoluciones-restock y cierres de inventario).
     *
     * Solo el admin debería ver el botón; la autorización formal vive en el
     * middleware `permiso:inventario.stock,editar` aplicado a la ruta.
     */
    public function recalcular(Request $request)
    {
        $almacenIds = $this->scope->almacenIdsVisibles($request->user());

        $stats = DB::transaction(function () use ($almacenIds) {
            $pares = Stock::combinacionesConMovimientos($almacenIds);

            // Snapshot de cantidades antes del reseteo para auditoria
            $stockPrevio = Stock::whereIn('almacen_id', $almacenIds)
                ->selectRaw('SUM(cantidad) as total_unidades, SUM(cantidad * costo_promedio) as valor')
                ->first();

            // Para no dejar registros con cantidad inflada de un escenario previo,
            // reseteamos solo los almacenes visibles a 0 y luego reconstruimos los pares.
            Stock::whereIn('almacen_id', $almacenIds)
                ->update(['cantidad' => 0, 'costo_promedio' => 0]);

            foreach ($pares as $p) {
                Stock::reconstruir($p['almacen_id'], $p['producto_id']);
            }

            $stockPosterior = Stock::whereIn('almacen_id', $almacenIds)
                ->selectRaw('SUM(cantidad) as total_unidades, SUM(cantidad * costo_promedio) as valor')
                ->first();

            return [
                'almacenes_afectados' => count($almacenIds),
                'combinaciones'       => $pares->count(),
                'unidades_antes'      => (float) ($stockPrevio?->total_unidades ?? 0),
                'unidades_despues'    => (float) ($stockPosterior?->total_unidades ?? 0),
                'valor_antes'         => round((float) ($stockPrevio?->valor ?? 0), 2),
                'valor_despues'       => round((float) ($stockPosterior?->valor ?? 0), 2),
            ];
        });

        // Regenerar también el KARDEX desde los documentos finales: el ledger en
        // vivo acumula ruido operativo (entrada_reverso / entrada_edicion de cada
        // edición); reconstruirlo lo deja limpio, cronológico y cuadrado con el
        // stock recién recalculado (apertura del corte + movimientos posteriores).
        \Illuminate\Support\Facades\Artisan::call('kardex:reconstruir', [
            '--empresa' => $request->user()->empresa_id,
        ]);

        \App\Services\AuditoriaService::log('stock.recalculado', null, $stats);

        return redirect()->back()->with('success',
            'Stock y kardex reconstruidos: inventario inicial + entradas, salidas, transferencias, ventas, entregas de pendientes, devoluciones y cierres confirmados.');
    }
}
