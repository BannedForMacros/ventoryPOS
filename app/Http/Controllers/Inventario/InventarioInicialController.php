<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Controller;
use App\Models\Almacen;
use App\Models\Categoria;
use App\Models\Producto;
use App\Services\AuditoriaService;
use App\Services\InventarioInicialService;
use App\Services\LocalScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Pantalla "Inventario inicial": el stock contado con el que arranca cada
 * producto. Se carga a mano (solo los que ya contaste; el resto queda "sin
 * cargar") o subiendo un Excel y diciendo qué columna es qué.
 */
class InventarioInicialController extends Controller
{
    public function __construct(
        private LocalScopeService $scope,
        private InventarioInicialService $inicial,
    ) {}

    public function index(Request $request)
    {
        $user      = $request->user();
        $almacenes = $this->scope->almacenesVisibles($user);
        $almacenId = (int) ($request->integer('almacen_id') ?: $almacenes->first()?->id);
        abort_unless($almacenes->contains('id', $almacenId), 403);

        $base = $this->productosConStock($user->empresa_id);

        $resumen = DB::table('stock_iniciales')
            ->where('almacen_id', $almacenId)
            ->whereIn('producto_id', (clone $base)->select('id'))
            ->selectRaw('COUNT(*) as cargados, COALESCE(SUM(cantidad * costo), 0) as valor,
                         COUNT(*) FILTER (WHERE costo <= 0) as sin_costo, MAX(fecha) as ultima_fecha')
            ->first();

        $lista = (clone $base)
            ->leftJoin('stock_iniciales as si', fn ($j) => $j->on('si.producto_id', '=', 'productos.id')->where('si.almacen_id', $almacenId))
            ->leftJoin('stock as s', fn ($j) => $j->on('s.producto_id', '=', 'productos.id')->where('s.almacen_id', $almacenId))
            ->leftJoin('categorias as c', 'c.id', '=', 'productos.categoria_id')
            ->when($request->string('q')->trim()->value(), fn ($q, $s) => $q->where(fn ($w) => $w
                ->where('productos.nombre', 'ilike', '%' . str_replace(' ', '%', $s) . '%')
                ->orWhere('productos.codigo', 'ilike', "%{$s}%")))
            ->when($request->categoria_id, fn ($q, $v) => $q->where('productos.categoria_id', $v))
            ->when($request->estado === 'pendientes', fn ($q) => $q->whereNull('si.id'))
            ->when($request->estado === 'cargados', fn ($q) => $q->whereNotNull('si.id'))
            ->orderBy('productos.nombre')
            ->select([
                'productos.id', 'productos.codigo', 'productos.nombre', 'c.nombre as categoria',
                's.cantidad as stock', 's.costo_promedio',
                'si.cantidad as ini_cantidad', 'si.costo as ini_costo', 'si.fecha as ini_fecha',
            ])
            ->paginate(50)->withQueryString();

        $unidades = DB::table('producto_unidades as pu')
            ->join('unidades_medida as um', 'um.id', '=', 'pu.unidad_medida_id')
            ->whereIn('pu.producto_id', $lista->pluck('id'))->where('pu.es_base', true)
            ->pluck('um.abreviatura', 'pu.producto_id');

        $lista->through(fn ($p) => [
            'id'        => $p->id,
            'codigo'    => $p->codigo,
            'nombre'    => $p->nombre,
            'categoria' => $p->categoria,
            'unidad'    => $unidades[$p->id] ?? 'und',
            'stock'     => (float) ($p->stock ?? 0),
            'costo'     => (float) ($p->costo_promedio ?? 0),
            'inicial'   => $p->ini_fecha === null ? null : [
                'cantidad' => (float) $p->ini_cantidad,
                'costo'    => (float) $p->ini_costo,
                'fecha'    => substr((string) $p->ini_fecha, 0, 10),
            ],
        ]);

        return Inertia::render('Inventario/Inicial', [
            'almacenes'  => $almacenes->map(fn ($a) => ['id' => $a->id, 'nombre' => $a->nombre])->values(),
            'almacenId'  => $almacenId,
            'categorias' => Categoria::where('empresa_id', $user->empresa_id)->orderBy('nombre')->get(['id', 'nombre']),
            'resumen'    => [
                'productos'    => (clone $base)->count(),
                'cargados'     => (int) $resumen->cargados,
                'valor'        => round((float) $resumen->valor, 2),
                'sin_costo'    => (int) $resumen->sin_costo,
                'ultima_fecha' => $resumen->ultima_fecha ? substr((string) $resumen->ultima_fecha, 0, 10) : null,
            ],
            'productos'  => $lista,
            'filters'    => $request->only(['q', 'estado', 'categoria_id', 'almacen_id']),
            'puede'      => ['editar' => $user->tienePermiso('inventario.inicial', 'editar')],
        ]);
    }

    /** Guarda lo cargado a mano o lo confirmado del Excel. */
    public function guardar(Request $request)
    {
        $data = $request->validate([
            'almacen_id'                 => ['required', 'integer'],
            'fecha'                      => ['required', 'date', 'before_or_equal:today'],
            'origen'                     => ['nullable', 'in:manual,excel'],
            'items'                      => ['required', 'array', 'min:1', 'max:5000'],
            'items.*.producto_id'        => ['required', 'integer'],
            'items.*.cantidad'           => ['required', 'numeric', 'min:0'],
            'items.*.producto_unidad_id' => ['nullable', 'integer'],
            'items.*.costo'              => ['nullable', 'numeric', 'min:0'],
        ], [
            'fecha.before_or_equal' => 'La fecha del conteo no puede ser futura.',
            'items.*.cantidad.min'  => 'La cantidad no puede ser negativa.',
        ]);

        $user    = $request->user();
        $almacen = $this->almacen($request, (int) $data['almacen_id']);

        @set_time_limit(300);   // un Excel grande reconstruye cada producto
        $res = $this->inicial->guardar($user->empresa_id, $almacen->id, $data['fecha'], $data['items']);

        AuditoriaService::log('inventario_inicial.cargado', $almacen, [
            'origen'    => $data['origen'] ?? 'manual',
            'fecha'     => $data['fecha'],
            'productos' => $res['guardados'],
        ], $user);

        $msg = $res['guardados'] === 1
            ? 'Inventario inicial guardado para 1 producto.'
            : "Inventario inicial guardado para {$res['guardados']} productos.";
        if ($res['sin_costo'] > 0) {
            $msg .= " {$res['sin_costo']} quedaron sin costo: complétalo para que el valor del inventario sea real.";
        }

        return back()->with('success', $msg);
    }

    public function quitar(Request $request, Producto $producto)
    {
        abort_unless($producto->empresa_id === $request->user()->empresa_id, 404);
        $almacen = $this->almacen($request, $request->integer('almacen_id'));

        if ($this->inicial->quitar($almacen->id, $producto->id)) {
            AuditoriaService::log('inventario_inicial.quitado', $producto, ['almacen_id' => $almacen->id], $request->user());
        }

        return back()->with('success', "Se quitó el inventario inicial de {$producto->nombre}.");
    }

    /** Filas del Excel (ya con las columnas elegidas) → producto del catálogo. */
    public function emparejar(Request $request)
    {
        $data = $request->validate([
            'almacen_id' => ['required', 'integer'],
            'filas'      => ['required', 'array', 'min:1', 'max:5000'],
            'filas.*.fila' => ['required', 'integer'],
        ]);
        $almacen = $this->almacen($request, (int) $data['almacen_id']);

        return response()->json([
            'filas' => $this->inicial->emparejar($request->user()->empresa_id, $almacen->id, $request->input('filas')),
        ]);
    }

    public function buscar(Request $request)
    {
        return response()->json([
            'productos' => $this->inicial->buscar($request->user()->empresa_id, (string) $request->query('q', '')),
        ]);
    }

    // ── Auxiliares ──────────────────────────────────────────────────────────

    /** Productos físicos activos que llevan stock (los servicios no se cuentan). */
    private function productosConStock(int $empresaId)
    {
        // Columnas calificadas: la lista le suma joins (stock, categorías…).
        return Producto::query()
            ->where('productos.empresa_id', $empresaId)
            ->where('productos.tipo', 'producto')
            ->where('productos.activo', true)
            ->where(fn ($q) => $q->whereNull('productos.controla_stock')->orWhere('productos.controla_stock', true));
    }

    private function almacen(Request $request, int $almacenId): Almacen
    {
        $almacen = Almacen::findOrFail($almacenId);
        abort_unless($this->scope->puedeAccederAlmacen($request->user(), $almacen), 403);

        return $almacen;
    }
}
