<?php

use App\Models\Producto;
use App\Models\Stock;
use App\Services\CostoVentaService;
use Illuminate\Support\Facades\DB;
use Tests\Support\TestEnv;

/**
 * Al crear un producto físico se puede escribir su stock inicial y su costo
 * (ambos opcionales). El stock queda como inventario inicial con la fecha del
 * último conteo del almacén, y las ventas de hoy lo descuentan.
 */
beforeEach(function () {
    $this->env = TestEnv::crear();
    $this->actingAs($this->env->admin);
    $this->alm = $this->env->almacen->id;
});

function psCrear($test, array $extra = []): Producto
{
    $test->post(route('catalogo.productos.store'), array_merge([
        'nombre' => 'Fierro 1/2 ' . uniqid(), 'tipo' => 'producto', 'activo' => true, 'incluye_igv' => true,
        'unidades' => [['unidad_medida_id' => $test->env->unidad->id, 'es_base' => true, 'factor_conversion' => 1, 'tipo_precio' => 'fijo', 'precio_venta' => 35]],
    ], $extra))->assertSessionHasNoErrors();

    return Producto::where('empresa_id', $test->env->empresa->id)->latest('id')->firstOrFail();
}

function psInicial($test, Producto $p): ?object
{
    return DB::table('stock_iniciales')->where('almacen_id', $test->alm)->where('producto_id', $p->id)->first();
}

it('sin stock ni costo el producto se crea como siempre', function () {
    $p = psCrear($this);

    expect(psInicial($this, $p))->toBeNull()
        ->and((float) $p->precio_costo)->toBe(0.0);
});

it('con stock y costo arranca con ese inventario inicial, contado ayer si nunca hubo conteo', function () {
    $p = psCrear($this, ['stock_inicial' => 40, 'costo_inicial' => 28.5]);

    $ini = psInicial($this, $p);
    expect((float) $ini->cantidad)->toBe(40.0)
        ->and((float) $ini->costo)->toBe(28.5)
        ->and(substr($ini->fecha, 0, 10))->toBe(now()->subDay()->toDateString());

    $stock = Stock::where('almacen_id', $this->alm)->where('producto_id', $p->id)->first();
    expect((float) $stock->cantidad)->toBe(40.0)
        ->and((float) $stock->costo_promedio)->toBe(28.5);

    // Una venta de hoy lo descuenta (el conteo vale al cierre de ayer)
    DB::table('ajustes_inventario')->insert([
        'empresa_id' => $this->env->empresa->id, 'almacen_id' => $this->alm, 'producto_id' => $p->id,
        'user_id' => $this->env->admin->id, 'numero' => 'AJ-PS1', 'tipo' => 'salida', 'cantidad_base' => 5,
        'fecha' => now()->toDateString(), 'estado' => 'confirmado', 'motivo' => 'test', 'created_at' => now(), 'updated_at' => now(),
    ]);
    expect((float) Stock::reconstruir($this->alm, $p->id)->cantidad)->toBe(35.0);
});

it('usa la fecha del último conteo cargado en el almacén', function () {
    $otro = $this->env->crearProducto(['stock_inicial' => 0]);
    DB::table('stock_iniciales')->insert([
        'empresa_id' => $this->env->empresa->id, 'almacen_id' => $this->alm, 'producto_id' => $otro->id,
        'fecha' => '2026-07-13', 'cantidad' => 3, 'costo' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $p = psCrear($this, ['stock_inicial' => 12]);

    expect(substr(psInicial($this, $p)->fecha, 0, 10))->toBe('2026-07-13');
    expect((float) Stock::where('almacen_id', $this->alm)->where('producto_id', $p->id)->value('cantidad'))->toBe(12.0);

    $props = $this->get(route('catalogo.productos.create'))->original->getData()['page']['props'];
    expect($props['inventarioInicial'])->toBe(['almacen' => $this->env->almacen->nombre, 'fecha' => '2026-07-13']);
});

it('si el último conteo es de hoy, el producto nuevo queda contado ayer', function () {
    $otro = $this->env->crearProducto(['stock_inicial' => 0]);
    DB::table('stock_iniciales')->insert([
        'empresa_id' => $this->env->empresa->id, 'almacen_id' => $this->alm, 'producto_id' => $otro->id,
        'fecha' => now()->toDateString(), 'cantidad' => 3, 'costo' => 1, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $p = psCrear($this, ['stock_inicial' => 8]);

    expect(substr(psInicial($this, $p)->fecha, 0, 10))->toBe(now()->subDay()->toDateString());
});

it('solo con costo queda como costo de referencia para la utilidad', function () {
    $p = psCrear($this, ['costo_inicial' => 6.75]);

    expect(psInicial($this, $p))->toBeNull()
        ->and((float) $p->precio_costo)->toBe(6.75)
        ->and(app(CostoVentaService::class)->paraVender($p, $this->alm))->toBe(6.75);
});

it('un servicio o un producto que no controla stock no carga inventario', function () {
    $servicio = psCrear($this, ['tipo' => 'servicio', 'tipo_precio' => 'fijo', 'precio_venta' => 50, 'unidades' => [], 'stock_inicial' => 10, 'costo_inicial' => 5]);
    expect(psInicial($this, $servicio))->toBeNull()
        ->and((float) $servicio->precio_costo)->toBe(0.0);

    $sinStock = psCrear($this, ['controla_stock' => false, 'stock_inicial' => 10, 'costo_inicial' => 5]);
    expect(psInicial($this, $sinStock))->toBeNull()
        ->and((float) $sinStock->precio_costo)->toBe(5.0);
});

it('no acepta cantidades ni costos negativos', function () {
    $this->post(route('catalogo.productos.store'), [
        'nombre' => 'X', 'tipo' => 'producto', 'stock_inicial' => -1, 'costo_inicial' => -2,
        'unidades' => [['unidad_medida_id' => $this->env->unidad->id, 'es_base' => true, 'factor_conversion' => 1, 'tipo_precio' => 'fijo', 'precio_venta' => 1]],
    ])->assertSessionHasErrors(['stock_inicial', 'costo_inicial']);
});
