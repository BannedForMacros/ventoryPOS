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
        ]);

        return Inertia::render('Inventario/Stock', [
            'stocks'              => $stocks,
            'almacenes'           => $almacenes,
            'mostrarSelector'     => $this->scope->mostrarSelectorLocal($user),
            'filters'             => $request->only(['almacen_id', 'busqueda']),
        ]);
    }

    /**
     * Recalcula el stock de TODOS los productos en TODOS los almacenes visibles
     * procesando las entradas confirmadas en orden cronológico.
     * Solo disponible para admins.
     */
    public function recalcular(Request $request)
    {
        $user       = $request->user();
        $almacenIds = $this->scope->almacenIdsVisibles($user);

        DB::transaction(function () use ($almacenIds) {
            // Obtener combinaciones únicas de almacen+producto con entradas confirmadas
            $combinaciones = \App\Models\EntradaDetalle::whereHas('entrada', fn ($q) =>
                    $q->whereIn('almacen_id', $almacenIds)->where('estado', 'confirmado')
                )
                ->join('entradas', 'entradas_detalle.entrada_id', '=', 'entradas.id')
                ->select('entradas.almacen_id', 'entradas_detalle.producto_id')
                ->distinct()
                ->get();

            // Resetear stock a 0 para recalcular limpio
            Stock::whereIn('almacen_id', $almacenIds)->update(['cantidad' => 0, 'costo_promedio' => 0]);

            foreach ($combinaciones as $combo) {
                Stock::recalcularDesdeEntradas($combo->almacen_id, $combo->producto_id);
            }
        });

        return redirect()->back()->with('success', 'Stock recalculado correctamente desde las entradas confirmadas.');
    }
}
