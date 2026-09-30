<?php

use App\Models\Producto;
use App\Models\Stock;
use Illuminate\Support\Facades\DB;
use Tests\Support\TestEnv;

/**
 * Alta rápida de productos desde el POS: se crea igual que en el Catálogo y
 * vuelve con la forma de la búsqueda del POS, lista para el carrito.
 */
beforeEach(function () {
    $this->env = TestEnv::crear();
    $this->actingAs($this->env->admin);
});

function pnPayload($test, array $extra = []): array
{
    return array_merge([
        'tipo' => 'producto', 'nombre' => 'Tubo PVC 1/2 ' . uniqid(), 'incluye_igv' => true, 'activo' => true,
        'unidades' => [['unidad_medida_id' => $test->env->unidad->id, 'es_base' => true, 'factor_conversion' => 1, 'tipo_precio' => 'fijo', 'precio_venta' => 8.5, 'activo' => true]],
    ], $extra);
}

it('entrega los datos del formulario', function () {
    $this->env->crearProducto(['incluye_igv' => true]);
    $this->env->crearProducto(['incluye_igv' => true]);
    $this->env->crearProducto(['incluye_igv' => false]);

    $d = $this->getJson(route('pos.productos.nuevo'))->assertOk()->json();

    expect($d['incluye_igv'])->toBeTrue()
        ->and(collect($d['unidades'])->pluck('id'))->toContain($this->env->unidad->id)
        ->and($d['inventarioInicial']['almacen'])->toBe($this->env->almacen->nombre);
});

it('crea el producto y lo devuelve listo para el carrito, con su stock', function () {
    $r = $this->postJson(route('pos.productos.crear'), pnPayload($this, ['stock_inicial' => 30, 'costo_inicial' => 5]))
        ->assertOk()->json();

    $p = $r['producto'];
    expect($p['tipo'])->toBe('producto')
        ->and($p['unidades'])->toHaveCount(1)
        ->and((float) $p['unidades'][0]['precio_venta'])->toBe(8.5)
        ->and($p['unidades'][0]['unidad_medida']['nombre'])->toBe($this->env->unidad->nombre)
        ->and((float) $p['stock_disponible'])->toBe(30.0)
        ->and((float) $p['stock_costo_promedio'])->toBe(5.0)
        ->and($r['aviso'])->toContain('30');

    expect(DB::table('stock_iniciales')->where('producto_id', $p['id'])->exists())->toBeTrue();
});

it('un servicio también se crea desde el POS', function () {
    $p = $this->postJson(route('pos.productos.crear'), [
        'tipo' => 'servicio', 'nombre' => 'Corte de fierro', 'tipo_precio' => 'fijo', 'precio_venta' => 3, 'incluye_igv' => true,
    ])->assertOk()->json('producto');

    expect($p['tipo'])->toBe('servicio')
        ->and($p['unidades'])->toHaveCount(1)
        ->and($p['stock_disponible'])->toBeNull();
});

it('valida como el Catálogo', function () {
    $this->postJson(route('pos.productos.crear'), ['tipo' => 'producto', 'nombre' => ''])
        ->assertStatus(422)->assertJsonValidationErrors(['nombre', 'unidades']);

    $existente = $this->env->crearProducto(['codigo' => 'TUB-01']);
    $this->postJson(route('pos.productos.crear'), pnPayload($this, ['codigo' => 'TUB-01']))
        ->assertStatus(422)->assertJsonValidationErrors(['codigo']);
});

it('sin permiso de crear productos no se puede', function () {
    $rol = \App\Models\Rol::create(['empresa_id' => $this->env->empresa->id, 'nombre' => 'Cajera ' . uniqid(), 'es_admin' => false]);
    $cajera = \App\Models\User::create([
        'empresa_id' => $this->env->empresa->id, 'local_id' => $this->env->local->id, 'rol_id' => $rol->id,
        'name' => 'Cajera', 'email' => uniqid() . '@test.com', 'password' => 'secreto123', 'activo' => true,
    ]);
    $this->actingAs($cajera);

    $this->postJson(route('pos.productos.crear'), pnPayload($this))->assertForbidden();
    $this->getJson(route('pos.productos.nuevo'))->assertForbidden();
    expect(Producto::where('empresa_id', $this->env->empresa->id)->count())->toBe(0);
});

it('el POS sabe si puede crear productos', function () {
    $this->env->abrirTurno();
    $props = $this->get(route('pos.index'))->original->getData()['page']['props'];

    expect($props['puedeCrearProducto'])->toBeTrue();
});
