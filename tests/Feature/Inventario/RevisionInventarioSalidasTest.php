<?php

use App\Models\Almacen;
use App\Models\Empresa;
use App\Models\Local;
use App\Models\Salida;
use App\Models\SalidaTipo;
use Tests\Support\TestEnv;

/**
 * Revisión de inventario (oct 2026) — editar una salida aceptaba un tipo de
 * salida de OTRA empresa y moverla a un almacén al que el usuario no tiene
 * acceso (store sí lo revisaba, update no).
 */
beforeEach(function () {
    $this->env = TestEnv::crear();
    $this->actingAs($this->env->admin);
    $this->producto = $this->env->crearProducto(['stock_inicial' => 50]);
    $this->tipo = SalidaTipo::create(['empresa_id' => $this->env->empresa->id, 'nombre' => 'Merma', 'slug' => 'merma', 'activo' => true]);

    $this->salida = Salida::create([
        'empresa_id' => $this->env->empresa->id, 'almacen_id' => $this->env->almacen->id,
        'user_id' => $this->env->admin->id, 'salida_tipo_id' => $this->tipo->id,
        'fecha' => now()->toDateString(), 'estado' => 'borrador', 'total' => 0,
    ]);
});

function rivSalPayload($test, array $extra = []): array
{
    return array_merge([
        'almacen_id' => $test->env->almacen->id, 'salida_tipo_id' => $test->tipo->id, 'fecha' => now()->toDateString(),
        'detalles' => [['producto_id' => $test->producto->id, 'unidad_medida_id' => $test->env->unidad->id, 'cantidad' => 2, 'factor_conversion' => 1]],
    ], $extra);
}

it('editar una salida no acepta tipos de otra empresa ni almacenes sin acceso', function () {
    $otra = TestEnv::crear();
    $tipoAjeno = SalidaTipo::create(['empresa_id' => $otra->empresa->id, 'nombre' => 'Ajeno', 'slug' => 'ajeno', 'activo' => true]);

    $this->put(route('inventario.salidas.update', $this->salida), rivSalPayload($this, ['salida_tipo_id' => $tipoAjeno->id]))
        ->assertSessionHasErrors('salida_tipo_id');
    expect($this->salida->fresh()->salida_tipo_id)->toBe($this->tipo->id);

    // Almacén de OTRO local de la misma empresa: el admin está asignado a su local.
    $otroLocal = Local::create(['empresa_id' => $this->env->empresa->id, 'nombre' => 'Local 2', 'direccion' => 'x', 'activo' => true]);
    $almAjeno  = Almacen::create(['empresa_id' => $this->env->empresa->id, 'local_id' => $otroLocal->id, 'nombre' => 'Alm 2', 'tipo' => 'local', 'activo' => true]);

    $this->put(route('inventario.salidas.update', $this->salida), rivSalPayload($this, ['almacen_id' => $almAjeno->id]))
        ->assertForbidden();
    expect($this->salida->fresh()->almacen_id)->toBe($this->env->almacen->id);

    // Lo válido sigue funcionando.
    $this->put(route('inventario.salidas.update', $this->salida), rivSalPayload($this))->assertSessionHasNoErrors();
    expect($this->salida->fresh()->detalles()->count())->toBe(1);
});
