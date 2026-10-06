<?php

use App\Models\UnidadMedida;
use Tests\Support\TestEnv;

/**
 * Revisión de inventario (oct 2026) — catálogo. Un producto con stock o con
 * movimientos no puede cambiar de unidad base ni de tipo (producto↔servicio):
 * todo lo registrado está en su unidad base y un servicio no lleva kardex.
 */
beforeEach(function () {
    $this->env = TestEnv::crear();
    $this->actingAs($this->env->admin);
    $this->caja = UnidadMedida::create(['empresa_id' => $this->env->empresa->id, 'nombre' => 'Caja', 'abreviatura' => 'CJA', 'activo' => true]);
});

function rivCatPayload($test, $producto, array $extra = [], ?int $unidadBase = null): array
{
    $base = $producto->unidades()->where('es_base', true)->firstOrFail();

    return array_merge([
        'nombre' => $producto->nombre, 'codigo' => $producto->codigo, 'tipo' => 'producto',
        'tipo_precio' => 'fijo', 'precio_venta' => 10, 'activo' => true, 'incluye_igv' => true,
        'unidades' => [[
            'id' => $base->id, 'unidad_medida_id' => $unidadBase ?? $base->unidad_medida_id, 'es_base' => true,
            'factor_conversion' => 1, 'tipo_precio' => 'fijo', 'precio_venta' => 10, 'activo' => true,
        ]],
    ], $extra);
}

it('bloquea cambiar la unidad base o el tipo de un producto con stock', function () {
    $producto = $this->env->crearProducto(['stock_inicial' => 120]);
    $unidadOriginal = $producto->unidadBase->unidad_medida_id;

    $this->put(route('catalogo.productos.update', $producto), rivCatPayload($this, $producto, [], $this->caja->id))
        ->assertSessionHasErrors('unidades');
    expect($producto->unidades()->where('es_base', true)->value('unidad_medida_id'))->toBe($unidadOriginal);

    $this->put(route('catalogo.productos.update', $producto), rivCatPayload($this, $producto, ['tipo' => 'servicio']))
        ->assertSessionHasErrors('tipo');
    expect($producto->fresh()->tipo)->toBe('producto');

    // Sin stock ni historia sí se puede (producto recién creado, mal configurado).
    $nuevo = $this->env->crearProducto(['stock_inicial' => 0]);
    \App\Models\Stock::where('producto_id', $nuevo->id)->delete();
    $this->put(route('catalogo.productos.update', $nuevo), rivCatPayload($this, $nuevo, [], $this->caja->id))
        ->assertSessionHasNoErrors();
    expect($nuevo->unidades()->where('es_base', true)->value('unidad_medida_id'))->toBe($this->caja->id);
});
