<?php

namespace App\Http\Controllers\Inventario;

use App\Support\Xlsx;

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

        // Última corrección automática del inventario (autocontrol nocturno), para
        // que se vea qué arregló el sistema sin que nadie apretara "Recalcular".
        $autocorreccion = \App\Models\Auditoria::deEmpresa($empresaId)
            ->where('accion', 'stock.autoreparado')
            ->where('created_at', '>=', now()->subDays(3))
            ->orderByDesc('created_at')
            ->first(['created_at', 'contexto']);

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
            'autocorreccion'       => $autocorreccion ? [
                'fecha'             => $autocorreccion->created_at->toIso8601String(),
                'stock_corregidos'  => (int) ($autocorreccion->contexto['stock_corregidos'] ?? 0),
                'kardex_corregidos' => (int) ($autocorreccion->contexto['kardex_corregidos'] ?? 0),
                'sin_respaldo'      => (int) ($autocorreccion->contexto['sin_respaldo'] ?? 0),
                'productos'         => collect($autocorreccion->contexto['detalle'] ?? [])
                    ->reject(fn ($d) => $d['solo_kardex'] ?? false)
                    ->take(8)
                    ->map(fn ($d) => [
                        'producto'         => $d['producto'] ?? ('#' . ($d['producto_id'] ?? '')),
                        'cantidad_antes'   => (float) ($d['cantidad_antes'] ?? 0),
                        'cantidad_despues' => (float) ($d['cantidad_despues'] ?? 0),
                        'sin_respaldo'     => (bool) ($d['sin_respaldo'] ?? false),
                    ])->values(),
            ] : null,
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

        $headers = ['Almacén', 'Producto', 'Código', 'Categoría', 'Unidad base', 'Cantidad', 'Costo promedio', 'Valor total', 'Estado'];
        $filas = [];
        foreach ($items as $s) {
            $cantidad = (float) $s->cantidad;
            $costo    = (float) $s->costo_promedio;
            $estado   = $cantidad < 0 ? 'Negativo' : ($cantidad === 0.0 ? 'Agotado' : ($cantidad <= $umbral ? 'Bajo' : 'Con stock'));
            $filas[] = [
                $s->almacen?->nombre ?? '—',
                $s->producto?->nombre ?? '—',
                $s->producto?->codigo ?? '—',
                $s->producto?->categoria?->nombre ?? '—',
                $s->producto?->unidadBase?->unidadMedida?->abreviatura ?? '—',
                $cantidad,
                $costo,
                round($cantidad * $costo, 2),
                $estado,
            ];
        }

        return Xlsx::descargar(
            $headers,
            $filas,
            'stock',
            [5 => true, 6 => '#,##0.0000', 7 => true],
        );
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

        // Motor único: stock y kardex salen del mismo cálculo y solo se escribe lo
        // que difiere. Ya no hay que resetear a 0 ni correr dos motores seguidos.
        $res = app(\App\Services\KardexService::class)->reconstruirAlmacenes($almacenIds);

        \App\Services\AuditoriaService::log('stock.recalculado', null, [
            'almacenes_afectados' => count($almacenIds),
            'combinaciones'       => $res['pares'],
            'kardex_corregidos'   => $res['kardex_corregidos'],
            'stock_corregidos'    => $res['stock_corregidos'],
            'sin_respaldo'        => $res['sin_respaldo'],
            'unidades_antes'      => $res['unidades_antes'],
            'unidades_despues'    => $res['unidades_despues'],
            'valor_antes'         => $res['valor_antes'],
            'valor_despues'       => $res['valor_despues'],
        ]);

        $mensaje = ($res['kardex_corregidos'] + $res['stock_corregidos']) === 0
            ? 'Stock y kardex revisados: ya estaban cuadrados con los documentos, no hubo nada que corregir.'
            : "Stock y kardex rearmados desde los documentos: {$res['stock_corregidos']} producto(s) corregido(s) en stock y {$res['kardex_corregidos']} en kardex.";

        if ($res['sin_respaldo'] > 0) {
            $mensaje .= " {$res['sin_respaldo']} producto(s) tienen stock sin ningún documento que lo respalde: no se tocaron, conviene revisarlos.";
        }

        return redirect()->back()->with('success', $mensaje);
    }
}
