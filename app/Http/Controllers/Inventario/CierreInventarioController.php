<?php

namespace App\Http\Controllers\Inventario;

use App\Console\Commands\ReconstruirKardex;
use App\Http\Controllers\Controller;
use App\Models\Almacen;
use App\Models\CierreInventario;
use App\Models\Producto;
use App\Models\Stock;
use App\Models\Turno;
use App\Services\AuditoriaService;
use App\Services\ConfiguracionOperacionService;
use App\Services\LocalScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CierreInventarioController extends Controller
{
    public function __construct(
        private LocalScopeService $scope,
        private ConfiguracionOperacionService $config,
    ) {}

    public function index(Request $request)
    {
        $user       = $request->user();
        $almacenIds = $this->scope->almacenIdsVisibles($user);

        $cierres = CierreInventario::whereIn('almacen_id', $almacenIds)
            ->with(['almacen.local', 'user'])
            ->when($request->almacen_id, fn ($q, $id) => $q->where('almacen_id', $id))
            ->when($request->estado, fn ($q, $e) => $q->where('estado', $e))
            ->when($request->fecha_desde, fn ($q, $f) => $q->whereDate('fecha', '>=', $f))
            ->when($request->fecha_hasta, fn ($q, $f) => $q->whereDate('fecha', '<=', $f))
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get();

        return Inertia::render('Inventario/Cierres/Index', [
            'cierres'         => $cierres,
            'almacenes'       => $this->scope->almacenesVisibles($user),
            'mostrarSelector' => $this->scope->mostrarSelectorLocal($user),
            'filters'         => $request->only(['almacen_id', 'estado', 'fecha_desde', 'fecha_hasta']),
        ]);
    }

    public function create(Request $request)
    {
        $user = $request->user();

        // Si viene desde el flujo de cierre de turno, pre-seleccionar el almacén del local
        $turnoId         = $request->query('turno_id');
        $almacenSugerido = null;

        if ($turnoId) {
            $turno = Turno::where('id', $turnoId)
                ->where('user_id', $user->id)
                ->where('estado', 'abierto')
                ->first();

            if ($turno) {
                $almacenSugerido = $this->scope->almacenParaVentas($user)?->id;
            }
        }

        return Inertia::render('Inventario/Cierres/Create', [
            'almacenes'        => $this->scope->almacenesVisibles($user),
            'mostrarSelector'  => $this->scope->mostrarSelectorLocal($user),
            'turnoId'          => $turnoId ? (int) $turnoId : null,
            'almacenSugerido'  => $almacenSugerido,
            // Modo lógico: precarga el stock del sistema y editas solo lo que difiere.
            'precarga'         => $this->config->cierrePrecargaStock($user->empresa_id),
        ]);
    }

    /**
     * Devuelve la lista de productos del almacén con su stock actual.
     * Usado en la pantalla Create para llenar la lista a declarar.
     */
    public function productosParaDeclarar(Request $request)
    {
        $user      = $request->user();
        $almacenId = (int) $request->query('almacen_id');

        $almacen = Almacen::findOrFail($almacenId);
        abort_unless($this->scope->puedeAccederAlmacen($user, $almacen), 403);

        $productos = Producto::deEmpresa($user->empresa_id)
            ->activo()
            ->productos()
            ->with('categoria:id,nombre')
            ->orderBy('nombre')
            ->get(['id', 'codigo', 'nombre', 'categoria_id']);

        $stocks = Stock::where('almacen_id', $almacenId)
            ->whereIn('producto_id', $productos->pluck('id'))
            ->get(['producto_id', 'cantidad', 'costo_promedio'])
            ->keyBy('producto_id');

        $resultado = $productos->map(fn ($p) => [
            'id'            => $p->id,
            'codigo'        => $p->codigo,
            'nombre'        => $p->nombre,
            'categoria'     => $p->categoria?->nombre,
            'categoria_id'  => $p->categoria_id,
            'stock_sistema' => (float) ($stocks[$p->id]->cantidad ?? 0),
            'costo'         => (float) ($stocks[$p->id]->costo_promedio ?? 0),
        ]);

        return response()->json([
            'productos' => $resultado,
            'precarga'  => $this->config->cierrePrecargaStock($user->empresa_id),
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'almacen_id'  => 'required|exists:almacenes,id',
            'turno_id'    => 'nullable|exists:turnos,id',
            'fecha'       => 'required|date',
            'observacion' => 'nullable|string',
            'items'       => 'required|array|min:1',
            'items.*.producto_id'     => 'required|exists:productos,id',
            // stock_sistema lo recalcula el servidor al guardar (fuente de verdad).
            'items.*.stock_sistema'   => 'nullable|numeric',
            'items.*.stock_declarado' => 'required|numeric|min:0',
            'items.*.observacion'     => 'nullable|string',
        ]);

        $almacen = Almacen::findOrFail($data['almacen_id']);
        abort_unless($this->scope->puedeAccederAlmacen($user, $almacen), 403);

        // Si trae turno_id: validar que el turno sea del usuario y esté abierto,
        // y que el almacén corresponda al local del turno (no se permite asociar el central a un turno).
        $turnoId = $data['turno_id'] ?? null;
        if ($turnoId) {
            $turno = Turno::where('id', $turnoId)
                ->where('user_id', $user->id)
                ->where('estado', 'abierto')
                ->firstOrFail();

            if ($almacen->local_id !== $turno->local_id) {
                return back()->withErrors([
                    'almacen_id' => 'El cierre asociado a un turno solo puede aplicar al almacén del local del turno.',
                ]);
            }
        }

        $cierre = DB::transaction(function () use ($data, $user, $turnoId, $request) {
            $cierre = CierreInventario::create([
                'empresa_id'  => $user->empresa_id,
                'almacen_id'  => $data['almacen_id'],
                'user_id'     => $user->id,
                'turno_id'    => $turnoId,
                'fecha'       => $data['fecha'],
                'estado'      => 'borrador',
                'observacion' => $data['observacion'] ?? null,
                'total_items' => count($data['items']),
            ]);

            // El stock del sistema se toma del stock REAL al momento de guardar
            // (no del que trajo el form): entre cargar y enviar pudo cambiar. Un
            // cierre nuevo aún no aplicó nada, así que ese stock es el "limpio".
            $stocks = $this->stockActual($data['almacen_id'], collect($data['items'])->pluck('producto_id'));

            foreach ($data['items'] as $i) {
                $sistema = (float) ($stocks[$i['producto_id']] ?? 0);
                $cierre->items()->create([
                    'producto_id'     => $i['producto_id'],
                    'stock_sistema'   => $sistema,
                    'stock_declarado' => $i['stock_declarado'],
                    'diferencia'      => round((float) $i['stock_declarado'] - $sistema, 4),
                    'observacion'     => $i['observacion'] ?? null,
                ]);
            }

            if ($request->boolean('confirmar')) {
                $cierre->confirmar();
                $this->reconstruirProductos($cierre->almacen, collect($data['items'])->pluck('producto_id'));
            }

            return $cierre;
        });

        AuditoriaService::log('cierre_inventario.creado', $cierre, [
            'items' => count($data['items']),
            'estado' => $cierre->estado,
        ], $user);

        return redirect()->route('inventario.cierres.show', $cierre->id)
            ->with('success', 'Cierre de inventario registrado.');
    }

    /**
     * Pantalla de edición: carga el ÚLTIMO estado del cierre y refresca el stock
     * del sistema "limpio" (sin el efecto de ESTE cierre), para que las diferencias
     * reflejen la realidad actual (p.ej. tras corregir una venta).
     */
    public function edit(Request $request, CierreInventario $cierre)
    {
        $user = $request->user();
        abort_unless($this->scope->puedeAccederAlmacen($user, $cierre->almacen), 403);
        abort_if($cierre->estado === 'anulado', 422, 'Un cierre anulado no se edita; crea uno nuevo.');

        $cierre->load(['almacen.local', 'items.producto:id,codigo,nombre']);
        $limpio = $this->stockSistemaLimpio($cierre);
        $costos = $this->costos($cierre->almacen_id, $cierre->items->pluck('producto_id'));

        $items = $cierre->items->map(function ($it) use ($limpio, $costos) {
            $sistema = (float) ($limpio[$it->producto_id] ?? 0);
            return [
                'producto_id'     => $it->producto_id,
                'codigo'          => $it->producto?->codigo,
                'nombre'          => $it->producto?->nombre ?? '—',
                'stock_sistema'   => $sistema,                                   // limpio, fresco
                'stock_declarado' => (float) $it->stock_declarado,
                'diferencia'      => round((float) $it->stock_declarado - $sistema, 4),
                'costo'           => (float) ($costos[$it->producto_id] ?? 0),
                'observacion'     => $it->observacion,
            ];
        })->values();

        return Inertia::render('Inventario/Cierres/Edit', [
            'cierre'  => [
                'id' => $cierre->id, 'fecha' => $cierre->fecha->toDateString(),
                'estado' => $cierre->estado, 'observacion' => $cierre->observacion,
                'almacen' => $cierre->almacen?->nombre,
            ],
            'items'   => $items,
            'precarga' => $this->config->cierrePrecargaStock($user->empresa_id),
        ]);
    }

    /**
     * Guarda cambios del cierre. Recalcula el stock del sistema al momento y las
     * diferencias. Si el cierre estaba CONFIRMADO, revierte su efecto, reasienta
     * los items y lo vuelve a confirmar — todo canónico (reconstruye stock+kardex),
     * así "editar y cerrar de nuevo" actualiza las diferencias sin pasos manuales.
     */
    public function update(Request $request, CierreInventario $cierre)
    {
        $user = $request->user();
        abort_unless($this->scope->puedeAccederAlmacen($user, $cierre->almacen), 403);
        abort_if($cierre->estado === 'anulado', 422, 'Un cierre anulado no se edita.');

        $data = $request->validate([
            'observacion' => 'nullable|string',
            'items'       => 'required|array|min:1',
            'items.*.producto_id'     => 'required|exists:productos,id',
            'items.*.stock_declarado' => 'required|numeric|min:0',
            'items.*.observacion'     => 'nullable|string',
        ]);

        DB::transaction(function () use ($cierre, $data, $request) {
            $eraConfirmado = $cierre->esConfirmado();
            $productosViejos = $cierre->items()->pluck('producto_id');

            // 1) Si estaba confirmado, "despegar" su efecto: a borrador y reconstruir
            //    → el stock queda como si este cierre no existiera (limpio).
            if ($eraConfirmado) {
                $cierre->update(['estado' => 'borrador']);
                $this->reconstruirProductos($cierre->almacen, $productosViejos);
            }

            // 2) Reasentar items contra el stock limpio actual.
            $cierre->items()->delete();
            $stocks = $this->stockActual($cierre->almacen_id, collect($data['items'])->pluck('producto_id'));
            foreach ($data['items'] as $i) {
                $sistema = (float) ($stocks[$i['producto_id']] ?? 0);
                $cierre->items()->create([
                    'producto_id'     => $i['producto_id'],
                    'stock_sistema'   => $sistema,
                    'stock_declarado' => $i['stock_declarado'],
                    'diferencia'      => round((float) $i['stock_declarado'] - $sistema, 4),
                    'observacion'     => $i['observacion'] ?? null,
                ]);
            }
            $cierre->update(['observacion' => $data['observacion'] ?? null, 'total_items' => count($data['items'])]);

            // 3) Si venía confirmado (o piden confirmar), aplicarlo de nuevo.
            if ($eraConfirmado || $request->boolean('confirmar')) {
                $cierre->refresh()->confirmar();
                $afectados = $productosViejos->merge(collect($data['items'])->pluck('producto_id'))->unique();
                $this->reconstruirProductos($cierre->almacen, $afectados);
            }
        });

        AuditoriaService::log('cierre_inventario.editado', $cierre, [
            'items' => count($data['items']),
            'estado' => $cierre->fresh()->estado,
        ], $user);

        return redirect()->route('inventario.cierres.show', $cierre->id)
            ->with('success', 'Cierre actualizado: diferencias recalculadas contra el stock actual.');
    }

    public function show(Request $request, CierreInventario $cierre)
    {
        abort_unless($this->scope->puedeAccederAlmacen($request->user(), $cierre->almacen), 403);

        $cierre->load(['almacen.local', 'user', 'items.producto:id,codigo,nombre']);
        $costos = $this->costos($cierre->almacen_id, $cierre->items->pluck('producto_id'));

        // Valorizar cada diferencia al costo promedio: el dueño ve el S/ de lo que
        // faltó (negativo) o sobró (positivo) para auditar.
        $faltante = 0.0; $sobrante = 0.0;
        $items = $cierre->items->map(function ($it) use ($costos, &$faltante, &$sobrante) {
            $costo = (float) ($costos[$it->producto_id] ?? 0);
            $valor = round((float) $it->diferencia * $costo, 2);
            if ($valor < 0) $faltante += $valor; elseif ($valor > 0) $sobrante += $valor;
            return [
                'producto_id'     => $it->producto_id,
                'codigo'          => $it->producto?->codigo,
                'nombre'          => $it->producto?->nombre ?? '—',
                'stock_sistema'   => (float) $it->stock_sistema,
                'stock_declarado' => (float) $it->stock_declarado,
                'diferencia'      => (float) $it->diferencia,
                'costo'           => $costo,
                'valor_diferencia'=> $valor,
                'observacion'     => $it->observacion,
            ];
        })->values();

        return Inertia::render('Inventario/Cierres/Show', [
            'cierre' => [
                'id' => $cierre->id, 'fecha' => $cierre->fecha->toDateString(),
                'estado' => $cierre->estado, 'observacion' => $cierre->observacion,
                'almacen' => $cierre->almacen?->nombre,
                'local' => $cierre->almacen?->local?->nombre,
                'usuario' => $cierre->user?->name,
                'total_items' => $cierre->total_items,
                'total_diferencias' => $cierre->total_diferencias,
            ],
            'items' => $items,
            'resumen' => [
                'faltante'    => round($faltante, 2),
                'sobrante'    => round($sobrante, 2),
                'neto'        => round($faltante + $sobrante, 2),
                'con_dif'     => $items->filter(fn ($i) => abs($i['diferencia']) > 0.00009)->count(),
            ],
        ]);
    }

    public function confirmar(Request $request, CierreInventario $cierre)
    {
        $user = $request->user();
        abort_unless($this->scope->puedeAccederAlmacen($user, $cierre->almacen), 403);
        abort_if(!$cierre->esBorrador(), 403, 'El cierre ya fue confirmado.');

        DB::transaction(function () use ($cierre) {
            $cierre->confirmar();
            $this->reconstruirProductos($cierre->almacen, $cierre->items()->pluck('producto_id'));
        });

        AuditoriaService::log('cierre_inventario.confirmado', $cierre, [], $user);

        return redirect()->back()->with('success', 'Cierre confirmado. Stock ajustado a lo declarado.');
    }

    /** Anula un cierre CONFIRMADO: revierte su ajuste de stock/kardex. */
    public function anular(Request $request, CierreInventario $cierre)
    {
        $user = $request->user();
        abort_unless($this->scope->puedeAccederAlmacen($user, $cierre->almacen), 403);
        abort_if(!$cierre->esConfirmado(), 422, 'Solo se anulan cierres confirmados.');

        $data = $request->validate(['motivo' => ['required', 'string', 'min:3', 'max:255']]);

        DB::transaction(function () use ($cierre) {
            $productos = $cierre->items()->pluck('producto_id');
            $cierre->update(['estado' => 'anulado']);
            // Al quedar 'anulado' ya no lo cuenta el recálculo → su efecto se revierte.
            $this->reconstruirProductos($cierre->almacen, $productos);
        });

        AuditoriaService::log('cierre_inventario.anulado', $cierre, ['motivo' => $data['motivo']], $user);

        return redirect()->back()->with('success', 'Cierre anulado: stock y kardex revertidos.');
    }

    public function destroy(Request $request, CierreInventario $cierre)
    {
        abort_unless($this->scope->puedeAccederAlmacen($request->user(), $cierre->almacen), 403);
        abort_if(!$cierre->esBorrador(), 403, 'No se pueden eliminar cierres confirmados. Anúlalos si ya se aplicaron.');

        $cierre->items()->delete();
        $cierre->delete();

        return redirect()->route('inventario.cierres.index')
            ->with('success', 'Cierre de inventario eliminado.');
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    /** Cantidad actual en stock por producto (keyed producto_id). */
    private function stockActual(int $almacenId, Collection $productoIds): Collection
    {
        return Stock::where('almacen_id', $almacenId)
            ->whereIn('producto_id', $productoIds)
            ->pluck('cantidad', 'producto_id');
    }

    /** Costo promedio actual por producto (keyed producto_id). */
    private function costos(int $almacenId, Collection $productoIds): Collection
    {
        return Stock::where('almacen_id', $almacenId)
            ->whereIn('producto_id', $productoIds)
            ->pluck('costo_promedio', 'producto_id');
    }

    /**
     * Stock del sistema "limpio" para cada producto de un cierre = stock actual
     * SIN el efecto de este cierre. Si está confirmado, resta su diferencia (que
     * ya se aplicó); si es borrador, es el stock actual tal cual.
     */
    private function stockSistemaLimpio(CierreInventario $cierre): Collection
    {
        $actual = $this->stockActual($cierre->almacen_id, $cierre->items->pluck('producto_id'));
        return $cierre->items->mapWithKeys(function ($it) use ($actual, $cierre) {
            $s = (float) ($actual[$it->producto_id] ?? 0);
            return [$it->producto_id => $cierre->esConfirmado() ? round($s - (float) $it->diferencia, 4) : $s];
        });
    }

    /** Reconstruye stock y kardex de cada producto (motor de "Recalcular", por par). */
    private function reconstruirProductos(Almacen $almacen, Collection $productoIds): void
    {
        $kardex = app(ReconstruirKardex::class);
        foreach ($productoIds->unique() as $pid) {
            Stock::reconstruir($almacen->id, (int) $pid);
            $kardex->reconstruirPar($almacen, (int) $pid);
        }
    }
}
