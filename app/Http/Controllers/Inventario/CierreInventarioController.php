<?php

namespace App\Http\Controllers\Inventario;

use App\Support\EnEmpresa;
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

        // Con `fecha` (cierre de un día pasado) se precarga el saldo de ESE día,
        // el mismo contra el que store() compara; sin ella, el stock de hoy.
        $fecha = $request->query('fecha');
        $alDia = $fecha && strtotime((string) $fecha) !== false
            ? $this->stockSistemaALaFecha($almacen, $fecha, $productos->pluck('id'))
            : null;

        $resultado = $productos->map(fn ($p) => [
            'id'            => $p->id,
            'codigo'        => $p->codigo,
            'nombre'        => $p->nombre,
            'categoria'     => $p->categoria?->nombre,
            'categoria_id'  => $p->categoria_id,
            'stock_sistema' => (float) ($alDia ? ($alDia[$p->id] ?? 0) : ($stocks[$p->id]->cantidad ?? 0)),
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
            'almacen_id'  => ['required', EnEmpresa::existe('almacenes')],
            'turno_id'    => ['nullable', EnEmpresa::existe('turnos')],
            'fecha'       => ['required', 'date', new \App\Rules\NoFutura],
            'observacion' => 'nullable|string',
            'items'       => 'required|array|min:1',
            'items.*.producto_id'     => ['required', EnEmpresa::existe('productos')],
            // stock_sistema lo recalcula el servidor al guardar (fuente de verdad).
            // El que vio el usuario solo sirve de referencia en el modo precargado.
            'items.*.stock_sistema'   => 'nullable|numeric',
            'items.*.stock_declarado' => 'required|numeric|min:0',
            'items.*.observacion'     => 'nullable|string',
        ]);

        $almacen = Almacen::findOrFail($data['almacen_id']);
        abort_unless($this->scope->puedeAccederAlmacen($user, $almacen), 403);

        // Un cierre fechado en o antes del inventario inicial no tendría efecto.
        app(\App\Services\KardexService::class)->exigirPosteriorAApertura(
            $almacen->id, collect($data['items'])->pluck('producto_id'), $data['fecha'], 'fecha', 'Este cierre',
        );

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

        $precarga = $this->config->cierrePrecargaStock($user->empresa_id);

        $cierre = DB::transaction(function () use ($data, $user, $turnoId, $request, $almacen, $precarga) {
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

            // El stock del sistema lo calcula el servidor (no el form) y AL DÍA del
            // cierre: un cierre de ayer se compara con el saldo de ayer, no con el
            // de hoy (si no, las ventas de hoy se "contaban" como faltante y el
            // cierre las borraba). Un cierre nuevo aún no aplicó nada.
            $stocks = $this->stockSistemaALaFecha($almacen, $data['fecha'], collect($data['items'])->pluck('producto_id'), $cierre->id);
            $diaPasado = \Illuminate\Support\Carbon::parse($data['fecha'])->toDateString() < today()->toDateString();

            foreach ($data['items'] as $i) {
                $cierre->items()->create($this->asentarItem((float) ($stocks[$i['producto_id']] ?? 0), $i, $precarga, $diaPasado));
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
        $costos = $this->costos($cierre->almacen_id, $cierre->items->pluck('producto_id'));

        // Se muestra el stock del sistema con el que se hizo el conteo (guardado
        // en el ítem). Lo que no se toque conserva su diferencia al guardar; antes
        // se recalculaba contra el stock de HOY y editar un cierre viejo borraba
        // las ventas y salidas posteriores.
        $items = $cierre->items->map(function ($it) use ($costos) {
            return [
                'producto_id'     => $it->producto_id,
                'codigo'          => $it->producto?->codigo,
                'nombre'          => $it->producto?->nombre ?? '—',
                'stock_sistema'   => (float) $it->stock_sistema,
                'stock_declarado' => (float) $it->stock_declarado,
                'diferencia'      => (float) $it->diferencia,
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
            'items.*.producto_id'     => ['required', EnEmpresa::existe('productos')],
            'items.*.stock_sistema'   => 'nullable|numeric',
            'items.*.stock_declarado' => 'required|numeric|min:0',
            'items.*.observacion'     => 'nullable|string',
        ]);

        app(\App\Services\KardexService::class)->exigirPosteriorAApertura(
            $cierre->almacen_id, collect($data['items'])->pluck('producto_id'), $cierre->fecha, 'items', 'Este cierre',
        );

        $precarga = $this->config->cierrePrecargaStock($user->empresa_id);

        DB::transaction(function () use ($cierre, $data, $request, $precarga) {
            $eraConfirmado = $cierre->esConfirmado();
            $productosViejos = $cierre->items()->pluck('producto_id');
            $itemsViejos     = $cierre->items()->get()->keyBy('producto_id');

            // 1) Si estaba confirmado, "despegar" su efecto: a borrador y reconstruir
            //    → el stock queda como si este cierre no existiera (limpio).
            if ($eraConfirmado) {
                $cierre->update(['estado' => 'borrador']);
                $this->reconstruirProductos($cierre->almacen, $productosViejos);
            }

            // 2) Reasentar items. Lo que NO se tocó (misma cantidad declarada)
            //    conserva su stock del sistema y su diferencia: el conteo se hizo
            //    contra ese stock. Solo lo cambiado o agregado se recalcula, contra
            //    el saldo AL DÍA del cierre (no contra el de hoy).
            $tocados = collect($data['items'])->filter(function ($i) use ($itemsViejos) {
                $viejo = $itemsViejos->get($i['producto_id']);
                return !$viejo || abs((float) $viejo->stock_declarado - (float) $i['stock_declarado']) > 0.00005;
            });
            $stocks = $tocados->isEmpty()
                ? collect()
                : $this->stockSistemaALaFecha($cierre->almacen, $cierre->fecha, $tocados->pluck('producto_id'), $cierre->id);

            $cierre->items()->delete();
            foreach ($data['items'] as $i) {
                $viejo = $itemsViejos->get($i['producto_id']);
                if ($viejo && !$tocados->contains(fn ($t) => (int) $t['producto_id'] === (int) $i['producto_id'])) {
                    $cierre->items()->create([
                        'producto_id'     => $i['producto_id'],
                        'stock_sistema'   => (float) $viejo->stock_sistema,
                        'stock_declarado' => (float) $viejo->stock_declarado,
                        'diferencia'      => (float) $viejo->diferencia,
                        'observacion'     => $i['observacion'] ?? null,
                    ]);
                    continue;
                }
                $cierre->items()->create($this->asentarItem((float) ($stocks[$i['producto_id']] ?? 0), $i, $precarga));
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
            ->with('success', 'Cierre actualizado: se recalcularon solo los productos que cambiaste, contra el stock del día del cierre.');
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
     * Stock del sistema de cada producto AL FINAL DEL DÍA del cierre, sin el
     * efecto de este cierre (debe estar en borrador cuando se llama).
     *
     * Si el cierre es de hoy, es el stock actual (no hay documentos con fecha
     * futura). Si es de un día pasado, es el saldo del kardex a esa fecha,
     * reproducido desde los documentos: así un cierre viejo no absorbe las
     * ventas, salidas y compras que pasaron DESPUÉS.
     */
    private function stockSistemaALaFecha(Almacen $almacen, $fecha, Collection $productoIds, ?int $excluirCierreId = null): Collection
    {
        $dia = \Illuminate\Support\Carbon::parse($fecha)->toDateString();
        if ($dia >= today()->toDateString()) {
            return $this->stockActual($almacen->id, $productoIds);
        }

        $kardex = app(\App\Services\KardexService::class);

        return $productoIds->unique()->mapWithKeys(function ($pid) use ($kardex, $almacen, $dia, $excluirCierreId) {
            $saldo = 0.0;
            foreach ($kardex->movimientos($almacen, (int) $pid) as $m) {
                if (substr((string) $m['fecha'], 0, 10) > $dia) continue;
                if ($excluirCierreId && $m['ref_tipo'] === 'cierre' && (int) $m['ref_id'] === $excluirCierreId) continue;
                $saldo += (float) $m['cantidad'];
            }
            return [(int) $pid => round($saldo, 4)];
        });
    }

    /**
     * Arma un ítem del cierre contra el stock del sistema calculado por el
     * servidor.
     *
     * Modo precargado: el formulario arrancó con el stock que el usuario VIO y
     * él corrigió solo lo que contó distinto. Si entre cargar y guardar se vendió
     * algo, comparar lo precargado contra el stock nuevo convertía esa venta en
     * "sobrante" y la deshacía. Por eso ahí la diferencia es lo que el usuario
     * cambió respecto de lo que vio, y lo declarado = sistema + esa diferencia.
     *
     * Cierre NUEVO de un día pasado (`$diaPasado`): lo visto puede ser el stock
     * de HOY y no el de ese día, así que no sirve de base para la diferencia
     * (contar 48 cuando ese día había 50 y hoy hay 45 daba sobrante +3). Ahí lo
     * que el usuario no tocó queda sin diferencia y lo que escribió es su conteo
     * de ese día, igual que en el modo en blanco.
     */
    private function asentarItem(float $sistema, array $i, bool $precarga, bool $diaPasado = false): array
    {
        $declarado = (float) $i['stock_declarado'];
        $visto     = $i['stock_sistema'] ?? null;

        if ($precarga && $diaPasado && $visto !== null && $visto !== '') {
            if (abs($declarado - (float) $visto) < 0.00005) {
                $declarado = $sistema;
            }
        } elseif ($precarga && $visto !== null && $visto !== '') {
            $dif       = round($declarado - (float) $visto, 4);
            $declarado = round(max(0.0, $sistema + $dif), 4);
        }

        return [
            'producto_id'     => $i['producto_id'],
            'stock_sistema'   => $sistema,
            'stock_declarado' => $declarado,
            'diferencia'      => round($declarado - $sistema, 4),
            'observacion'     => $i['observacion'] ?? null,
        ];
    }

    /** Reconstruye stock y kardex de cada producto con el motor único (una pasada, los dos libros). */
    private function reconstruirProductos(Almacen $almacen, Collection $productoIds): void
    {
        $kardex = app(\App\Services\KardexService::class);
        foreach ($productoIds->unique() as $pid) {
            $kardex->reconstruirPar($almacen->id, (int) $pid);
        }
    }
}
