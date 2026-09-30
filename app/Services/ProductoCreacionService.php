<?php

namespace App\Services;

use App\Models\Almacen;
use App\Models\Producto;
use App\Models\UnidadMedida;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Crea un producto o servicio con sus presentaciones y, si es un producto
 * físico, su stock inicial y su costo (opcionales). Lo usan el Catálogo y el
 * alta rápida desde el POS: un producto nace igual desde cualquiera de los dos.
 *
 * @phpstan-type Resultado array{producto: Producto, aviso: ?string}
 */
class ProductoCreacionService
{
    public function __construct(
        private LocalScopeService $scope,
        private InventarioInicialService $inicial,
    ) {}

    /**
     * @param  array  $data  Validado con ProductoRequest.
     * @return array{producto: Producto, aviso: ?string}
     */
    public function crear(User $user, array $data): array
    {
        $producto = DB::transaction(function () use ($data, $user) {
            $esProducto = $data['tipo'] === 'producto';

            $producto = Producto::create([
                'empresa_id'     => $user->empresa_id,
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

            return $producto;
        });

        return ['producto' => $producto, 'aviso' => $this->cargarInventarioInicial($user, $producto, $data)];
    }

    /** Almacén donde queda el stock inicial: el de ventas del usuario, o el primero que puede ver. */
    public function almacenInicial(User $user): ?Almacen
    {
        return $this->scope->almacenParaVentas($user) ?? $this->scope->almacenesVisibles($user)->first();
    }

    /**
     * Stock inicial y costo escritos al crear un producto físico (opcionales).
     *  - Con stock: queda como su inventario inicial en el almacén del usuario,
     *    con la fecha del último conteo (ver fechaParaProductoNuevo), y el
     *    costo es su costo promedio.
     *  - Solo costo: se guarda como costo de referencia del producto; se usa
     *    para la utilidad de sus ventas hasta que entre la primera compra.
     */
    private function cargarInventarioInicial(User $user, Producto $producto, array $data): ?string
    {
        if (!$producto->esProductoFisico()) {
            return null;
        }

        $stock = (float) ($data['stock_inicial'] ?? 0);
        $costo = isset($data['costo_inicial']) && $data['costo_inicial'] !== '' ? (float) $data['costo_inicial'] : null;

        if ($stock > 0 && $producto->controla_stock !== false) {
            $almacen = $this->almacenInicial($user);
            if (!$almacen) {
                return 'No se cargó el stock inicial: tu usuario no tiene un almacén asignado. Cárgalo en Inventario inicial.';
            }

            $fecha = $this->inicial->fechaParaProductoNuevo($almacen->id);
            $this->inicial->guardar($producto->empresa_id, $almacen->id, $fecha, [
                ['producto_id' => $producto->id, 'cantidad' => $stock, 'costo' => $costo],
            ]);

            AuditoriaService::log('inventario_inicial.cargado', $almacen, [
                'origen' => 'producto_nuevo', 'fecha' => $fecha, 'productos' => 1, 'producto_id' => $producto->id,
            ], $user);

            return 'Arranca con ' . rtrim(rtrim(number_format($stock, 4, '.', ''), '0'), '.') . " en stock en {$almacen->nombre}.";
        }

        if ($costo !== null && $costo > 0) {
            $producto->update(['precio_costo' => round($costo, 4)]);
        }

        return null;
    }

    /**
     * Para servicios sin variantes explicitas, crea una presentacion default
     * con la unidad "Servicio" (la crea si no existe) y el precio del servicio.
     * Esto garantiza que todo servicio sea agregable desde el POS.
     */
    public function crearPresentacionDefaultServicio(Producto $producto, array $data): void
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
}
