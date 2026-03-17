<?php

namespace App\Http\Controllers\Inventario;

use App\Http\Controllers\Controller;
use App\Models\Almacen;
use App\Models\Entrada;
use App\Models\Producto;
use App\Services\LocalScopeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class EntradaController extends Controller
{
    public function __construct(private LocalScopeService $scope) {}

    public function index(Request $request)
    {
        $user       = $request->user();
        $almacenIds = $this->scope->almacenIdsVisibles($user);

        $entradas = Entrada::whereIn('almacen_id', $almacenIds)
            ->with(['almacen.local', 'user'])
            ->when($request->almacen_id, fn ($q, $id) => $q->where('almacen_id', $id))
            ->when($request->estado, fn ($q, $e) => $q->where('estado', $e))
            ->when($request->fecha_desde, fn ($q, $f) => $q->whereDate('fecha', '>=', $f))
            ->when($request->fecha_hasta, fn ($q, $f) => $q->whereDate('fecha', '<=', $f))
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get();

        return Inertia::render('Inventario/Entradas/Index', [
            'entradas'        => $entradas,
            'almacenes'       => $this->scope->almacenesVisibles($user),
            'mostrarSelector' => $this->scope->mostrarSelectorLocal($user),
            'filters'         => $request->only(['almacen_id', 'estado', 'fecha_desde', 'fecha_hasta']),
        ]);
    }

    public function create(Request $request)
    {
        $user      = $request->user();
        $empresaId = $user->empresa_id;

        return Inertia::render('Inventario/Entradas/Create', [
            'almacenes' => $this->scope->almacenesVisibles($user),
            'productos' => Producto::deEmpresa($empresaId)
                ->activo()
                ->productos()
                ->with(['unidades.unidadMedida'])
                ->orderBy('nombre')
                ->get(),
            'mostrarSelector' => $this->scope->mostrarSelectorLocal($user),
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'almacen_id'       => 'required|exists:almacenes,id',
            'proveedor'        => 'nullable|string|max:150',
            'numero_documento' => 'nullable|string|max:50',
            'tipo'             => 'required|in:compra,ajuste,devolucion,otro',
            'fecha'            => 'required|date',
            'observacion'      => 'nullable|string',
            'detalles'         => 'required|array|min:1',
            'detalles.*.producto_id'       => 'required|exists:productos,id',
            'detalles.*.unidad_medida_id'  => 'required|exists:unidades_medida,id',
            'detalles.*.cantidad'          => 'required|numeric|min:0.0001',
            'detalles.*.factor_conversion' => 'required|numeric|min:0.0001',
            'detalles.*.precio_costo'      => 'required|numeric|min:0',
        ]);

        abort_unless($this->scope->puedeAccederAlmacen($user, Almacen::find($data['almacen_id'])), 403);

        DB::transaction(function () use ($data, $user) {
            $total = 0;

            $entrada = Entrada::create([
                'empresa_id'       => $user->empresa_id,
                'almacen_id'       => $data['almacen_id'],
                'user_id'          => $user->id,
                'proveedor'        => $data['proveedor'] ?? null,
                'numero_documento' => $data['numero_documento'] ?? null,
                'tipo'             => $data['tipo'],
                'fecha'            => $data['fecha'],
                'observacion'      => $data['observacion'] ?? null,
                'estado'           => 'borrador',
                'total'            => 0,
            ]);

            foreach ($data['detalles'] as $d) {
                $cantidadBase = round((float) $d['cantidad'] * (float) $d['factor_conversion'], 4);
                $subtotal     = round((float) $d['cantidad'] * (float) $d['precio_costo'], 2);
                $total       += $subtotal;

                $entrada->detalles()->create([
                    'producto_id'      => $d['producto_id'],
                    'unidad_medida_id' => $d['unidad_medida_id'],
                    'cantidad'         => $d['cantidad'],
                    'factor_conversion'=> $d['factor_conversion'],
                    'cantidad_base'    => $cantidadBase,
                    'precio_costo'     => $d['precio_costo'],
                    'subtotal'         => $subtotal,
                ]);
            }

            $entrada->update(['total' => $total]);

            // Si se pidió confirmar directamente
            if (request()->boolean('confirmar')) {
                $entrada->confirmar();
            }
        });

        return redirect()->route('inventario.entradas.index')
            ->with('success', 'Entrada registrada correctamente.');
    }

    public function edit(Request $request, Entrada $entrada)
    {
        abort_if($entrada->estado !== 'borrador', 403, 'Solo se pueden editar entradas en borrador.');
        abort_unless($this->scope->puedeAccederAlmacen($request->user(), $entrada->almacen), 403);

        $user      = $request->user();
        $empresaId = $user->empresa_id;

        return Inertia::render('Inventario/Entradas/Edit', [
            'entrada'   => $entrada->load(['detalles.producto', 'detalles.unidadMedida']),
            'almacenes' => $this->scope->almacenesVisibles($user),
            'productos' => Producto::deEmpresa($empresaId)
                ->activo()
                ->productos()
                ->with(['unidades.unidadMedida'])
                ->orderBy('nombre')
                ->get(),
            'mostrarSelector' => $this->scope->mostrarSelectorLocal($user),
        ]);
    }

    public function update(Request $request, Entrada $entrada)
    {
        abort_if($entrada->estado !== 'borrador', 403, 'Solo se pueden editar entradas en borrador.');
        abort_unless($this->scope->puedeAccederAlmacen($request->user(), $entrada->almacen), 403);

        $data = $request->validate([
            'almacen_id'       => 'required|exists:almacenes,id',
            'proveedor'        => 'nullable|string|max:150',
            'numero_documento' => 'nullable|string|max:50',
            'tipo'             => 'required|in:compra,ajuste,devolucion,otro',
            'fecha'            => 'required|date',
            'observacion'      => 'nullable|string',
            'detalles'         => 'required|array|min:1',
            'detalles.*.producto_id'       => 'required|exists:productos,id',
            'detalles.*.unidad_medida_id'  => 'required|exists:unidades_medida,id',
            'detalles.*.cantidad'          => 'required|numeric|min:0.0001',
            'detalles.*.factor_conversion' => 'required|numeric|min:0.0001',
            'detalles.*.precio_costo'      => 'required|numeric|min:0',
        ]);

        DB::transaction(function () use ($data, $entrada) {
            $total = 0;

            $entrada->update([
                'almacen_id'       => $data['almacen_id'],
                'proveedor'        => $data['proveedor'] ?? null,
                'numero_documento' => $data['numero_documento'] ?? null,
                'tipo'             => $data['tipo'],
                'fecha'            => $data['fecha'],
                'observacion'      => $data['observacion'] ?? null,
            ]);

            $entrada->detalles()->delete();

            foreach ($data['detalles'] as $d) {
                $cantidadBase = round((float) $d['cantidad'] * (float) $d['factor_conversion'], 4);
                $subtotal     = round((float) $d['cantidad'] * (float) $d['precio_costo'], 2);
                $total       += $subtotal;

                $entrada->detalles()->create([
                    'producto_id'      => $d['producto_id'],
                    'unidad_medida_id' => $d['unidad_medida_id'],
                    'cantidad'         => $d['cantidad'],
                    'factor_conversion'=> $d['factor_conversion'],
                    'cantidad_base'    => $cantidadBase,
                    'precio_costo'     => $d['precio_costo'],
                    'subtotal'         => $subtotal,
                ]);
            }

            $entrada->update(['total' => $total]);
        });

        return redirect()->route('inventario.entradas.index')
            ->with('success', 'Entrada actualizada correctamente.');
    }

    public function confirmar(Request $request, Entrada $entrada)
    {
        abort_unless($this->scope->puedeAccederAlmacen($request->user(), $entrada->almacen), 403);

        $entrada->confirmar();

        return redirect()->back()->with('success', 'Entrada confirmada. El stock ha sido actualizado.');
    }

    public function destroy(Request $request, Entrada $entrada)
    {
        abort_if($entrada->estado !== 'borrador', 403, 'Solo se pueden eliminar entradas en borrador.');
        abort_unless($this->scope->puedeAccederAlmacen($request->user(), $entrada->almacen), 403);

        $entrada->detalles()->delete();
        $entrada->delete();

        return redirect()->back()->with('success', 'Entrada eliminada correctamente.');
    }
}
