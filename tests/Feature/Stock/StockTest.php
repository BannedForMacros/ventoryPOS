<?php

use App\Exceptions\InsufficientStockException;
use App\Models\Stock;
use Tests\Support\TestEnv;

beforeEach(function () {
    $this->env = TestEnv::crear();
});

it('ajustar con cantidad positiva suma al stock y recalcula costo promedio', function () {
    $producto = $this->env->crearProducto(['stock_inicial' => 10, 'precio_costo' => 6]);

    // Entrada de 5 unidades a costo 8 → CPP = (10*6 + 5*8) / 15 = 100/15 ≈ 6.6667
    $stock = Stock::ajustar($this->env->almacen->id, $producto->id, 5, 8);

    expect((float) $stock->cantidad)->toBe(15.0);
    expect((float) $stock->costo_promedio)->toBe(round(100 / 15, 4));
});

it('ajustar con cantidad negativa descuenta el stock', function () {
    $producto = $this->env->crearProducto(['stock_inicial' => 20]);

    $stock = Stock::ajustar($this->env->almacen->id, $producto->id, -7);

    expect((float) $stock->cantidad)->toBe(13.0);
});

it('lanza InsufficientStockException cuando una salida supera el disponible', function () {
    $producto = $this->env->crearProducto(['stock_inicial' => 3, 'nombre' => 'Producto escaso']);

    Stock::ajustar($this->env->almacen->id, $producto->id, -10);
})->throws(InsufficientStockException::class);

it('InsufficientStockException expone el nombre, disponible y solicitado', function () {
    $producto = $this->env->crearProducto(['stock_inicial' => 2, 'nombre' => 'Alfajor']);

    try {
        Stock::ajustar($this->env->almacen->id, $producto->id, -5);
        $this->fail('Debió lanzar InsufficientStockException');
    } catch (InsufficientStockException $e) {
        expect($e->productoNombre)->toBe('Alfajor');
        expect((float) $e->disponible)->toBe(2.0);
        expect((float) $e->solicitado)->toBe(5.0);
    }
});

it('permitirNegativo:true deja pasar la salida aunque excede (ajuste administrativo)', function () {
    $producto = $this->env->crearProducto(['stock_inicial' => 2]);

    $stock = Stock::ajustar($this->env->almacen->id, $producto->id, -5, 0, permitirNegativo: true);

    expect((float) $stock->cantidad)->toBe(-3.0);
});

it('si el row de stock no existe, ajustar lo crea idempotentemente (INSERT ON CONFLICT)', function () {
    // crearProducto inserta stock con cantidad=stock_inicial; usamos un segundo
    // producto y eliminamos su stock para forzar el path de creación.
    $producto = $this->env->crearProducto(['stock_inicial' => 0]);
    Stock::where('producto_id', $producto->id)->delete();

    $stock = Stock::ajustar($this->env->almacen->id, $producto->id, 5, 10);

    expect($stock->exists)->toBeTrue();
    expect((float) $stock->cantidad)->toBe(5.0);
    expect((float) $stock->costo_promedio)->toBe(10.0);
});
