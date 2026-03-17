<?php

namespace App\Http\Controllers\Catalogo;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalogo\ProductoRequest;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\UnidadMedida;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ProductoController extends Controller
{
    public function index(Request $request)
    {
        $empresaId = $request->user()->empresa_id;

        $productos = Producto::deEmpresa($empresaId)
            ->with(['categoria', 'unidadBase.unidadMedida'])
            ->when($request->search, fn($q, $s) =>
                $q->where(fn($q2) =>
                    $q2->where('nombre', 'ilike', "%{$s}%")
                       ->orWhere('codigo', 'ilike', "%{$s}%")
                )
            )
            ->when($request->tipo, fn($q, $t) => $q->where('tipo', $t))
            ->when($request->categoria_id, fn($q, $c) => $q->where('categoria_id', $c))
            ->orderBy('nombre')
            ->get();

        $categorias = Categoria::deEmpresa($empresaId)->activo()->orderBy('nombre')->get();
        $unidades   = UnidadMedida::deEmpresa($empresaId)->activo()->orderBy('nombre')->get();

        return Inertia::render('Catalogo/Productos', [
            'productos'  => $productos,
            'categorias' => $categorias,
            'unidades'   => $unidades,
        ]);
    }

    public function create(Request $request)
    {
        $empresaId = $request->user()->empresa_id;

        return Inertia::render('Catalogo/Productos/Create', [
            'categorias' => Categoria::deEmpresa($empresaId)->activo()->orderBy('nombre')->get(),
            'unidades'   => UnidadMedida::deEmpresa($empresaId)->activo()->orderBy('nombre')->get(),
        ]);
    }

    public function store(ProductoRequest $request)
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $request) {
            $esProducto = $data['tipo'] === 'producto';

            $producto = Producto::create([
                'empresa_id'   => $request->user()->empresa_id,
                'categoria_id' => $data['categoria_id'] ?? null,
                'codigo'       => $data['codigo'] ?? null,
                'nombre'       => $data['nombre'],
                'descripcion'  => $data['descripcion'] ?? null,
                'tipo'         => $data['tipo'],
                // Para productos físicos el precio real está en cada unidad; guardamos 0 como placeholder.
                'tipo_precio'  => $esProducto ? 'fijo' : $data['tipo_precio'],
                'precio_venta' => $esProducto ? 0 : $data['precio_venta'],
                'precio_costo' => 0,
                'activo'       => $data['activo'] ?? true,
            ]);

            if ($producto->esProductoFisico()) {
                foreach ($data['unidades'] as $u) {
                    $producto->unidades()->create([
                        'unidad_medida_id'  => $u['unidad_medida_id'],
                        'es_base'           => $u['es_base'],
                        'factor_conversion' => $u['es_base'] ? 1 : $u['factor_conversion'],
                        'tipo_precio'       => $u['tipo_precio'],
                        'precio_venta'      => $u['precio_venta'],
                        'precio_costo'      => 0,
                        'activo'            => $u['activo'] ?? true,
                    ]);
                }
            }
        });

        return redirect()->route('catalogo.productos.index')
            ->with('success', 'Producto creado correctamente.');
    }

    public function edit(Request $request, Producto $producto)
    {
        $empresaId = $request->user()->empresa_id;

        return Inertia::render('Catalogo/Productos/Edit', [
            'producto'   => $producto->load(['categoria', 'unidades.unidadMedida']),
            'categorias' => Categoria::deEmpresa($empresaId)->activo()->orderBy('nombre')->get(),
            'unidades'   => UnidadMedida::deEmpresa($empresaId)->activo()->orderBy('nombre')->get(),
        ]);
    }

    public function update(ProductoRequest $request, Producto $producto)
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $producto) {
            $esProducto = $data['tipo'] === 'producto';

            $producto->update([
                'categoria_id' => $data['categoria_id'] ?? null,
                'codigo'       => $data['codigo'] ?? null,
                'nombre'       => $data['nombre'],
                'descripcion'  => $data['descripcion'] ?? null,
                'tipo'         => $data['tipo'],
                'tipo_precio'  => $esProducto ? 'fijo' : $data['tipo_precio'],
                'precio_venta' => $esProducto ? 0 : $data['precio_venta'],
                'precio_costo' => 0,
                'activo'       => $data['activo'] ?? true,
            ]);

            if ($producto->esProductoFisico()) {
                $incoming    = collect($data['unidades']);
                $incomingIds = $incoming->pluck('id')->filter();

                $producto->unidades()
                    ->whereNotIn('id', $incomingIds)
                    ->delete();

                foreach ($incoming as $u) {
                    $producto->unidades()->updateOrCreate(
                        ['id' => $u['id'] ?? null],
                        [
                            'unidad_medida_id'  => $u['unidad_medida_id'],
                            'es_base'           => $u['es_base'],
                            'factor_conversion' => $u['es_base'] ? 1 : $u['factor_conversion'],
                            'tipo_precio'       => $u['tipo_precio'],
                            'precio_venta'      => $u['precio_venta'],
                            'precio_costo'      => 0,
                            'activo'            => $u['activo'] ?? true,
                        ]
                    );
                }
            } else {
                $producto->unidades()->delete();
            }
        });

        return redirect()->route('catalogo.productos.index')
            ->with('success', 'Producto actualizado correctamente.');
    }

    public function destroy(Producto $producto)
    {
        $producto->update(['activo' => false]);

        return redirect()->back()->with('success', 'Producto desactivado correctamente.');
    }
}
