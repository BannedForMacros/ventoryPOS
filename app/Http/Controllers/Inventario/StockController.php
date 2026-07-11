<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Controller;
use App\Models\Almacen;
use App\Models\Entrada;
use App\Models\Stock;
use App\Services\LocalScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class StockController extends Controller
{
    public function __construct(private LocalScopeService $scope) {}

    public function index(Request $request)
    {
        $user      = $request->user();
        $almacenes = $this->scope->almacenesVisibles($user);
        $almacenIds = $almacenes->pluck('id')->toArray();

        $query = Stock::whereIn('almacen_id', $almacenIds)
            ->with(['producto.unidadBase.unidadMedida', 'almacen.local'])
            ->when($request->almacen_id, fn ($q, $id) => $q->where('almacen_id', $id))
            ->when($request->busqueda, fn ($q, $s) =>
                $q->whereHas('producto', fn ($p) =>
                    $p->where('nombre', 'ilike', "%{$s}%")
                      ->orWhere('codigo', 'ilike', "%{$s}%")
                )
            );

        $stocks = $query->get()->map(fn ($s) => [
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
            'mostrarSelector'      => $this->scope->mostrarSelectorLocal($user),
            'filters'              => $request->only(['almacen_id', 'busqueda']),
            'stocksNegativosCount' => $stocksNegativos->count(),
            'stocksNegativos'      => $stocksNegativos,
        ]);
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

        \App\Services\AuditoriaService::log('stock.recalculado', null, $stats);

        return redirect()->back()->with('success',
            'Stock reconstruido a partir de entradas, salidas, transferencias, ventas, devoluciones y cierres confirmados.');
    }
}
