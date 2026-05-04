<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Controller;
use App\Models\Almacen;
use App\Models\CierreInventario;
use App\Models\Producto;
use App\Models\Stock;
use App\Models\Turno;
use App\Services\LocalScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CierreInventarioController extends Controller
{
    public function __construct(private LocalScopeService $scope) {}

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
            ->pluck('cantidad', 'producto_id');

        $resultado = $productos->map(fn ($p) => [
            'id'            => $p->id,
            'codigo'        => $p->codigo,
            'nombre'        => $p->nombre,
            'categoria'     => $p->categoria?->nombre,
            'categoria_id'  => $p->categoria_id,
            'stock_sistema' => (float) ($stocks[$p->id] ?? 0),
        ]);

        return response()->json($resultado);
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
            'items.*.stock_sistema'   => 'required|numeric',
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

        $cierre = DB::transaction(function () use ($data, $user, $turnoId) {
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

            foreach ($data['items'] as $i) {
                $diff = round((float) $i['stock_declarado'] - (float) $i['stock_sistema'], 4);

                $cierre->items()->create([
                    'producto_id'     => $i['producto_id'],
                    'stock_sistema'   => $i['stock_sistema'],
                    'stock_declarado' => $i['stock_declarado'],
                    'diferencia'      => $diff,
                    'observacion'     => $i['observacion'] ?? null,
                ]);
            }

            if ($request->boolean('confirmar')) {
                $cierre->confirmar();
            }

            return $cierre;
        });

        return redirect()->route('inventario.cierres.show', $cierre->id)
            ->with('success', 'Cierre de inventario registrado.');
    }

    public function show(Request $request, CierreInventario $cierre)
    {
        abort_unless($this->scope->puedeAccederAlmacen($request->user(), $cierre->almacen), 403);

        $cierre->load(['almacen.local', 'user', 'items.producto']);

        return Inertia::render('Inventario/Cierres/Show', [
            'cierre' => $cierre,
        ]);
    }

    public function confirmar(Request $request, CierreInventario $cierre)
    {
        abort_unless($this->scope->puedeAccederAlmacen($request->user(), $cierre->almacen), 403);
        abort_if(!$cierre->esBorrador(), 403, 'El cierre ya fue confirmado.');

        $cierre->confirmar();

        return redirect()->back()->with('success', 'Cierre confirmado. Stock ajustado.');
    }

    public function destroy(Request $request, CierreInventario $cierre)
    {
        abort_unless($this->scope->puedeAccederAlmacen($request->user(), $cierre->almacen), 403);
        abort_if(!$cierre->esBorrador(), 403, 'No se pueden eliminar cierres confirmados.');

        $cierre->items()->delete();
        $cierre->delete();

        return redirect()->route('inventario.cierres.index')
            ->with('success', 'Cierre de inventario eliminado.');
    }
}
