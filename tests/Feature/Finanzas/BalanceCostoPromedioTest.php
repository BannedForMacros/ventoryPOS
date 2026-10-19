<?php

use App\Models\Stock;
use App\Services\BalanceDiarioService;
use Tests\Support\TestEnv;

/**
 * El balance valoriza el stock al COSTO PROMEDIO, el mismo de Inventario >
 * Stock. Antes usaba la última compra: 4 kg comprados caros re-valorizaban
 * miles de kg comprados baratos (caso Alambre 16 de Ferretería H&C).
 */
it('una compra chica y cara no re-valoriza todo el stock del balance', function () {
    $env = TestEnv::crear();
    $producto = $env->crearProducto(['precio_venta' => 5, 'precio_costo' => 3.18, 'stock_inicial' => 1000]);

    Stock::ajustar($env->almacen->id, $producto->id, 4, 3.50);   // compra chica a 3.50

    $stock = Stock::where('producto_id', $producto->id)->firstOrFail();
    $valorInventario = round((float) $stock->cantidad * (float) $stock->costo_promedio, 2);

    $servicio = app(BalanceDiarioService::class);
    $hoy = now()->toDateString();
    $linea = (float) $servicio->generar($env->admin, $hoy)->items->firstWhere('categoria', 'stock')->monto;

    expect($linea)->toBe($valorInventario)               // = Inventario > Stock
        ->and($linea)->toBeLessThan(1004 * 3.5 - 100);   // no a la última compra (3,514)

    $detalle = $servicio->desgloseStock($env->empresa->id, $hoy)['p' . $producto->id]['detalle'];
    expect($detalle)->toContain('× S/ ' . number_format((float) $stock->costo_promedio, 4));
});
