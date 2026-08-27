<?php

use App\Models\Stock;
use Tests\Support\TestEnv;

beforeEach(function () {
    $this->env = TestEnv::crear();
    $this->actingAs($this->env->admin);
});

it('exporta el stock actual a csv respetando filtros', function () {
    $producto = $this->env->crearProducto(['nombre' => 'Producto A', 'precio_costo' => 10, 'stock_inicial' => 0]);
    Stock::updateOrCreate(
        ['almacen_id' => $this->env->almacen->id, 'producto_id' => $producto->id],
        ['cantidad' => 10, 'costo_promedio' => 5]
    );

    $response = $this->get(route('inventario.stock.exportar'));
    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/csv; charset=utf-8');
    $this->assertStringContainsString('Producto A', $response->getContent());
});

it('el csv incluye cabeceras y estado de stock', function () {
    $producto = $this->env->crearProducto(['nombre' => 'Producto Bajo', 'precio_costo' => 10, 'stock_inicial' => 0]);
    Stock::updateOrCreate(
        ['almacen_id' => $this->env->almacen->id, 'producto_id' => $producto->id],
        ['cantidad' => 3, 'costo_promedio' => 10]
    );

    $response = $this->get(route('inventario.stock.exportar'));
    $content = $response->getContent();
    $this->assertStringContainsString('"Almacén","Producto","Código","Categoría","Unidad base","Cantidad","Costo promedio","Valor total","Estado"', $content);
    $this->assertStringContainsString('"Bajo"', $content);
});
