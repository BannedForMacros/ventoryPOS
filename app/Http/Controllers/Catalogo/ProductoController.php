<?php

namespace App\Http\Controllers\Catalogo;

use App\Http\Controllers\Controller;
use App\Http\Requests\Catalogo\ProductoRequest;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\UnidadMedida;
use App\Services\InventarioInicialService;
use App\Services\ProductoCreacionService;
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

    /**
     * Productos de la empresa con un nombre parecido al que se está creando,
     * para avisar "¿no será el mismo?" antes de duplicarlo (caso ladrillo
     * LARK: la entrada fue a un producto y la venta a su gemelo). Ignora
     * mayúsculas, tildes y espacios; usa el índice trigram de productos.
     */
    public function parecidos(Request $request)
    {
        $nombre = trim((string) $request->query('nombre', ''));
        if (mb_strlen($nombre) < 4) return response()->json(['productos' => []]);

        $productos = Producto::where('empresa_id', $request->user()->empresa_id)
            ->where('activo', true)
            ->whereRaw('public.unaccent_immutable(nombre) % public.unaccent_immutable(?)', [$nombre])
            ->orderByRaw('similarity(public.unaccent_immutable(nombre), public.unaccent_immutable(?)) DESC', [$nombre])
            ->limit(3)
            ->get(['id', 'codigo', 'nombre']);

        return response()->json(['productos' => $productos]);
    }

    public function create(Request $request)
    {
        $empresaId = $request->user()->empresa_id;

        $almacen = app(ProductoCreacionService::class)->almacenInicial($request->user());

        return Inertia::render('Catalogo/Productos/Create', [
            'categorias' => Categoria::deEmpresa($empresaId)->activo()->orderBy('nombre')->get(),
            'unidades'   => UnidadMedida::deEmpresa($empresaId)->activo()->orderBy('nombre')->get(),
            // Dónde y con qué fecha queda el stock inicial que se escriba al crear.
            'inventarioInicial' => $almacen ? [
                'almacen' => $almacen->nombre,
                'fecha'   => app(InventarioInicialService::class)->fechaParaProductoNuevo($almacen->id),
            ] : null,
        ]);
    }

    public function store(ProductoRequest $request, ProductoCreacionService $creacion)
    {
        ['aviso' => $aviso] = $creacion->crear($request->user(), $request->validated());

        return redirect()->route('catalogo.productos.index')
            ->with('success', 'Producto creado correctamente.' . ($aviso ? " {$aviso}" : ''));
    }

    public function edit(Request $request, Producto $producto)
    {
        $empresaId = $request->user()->empresa_id;
        abort_if($producto->empresa_id !== $empresaId, 403);

        return Inertia::render('Catalogo/Productos/Edit', [
            'producto'   => $producto->load(['categoria', 'unidades.unidadMedida']),
            'categorias' => Categoria::deEmpresa($empresaId)->activo()->orderBy('nombre')->get(),
            'unidades'   => UnidadMedida::deEmpresa($empresaId)->activo()->orderBy('nombre')->get(),
        ]);
    }

    public function update(ProductoRequest $request, Producto $producto)
    {
        abort_if($producto->empresa_id !== $request->user()->empresa_id, 403);

        $data = $request->validated();

        if ($bloqueo = $this->cambioEstructuralBloqueado($producto, $data)) {
            return back()->withErrors($bloqueo)->withInput();
        }

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
                        // Al desactivar también limpiamos es_base: si no, una base
                        // vieja desactivada seguía contando como base y desordenaba
                        // el default de unidad (p.ej. las entradas salían en "UND").
                        $unidad->update(['activo' => false, 'es_base' => false]);
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
                app(ProductoCreacionService::class)->crearPresentacionDefaultServicio($producto, $data);
            }
        });

        return redirect()->route('catalogo.productos.index')
            ->with('success', 'Producto actualizado correctamente.');
    }

    /**
     * Un producto con stock o con historia (compras, ventas, kardex) no puede
     * cambiar de unidad base ni pasar de producto a servicio (o al revés).
     *
     * Todo lo registrado está en unidades base: si "UND" pasa a ser "CAJA", las
     * 120 unidades en stock se leerían como 120 cajas; y un servicio con ventas
     * convertido en producto haría que el kardex descuente esas ventas de golpe
     * (stock negativo). Para cambiarlo, se crea un producto nuevo.
     *
     * @return array<string, string>|null  errores por campo, o null si se puede
     */
    private function cambioEstructuralBloqueado(Producto $producto, array $data): ?array
    {
        $cambiaTipo = ($data['tipo'] ?? $producto->tipo) !== $producto->tipo;

        $baseActual = $producto->unidades()->where('es_base', true)->where('activo', true)
            ->orderByDesc('id')->value('unidad_medida_id');
        $baseNueva  = collect($data['unidades'] ?? [])->first(fn ($u) => !empty($u['es_base']))['unidad_medida_id'] ?? null;
        $cambiaBase = $baseActual !== null && $baseNueva !== null && (int) $baseActual !== (int) $baseNueva;

        if (!$cambiaTipo && !$cambiaBase) {
            return null;
        }

        $tieneStock = DB::table('stock')->where('producto_id', $producto->id)
            ->whereRaw('ABS(cantidad) > 0.00005')->exists();
        $tieneHistoria = $tieneStock
            || DB::table('movimientos_inventario')->where('producto_id', $producto->id)->exists()
            || DB::table('stock_iniciales')->where('producto_id', $producto->id)->exists()
            || DB::table('entradas_detalle')->where('producto_id', $producto->id)->exists()
            || DB::table('salidas_detalle')->where('producto_id', $producto->id)->exists()
            || DB::table('venta_items')->where('producto_id', $producto->id)->exists();

        if (!$tieneHistoria) {
            return null;
        }

        $motivo = $tieneStock ? 'tiene stock' : 'ya tiene movimientos (compras, ventas o kardex)';
        $errores = [];
        if ($cambiaTipo) {
            $errores['tipo'] = "No se puede cambiar \"{$producto->nombre}\" de "
                . ($producto->tipo === 'servicio' ? 'servicio a producto' : 'producto a servicio')
                . " porque {$motivo}. Crea un producto nuevo con el tipo correcto y desactiva este.";
        }
        if ($cambiaBase) {
            $errores['unidades'] = "No se puede cambiar la unidad base de \"{$producto->nombre}\" porque {$motivo}: "
                . 'todo lo registrado está en la unidad base actual. Agrega la nueva unidad como presentación (con su factor) o crea un producto nuevo.';
        }

        return $errores;
    }

    public function destroy(Request $request, Producto $producto)
    {
        abort_if($producto->empresa_id !== $request->user()->empresa_id, 403);

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
