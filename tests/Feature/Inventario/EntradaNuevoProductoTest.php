<?php

use App\Models\Producto;
use Illuminate\Support\Facades\DB;
use Tests\Support\TestEnv;

/**
 * Alta rápida de productos desde Nueva / Editar entrada: se crea igual que en
 * el Catálogo, sin stock inicial (lo trae la entrada), y vuelve con la forma
 * del selector de la entrada.
 */
beforeEach(function () {
    $this->env = TestEnv::crear();
    $this->actingAs($this->env->admin);
});

function enpPayload($test, array $extra = []): array
{
    return array_merge([
        'tipo' => 'producto', 'nombre' => 'Codo PVC 1/2 ' . uniqid(), 'incluye_igv' => true, 'activo' => true,
        'unidades' => [['unidad_medida_id' => $test->env->unidad->id, 'es_base' => true, 'factor_conversion' => 1, 'tipo_precio' => 'fijo', 'precio_venta' => 2.5, 'activo' => true]],
    ], $extra);
}

it('crea el producto y lo devuelve listo para la entrada, sin stock inicial', function () {
    $p = $this->postJson(route('inventario.entradas.productos.crear'), enpPayload($this, ['stock_inicial' => 30, 'costo_inicial' => 5]))
        ->assertOk()->json();

    expect($p['tipo'])->toBe('producto')
        ->and($p['unidades'])->toHaveCount(1)
        ->and($p['unidades'][0]['es_base'])->toBeTrue()
        ->and($p['unidades'][0]['unidad_medida']['abreviatura'])->toBe($this->env->unidad->abreviatura);

    expect(DB::table('stock_iniciales')->where('producto_id', $p['id'])->exists())->toBeFalse();
});

it('siempre crea un producto físico', function () {
    $p = $this->postJson(route('inventario.entradas.productos.crear'), enpPayload($this, ['tipo' => 'servicio', 'tipo_precio' => 'fijo', 'precio_venta' => 3]))
        ->assertOk()->json();

    expect($p['tipo'])->toBe('producto');
});

it('valida como el Catálogo', function () {
    $this->postJson(route('inventario.entradas.productos.crear'), ['tipo' => 'producto', 'nombre' => ''])
        ->assertStatus(422)->assertJsonValidationErrors(['nombre', 'unidades']);
});

it('sin permiso de crear productos no se puede', function () {
    $rol = \App\Models\Rol::create(['empresa_id' => $this->env->empresa->id, 'nombre' => 'Almacenero ' . uniqid(), 'es_admin' => false]);
    $user = \App\Models\User::create([
        'empresa_id' => $this->env->empresa->id, 'local_id' => $this->env->local->id, 'rol_id' => $rol->id,
        'name' => 'Almacenero', 'email' => uniqid() . '@test.com', 'password' => 'secreto123', 'activo' => true,
    ]);
    $this->actingAs($user);

    $this->postJson(route('inventario.entradas.productos.crear'), enpPayload($this))->assertForbidden();
    expect(Producto::where('empresa_id', $this->env->empresa->id)->count())->toBe(0);
});

it('Nueva entrada sabe si puede crear productos', function () {
    $props = $this->get(route('inventario.entradas.create'))->original->getData()['page']['props'];

    expect($props['puedeCrearProducto'])->toBeTrue();
});
