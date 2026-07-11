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
                'empresa_id'     => $request->user()->empresa_id,
                'categoria_id'   => $data['categoria_id'] ?? null,
                'codigo'         => $data['codigo'] ?? null,
                'nombre'         => $data['nombre'],
                'descripcion'    => $data['descripcion'] ?? null,
                'imagen'         => $data['imagen'] ?? null,
                'tipo'           => $data['tipo'],
                // Para productos físicos el precio real está en cada unidad; guardamos 0 como placeholder.
                'tipo_precio'    => $esProducto ? 'fijo' : $data['tipo_precio'],
                'precio_venta'   => $esProducto ? 0 : $data['precio_venta'],
                'precio_costo'   => 0,
                'activo'         => $data['activo'] ?? true,
                'incluye_igv'    => $data['incluye_igv'] ?? false,
                'controla_stock' => $esProducto ? ($data['controla_stock'] ?? null) : false,
                'es_retornable'  => $esProducto ? ($data['es_retornable'] ?? null) : false,
            ]);

            $unidades = $data['unidades'] ?? [];

            // Procesa presentaciones para AMBOS tipos (productos y servicios con variantes).
            // Si el form no envia ninguna y es servicio, se crea una unica presentacion
            // por defecto para que el POS pueda agregarlo al carrito (precio del servicio).
            if (!empty($unidades)) {
                foreach ($unidades as $u) {
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
            } elseif ($producto->esServicio()) {
                $this->crearPresentacionDefaultServicio($producto, $data);
            }
        });

        return redirect()->route('catalogo.productos.index')
            ->with('success', 'Producto creado correctamente.');
    }

    /**
     * Para servicios sin variantes explicitas, crea una presentacion default
     * con la unidad "Servicio" (la crea si no existe) y el precio del servicio.
     * Esto garantiza que todo servicio sea agregable desde el POS.
     */
    private function crearPresentacionDefaultServicio(Producto $producto, array $data): void
    {
        $um = UnidadMedida::firstOrCreate(
            ['empresa_id' => $producto->empresa_id, 'nombre' => 'Servicio'],
            ['abreviatura' => 'srv', 'activo' => true]
        );

        $producto->unidades()->create([
            'unidad_medida_id'  => $um->id,
            'es_base'           => true,
            'factor_conversion' => 1,
            'tipo_precio'       => $data['tipo_precio'] ?? 'fijo',
            'precio_venta'      => $data['precio_venta'] ?? 0,
            'precio_costo'      => 0,
            'activo'            => true,
        ]);
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

            // NO se toca precio_costo: el formulario no lo maneja, y el costo real
            // (para el balance y la valorización) vive en stock.costo_promedio.
            // Antes se pisaba a 0 en cada edición y eso borraba el costo del
            // producto, descuadrando el balance. Ahora se conserva su valor.
            $producto->update([
                'categoria_id'   => $data['categoria_id'] ?? null,
                'codigo'         => $data['codigo'] ?? null,
                'nombre'         => $data['nombre'],
                'descripcion'    => $data['descripcion'] ?? null,
                'imagen'         => $data['imagen'] ?? null,
                'tipo'           => $data['tipo'],
                'tipo_precio'    => $esProducto ? 'fijo' : $data['tipo_precio'],
                'precio_venta'   => $esProducto ? 0 : $data['precio_venta'],
                'activo'         => $data['activo'] ?? true,
                'incluye_igv'    => $data['incluye_igv'] ?? false,
                'controla_stock' => $esProducto ? ($data['controla_stock'] ?? null) : false,
                'es_retornable'  => $esProducto ? ($data['es_retornable'] ?? null) : false,
            ]);

            $unidades = $data['unidades'] ?? null;

            // Si el form mando presentaciones (sea producto o servicio con variantes),
            // sincronizarlas. Si no mando ninguna y es servicio sin presentaciones aun,
            // crear la default para mantener consistencia.
            if (!empty($unidades)) {
                $incoming    = collect($unidades);
                $incomingIds = $incoming->pluck('id')->filter();

                // Unidades removidas del formulario: borrar solo si no tienen ventas;
                // si tienen ventas asociadas, desactivarlas para preservar el historial.
                $unidadesARemover = $producto->unidades()->whereNotIn('id', $incomingIds)->get();
                foreach ($unidadesARemover as $unidad) {
                    $tieneVentas = \App\Models\VentaItem::where('producto_unidad_id', $unidad->id)->exists();
                    if ($tieneVentas) {
                        $unidad->update(['activo' => false]);
                    } else {
                        $unidad->delete();
                    }
                }

                foreach ($incoming as $u) {
                    $match = isset($u['id'])
                        ? ['id' => $u['id']]
                        : ['unidad_medida_id' => $u['unidad_medida_id']];

                    // No se pisa precio_costo de la unidad: se conserva el que
                    // ya tuviera (una edición no debe borrar el costo).
                    $producto->unidades()->updateOrCreate(
                        $match,
                        [
                            'unidad_medida_id'  => $u['unidad_medida_id'],
                            'es_base'           => $u['es_base'],
                            'factor_conversion' => $u['es_base'] ? 1 : $u['factor_conversion'],
                            'tipo_precio'       => $u['tipo_precio'],
                            'precio_venta'      => $u['precio_venta'],
                            'activo'            => $u['activo'] ?? true,
                        ]
                    );
                }
            } elseif ($producto->esServicio() && $producto->unidades()->count() === 0) {
                $this->crearPresentacionDefaultServicio($producto, $data);
            }
        });

        return redirect()->route('catalogo.productos.index')
            ->with('success', 'Producto actualizado correctamente.');
    }

    public function destroy(Producto $producto)
    {
        $producto->update(['activo' => false]);

        \App\Services\AuditoriaService::log('producto.eliminado', $producto, [
            'codigo' => $producto->codigo,
            'nombre' => $producto->nombre,
            'tipo'   => $producto->tipo,
            // Es soft (activo=false). Si se vuelve hard delete en el futuro,
            // este registro queda como prueba de quien lo desactivo.
            'soft'   => true,
        ]);

        return redirect()->back()->with('success', 'Producto desactivado correctamente.');
    }
}
