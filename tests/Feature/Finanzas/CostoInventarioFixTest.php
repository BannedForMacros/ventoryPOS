<?php

use App\Models\Producto;
use App\Services\BalanceDiarioService;
use Tests\Support\TestEnv;

/**
 * Fix del costo de inventario:
 *  1) El balance valoriza con costo_promedio cuando precio_costo quedó en 0.
 *  2) Editar un producto ya NO borra su precio_costo.
 */

it('el balance valoriza el stock con costo_promedio cuando precio_costo está en 0', function () {
    $env = TestEnv::crear();
    // Producto con costo 10 y 5 uds → stock.costo_promedio = 10
    $producto = $env->crearProducto(['precio_costo' => 10, 'stock_inicial' => 5]);

    // Simular que una edición borró el precio_costo a 0 (el bug histórico)
    Producto::where('id', $producto->id)->update(['precio_costo' => 0]);

    $balance = app(BalanceDiarioService::class)->generar($env->admin, now()->toDateString());
    $stockItem = $balance->items->firstWhere('categoria', 'stock');

    // 5 × COALESCE(NULLIF(0,0), costo_promedio 10) = 50 (NO 0)
    expect((float) $stockItem->monto)->toBe(50.0);
});

it('editar un producto NO borra su precio_costo', function () {
    $env = TestEnv::crear();
    $this->actingAs($env->admin);

    $producto = $env->crearProducto(['precio_costo' => 8]);
    expect((float) $producto->precio_costo)->toBe(8.0);

    $this->put(route('catalogo.productos.update', $producto), [
        'nombre'         => 'Cemento Editado',
        'tipo'           => 'producto',
        'categoria_id'   => $env->categoria->id,
        'incluye_igv'    => true,
        'controla_stock' => true,
        'unidades'       => [[
            'id'                => $producto->unidadBase->id,
            'unidad_medida_id'  => $env->unidad->id,
            'es_base'           => true,
            'factor_conversion' => 1,
            'tipo_precio'       => 'fijo',
            'precio_venta'      => 15,
            'activo'            => true,
        ]],
    ])->assertSessionHasNoErrors();

    $fresh = $producto->fresh();
    expect($fresh->nombre)->toBe('Cemento Editado');   // el cambio sí se aplicó
    expect((float) $fresh->precio_costo)->toBe(8.0);    // el costo se conservó (no 0)
});
