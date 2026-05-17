<?php

use App\Models\Stock;
use App\Services\VentaService;
use Tests\Support\TestEnv;

beforeEach(function () {
    $this->env = TestEnv::crear();
    $this->actingAs($this->env->admin);
});

it('Stock::reconstruir NO trunca a 0 cuando los movimientos dan saldo negativo', function () {
    // Escenario: venta confirmada de 10, sin entradas que la respalden.
    // El stock real es -10 (vendimos lo que no había).
    $turno    = $this->env->abrirTurno();
    $producto = $this->env->crearProducto(['precio_venta' => 5, 'stock_inicial' => 10, 'incluye_igv' => false]);

    // Vendo 10 unidades — stock queda en 0
    app(VentaService::class)->crear([
        'tipo_comprobante' => 'ticket',
        'items' => [[
            'producto_id'        => $producto->id,
            'producto_unidad_id' => $producto->unidadBase->id,
            'cantidad'           => 10,
            'precio_unitario'    => 5,
        ]],
        'pagos' => [[
            'metodo_pago_id' => $this->env->metodo('efectivo')->id,
            'monto'          => 50,
        ]],
    ], $this->env->admin, $turno);

    // Manualmente borro el stock inicial que crearProducto insertó (simulamos
    // que esa cantidad de 10 nunca debió estar — no había entrada confirmada).
    // Reconstruimos: solo hay 1 venta de 10 → saldo debería ser -10.
    Stock::reconstruir($this->env->almacen->id, $producto->id);

    $stock = Stock::where('almacen_id', $this->env->almacen->id)
        ->where('producto_id', $producto->id)
        ->first();

    expect((float) $stock->cantidad)->toBe(-10.0);
});

it('scopeNegativo filtra solo los stocks con saldo < 0', function () {
    $p1 = $this->env->crearProducto(['stock_inicial' => 10]);
    $p2 = $this->env->crearProducto(['stock_inicial' => 0]);

    // Forzar p2 a -3 directamente (simulando reconstruir tras inconsistencia)
    Stock::where('producto_id', $p2->id)->update(['cantidad' => -3]);

    $negativos = Stock::whereIn('almacen_id', [$this->env->almacen->id])
        ->negativo()
        ->get();

    expect($negativos)->toHaveCount(1);
    expect($negativos->first()->producto_id)->toBe($p2->id);
});

it('StockController::index expone stocksNegativosCount y marca es_negativo en cada fila', function () {
    $p1 = $this->env->crearProducto(['stock_inicial' => 50]);
    $p2 = $this->env->crearProducto(['stock_inicial' => 0]);

    Stock::where('producto_id', $p2->id)->update(['cantidad' => -2]);

    $response = $this->get(route('inventario.stock.index'));
    $props = $response->original->getData()['page']['props'];

    expect($props['stocksNegativosCount'])->toBe(1);

    $rowP2 = collect($props['stocks'])->firstWhere('producto_id', $p2->id);
    expect($rowP2['es_negativo'])->toBeTrue();
    expect((float) $rowP2['cantidad'])->toBe(-2.0);

    $rowP1 = collect($props['stocks'])->firstWhere('producto_id', $p1->id);
    expect($rowP1['es_negativo'])->toBeFalse();
});
