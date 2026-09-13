<?php

use App\Jobs\ReconstruirParKardex;
use App\Models\Stock;
use App\Services\KardexService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Support\TestEnv;

/**
 * Bloque 0 — motor único de inventario (KardexService).
 *
 * Cada caso reproduce una falla real de Ferretería H&C (setiembre 2026) o una
 * garantía del motor: stock y kardex salen del MISMO cálculo, en vivo y
 * reconstruido dan igual, y lo que se desordena se corrige solo.
 */
beforeEach(function () {
    $this->env = TestEnv::crear();
    $this->actingAs($this->env->admin);
    $this->kardex = app(KardexService::class);
    $this->alm = $this->env->almacen->id;
});

/** Entrada CONFIRMADA como documento (sin pasar por el observer). */
function kxEntrada(TestEnv $env, int $productoId, string $fecha, float $cantidad, float $costo): int
{
    $id = DB::table('entradas')->insertGetId([
        'empresa_id' => $env->empresa->id,
        'almacen_id' => $env->almacen->id,
        'user_id'    => $env->admin->id,
        'tipo'       => 'compra',
        'fecha'      => $fecha,
        'estado'     => 'confirmado',
        'total'      => round($cantidad * $costo, 2),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    DB::table('entradas_detalle')->insert([
        'entrada_id'        => $id,
        'producto_id'       => $productoId,
        'unidad_medida_id'  => $env->unidad->id,
        'cantidad'          => $cantidad,
        'factor_conversion' => 1,
        'cantidad_base'     => $cantidad,
        'precio_costo'      => $costo,
        'subtotal'          => round($cantidad * $costo, 2),
        'created_at'        => now(),
        'updated_at'        => now(),
    ]);

    return $id;
}

/** Ajuste de salida CONFIRMADO como documento. */
function kxAjusteSalida(TestEnv $env, int $productoId, string $fecha, float $cantidad): void
{
    DB::table('ajustes_inventario')->insert([
        'empresa_id'    => $env->empresa->id,
        'almacen_id'    => $env->almacen->id,
        'producto_id'   => $productoId,
        'user_id'       => $env->admin->id,
        'numero'        => 'AJ-T' . uniqid(),
        'tipo'          => 'salida',
        'cantidad_base' => $cantidad,
        'fecha'         => $fecha,
        'estado'        => 'confirmado',
        'motivo'        => 'test',
        'created_at'    => now(),
        'updated_at'    => now(),
    ]);
}

function kxFilas(int $almacenId, int $productoId): \Illuminate\Support\Collection
{
    return DB::table('movimientos_inventario')
        ->where('almacen_id', $almacenId)->where('producto_id', $productoId)
        ->orderBy('fecha')->orderBy('id')->get();
}

function kxStock(int $almacenId, int $productoId): Stock
{
    return Stock::where('almacen_id', $almacenId)->where('producto_id', $productoId)->firstOrFail();
}

/** Escenario base: compra 10 a 5, compra 10 a 8, salen 15, compra 5 a 10. */
function kxEscenarioBase(TestEnv $env, int $productoId): void
{
    kxEntrada($env, $productoId, '2020-01-10', 10, 5);
    kxEntrada($env, $productoId, '2020-01-20', 10, 8);
    kxAjusteSalida($env, $productoId, '2020-01-25', 15);
    kxEntrada($env, $productoId, '2020-01-30', 5, 10);
}

it('el costo promedio no explota cuando la compra llega con stock negativo', function () {
    // Ladrillo TAYSON, 10/09: se vendieron 2,290 antes de registrar la compra de
    // 2,291 a S/ 0.99. La fórmula vieja dividía el total entre 1 unidad → S/ 2,268.09.
    $p = $this->env->crearProducto(['stock_inicial' => 0, 'precio_costo' => 0]);

    Stock::ajustar($this->alm, $p->id, -2290, 0, permitirNegativo: true);
    $stock = Stock::ajustar($this->alm, $p->id, 2291, 0.99);

    expect((float) $stock->cantidad)->toBe(1.0);
    expect((float) $stock->costo_promedio)->toBe(0.99);
});

it('la reconstrucción promedia descontando lo que salió (fórmula única)', function () {
    $p = $this->env->crearProducto(['stock_inicial' => 0, 'precio_costo' => 0]);
    kxEscenarioBase($this->env, $p->id);

    $this->kardex->reconstruirPar($this->alm, $p->id);

    // (10·5 + 10·8) / 20 = 6.5 → salen 15, quedan 5 a 6.5 → (5·6.5 + 5·10) / 10 = 8.25.
    // El motor viejo de la tabla stock no restaba la salida: (50 + 80 + 50) / 25 = 7.2.
    $stock = kxStock($this->alm, $p->id);
    expect((float) $stock->cantidad)->toBe(10.0);
    expect((float) $stock->costo_promedio)->toBe(8.25);

    $ultima = kxFilas($this->alm, $p->id)->last();
    expect((float) $ultima->saldo_cantidad)->toBe(10.0);
    expect((float) $ultima->costo_promedio)->toBe(8.25);
});

it('en vivo y reconstruido dan exactamente lo mismo', function () {
    $p = $this->env->crearProducto(['stock_inicial' => 0, 'precio_costo' => 0]);
    kxEscenarioBase($this->env, $p->id);

    $ctx = fn (string $tipo, string $fecha) => ['tipo' => $tipo, 'fecha' => $fecha, 'empresa_id' => $this->env->empresa->id];
    Stock::ajustar($this->alm, $p->id, 10, 5, contexto: $ctx('entrada', '2020-01-10'));
    Stock::ajustar($this->alm, $p->id, 10, 8, contexto: $ctx('entrada', '2020-01-20'));
    Stock::ajustar($this->alm, $p->id, -15, 0, contexto: $ctx('ajuste_salida', '2020-01-25'));
    Stock::ajustar($this->alm, $p->id, 5, 10, contexto: $ctx('entrada', '2020-01-30'));

    $r = $this->kardex->reconstruirPar($this->alm, $p->id);

    expect($r['stock_cambia'])->toBeFalse();
    expect($r['kardex_cambia'])->toBeFalse();
    expect((float) kxStock($this->alm, $p->id)->costo_promedio)->toBe(8.25);
});

it('reconstruir dos veces no reescribe nada', function () {
    $p = $this->env->crearProducto(['stock_inicial' => 0, 'precio_costo' => 0]);
    kxEscenarioBase($this->env, $p->id);

    $this->kardex->reconstruirPar($this->alm, $p->id);
    $ids = kxFilas($this->alm, $p->id)->pluck('id')->all();

    $r = $this->kardex->reconstruirPar($this->alm, $p->id);

    expect($r['kardex_cambia'])->toBeFalse();
    expect($r['stock_cambia'])->toBeFalse();
    expect(kxFilas($this->alm, $p->id)->pluck('id')->all())->toBe($ids);
});

it('corrige un stock descuadrado e informa el antes y el después', function () {
    $p = $this->env->crearProducto(['stock_inicial' => 0, 'precio_costo' => 0]);
    kxEscenarioBase($this->env, $p->id);
    $this->kardex->reconstruirPar($this->alm, $p->id);

    Stock::where('almacen_id', $this->alm)->where('producto_id', $p->id)
        ->update(['cantidad' => 999, 'costo_promedio' => 123]);

    $r = $this->kardex->reconstruirPar($this->alm, $p->id);

    expect($r['stock_cambia'])->toBeTrue();
    expect($r['cantidad_antes'])->toBe(999.0);
    expect($r['cantidad_despues'])->toBe(10.0);
    expect((float) kxStock($this->alm, $p->id)->costo_promedio)->toBe(8.25);
});

it('no pone en cero un stock que no tiene ningún documento que lo respalde', function () {
    $p = $this->env->crearProducto(['stock_inicial' => 50, 'precio_costo' => 3]);

    $r = $this->kardex->reconstruirPar($this->alm, $p->id);

    expect($r['sin_respaldo'])->toBeTrue();
    expect((float) kxStock($this->alm, $p->id)->cantidad)->toBe(50.0);
    expect(kxFilas($this->alm, $p->id))->toHaveCount(0);
});

it('una compra cargada tarde se reordena sola y corrige el costo', function () {
    config(['inventario.autocorreccion_kardex' => true]);
    $p   = $this->env->crearProducto(['stock_inicial' => 0, 'precio_costo' => 0]);
    $ctx = fn (string $tipo, string $fecha) => ['tipo' => $tipo, 'fecha' => $fecha, 'empresa_id' => $this->env->empresa->id];

    kxEntrada($this->env, $p->id, '2020-03-01', 100, 2);
    Stock::ajustar($this->alm, $p->id, 100, 2, contexto: $ctx('entrada', '2020-03-01'));
    kxAjusteSalida($this->env, $p->id, '2020-03-05', 100);
    Stock::ajustar($this->alm, $p->id, -100, 0, contexto: $ctx('ajuste_salida', '2020-03-05'));

    // La compra del 03/03 se registra recién ahora. En vivo encontraba saldo 0 y el
    // costo quedaba en 4. En su orden real: 100 a 2 + 100 a 4 → costo 3; salen 100.
    kxEntrada($this->env, $p->id, '2020-03-03', 100, 4);
    Stock::ajustar($this->alm, $p->id, 100, 4, contexto: $ctx('entrada', '2020-03-03'));

    $stock = kxStock($this->alm, $p->id);
    expect((float) $stock->cantidad)->toBe(100.0);
    expect((float) $stock->costo_promedio)->toBe(3.0);
    expect(kxFilas($this->alm, $p->id)->pluck('tipo')->all())->toBe(['entrada', 'entrada', 'ajuste_salida']);
});

it('una anulación o reverso agenda la reconstrucción del producto', function () {
    config(['inventario.autocorreccion_kardex' => true]);
    Queue::fake();
    $p = $this->env->crearProducto(['stock_inicial' => 10]);

    Stock::ajustar($this->alm, $p->id, 5, contexto: ['tipo' => 'venta_anulacion', 'fecha' => now()]);

    Queue::assertPushed(ReconstruirParKardex::class,
        fn ($job) => $job->productoId === $p->id && $job->motivo === 'venta_anulacion');
});

it('con la autocorrección apagada no agenda nada', function () {
    config(['inventario.autocorreccion_kardex' => false]);
    Queue::fake();
    $p = $this->env->crearProducto(['stock_inicial' => 10]);

    Stock::ajustar($this->alm, $p->id, 5, contexto: ['tipo' => 'venta_anulacion', 'fecha' => now()]);

    Queue::assertNothingPushed();
});

it('el comando en modo simulación no escribe nada', function () {
    $p = $this->env->crearProducto(['stock_inicial' => 0, 'precio_costo' => 0]);
    kxEscenarioBase($this->env, $p->id);
    $this->kardex->reconstruirPar($this->alm, $p->id);
    Stock::where('almacen_id', $this->alm)->where('producto_id', $p->id)->update(['cantidad' => 999]);

    $this->artisan('kardex:reconstruir', ['--almacen' => $this->alm, '--simular' => true])->assertSuccessful();

    expect((float) kxStock($this->alm, $p->id)->cantidad)->toBe(999.0);
});

it('el autocontrol nocturno corrige, deja constancia y lo muestra en Stock', function () {
    $p = $this->env->crearProducto(['stock_inicial' => 0, 'precio_costo' => 0, 'nombre' => 'Ladrillo de prueba']);
    kxEscenarioBase($this->env, $p->id);
    $this->kardex->reconstruirPar($this->alm, $p->id);
    Stock::where('almacen_id', $this->alm)->where('producto_id', $p->id)->update(['cantidad' => 110]);

    $this->artisan('inventario:autocontrol', ['--empresa' => $this->env->empresa->id])->assertSuccessful();

    expect((float) kxStock($this->alm, $p->id)->cantidad)->toBe(10.0);

    $audit = \App\Models\Auditoria::where('empresa_id', $this->env->empresa->id)
        ->where('accion', 'stock.autoreparado')->first();
    expect($audit)->not->toBeNull();
    expect($audit->user_name)->toBe('Sistema');
    expect($audit->contexto['stock_corregidos'])->toBe(1);

    $props = $this->get(route('inventario.stock.index'))->original->getData()['page']['props'];
    expect($props['autocorreccion']['stock_corregidos'])->toBe(1);
    expect($props['autocorreccion']['productos'][0]['producto'])->toBe('Ladrillo de prueba');
    expect($props['autocorreccion']['productos'][0]['cantidad_despues'])->toBe(10.0);
});

it('el autocontrol no deja constancia cuando todo está cuadrado', function () {
    $p = $this->env->crearProducto(['stock_inicial' => 0, 'precio_costo' => 0]);
    kxEscenarioBase($this->env, $p->id);
    $this->kardex->reconstruirPar($this->alm, $p->id);

    $this->artisan('inventario:autocontrol', ['--empresa' => $this->env->empresa->id])->assertSuccessful();

    expect(\App\Models\Auditoria::where('empresa_id', $this->env->empresa->id)
        ->where('accion', 'stock.autoreparado')->exists())->toBeFalse();
});
