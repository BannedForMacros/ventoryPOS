<?php

use App\Models\Stock;
use Tests\Support\LectorXlsx;
use Tests\Support\TestEnv;

/**
 * La exportación de stock.
 *
 * ─── POR QUÉ SE REESCRIBIERON ESTAS DOS PRUEBAS ────────────────────────────────
 *
 * Comprobaban un CSV. El 28 de agosto la exportación pasó a Excel y nadie las
 * actualizó, así que llevaban casi un mes fallando: buscaban texto plano dentro de
 * un ZIP y, naturalmente, no lo encontraban. El sistema funcionaba; la prueba no.
 *
 * Ahora se lee la hoja de verdad. Y se comprueba además el tipo de contenido, que es
 * lo que hace que el navegador abra el archivo en vez de enseñar caracteres raros.
 */

beforeEach(function () {
    $this->env = TestEnv::crear();
    $this->actingAs($this->env->admin);
});

it('exporta el stock actual a Excel respetando filtros', function () {
    $producto = $this->env->crearProducto(['nombre' => 'Producto A', 'precio_costo' => 10, 'stock_inicial' => 0]);
    Stock::updateOrCreate(
        ['almacen_id' => $this->env->almacen->id, 'producto_id' => $producto->id],
        ['cantidad' => 10, 'costo_promedio' => 5]
    );

    $response = $this->get(route('inventario.stock.exportar'));

    $response->assertOk();
    $response->assertHeader(
        'Content-Type',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    );

    expect(LectorXlsx::texto($response->getContent()))->toContain('Producto A');
});

it('la hoja incluye cabeceras y estado de stock', function () {
    $producto = $this->env->crearProducto(['nombre' => 'Producto Bajo', 'precio_costo' => 10, 'stock_inicial' => 0]);
    Stock::updateOrCreate(
        ['almacen_id' => $this->env->almacen->id, 'producto_id' => $producto->id],
        ['cantidad' => 3, 'costo_promedio' => 10]
    );

    $texto = LectorXlsx::texto($this->get(route('inventario.stock.exportar'))->getContent());

    // Las nueve cabeceras, una por una: si alguien quita una columna, esto lo dice
    // por su nombre en vez de fallar con «la cadena no coincide».
    foreach (['Almacén', 'Producto', 'Código', 'Categoría', 'Unidad base',
              'Cantidad', 'Costo promedio', 'Valor total', 'Estado'] as $cabecera) {
        expect($texto)->toContain($cabecera);
    }

    // 3 unidades está por debajo del umbral: tiene que salir marcado.
    expect($texto)->toContain('Bajo');
});
