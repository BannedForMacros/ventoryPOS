<?php

use App\Models\Almacen;
use App\Models\Auditoria;
use App\Models\Entrada;
use App\Models\EntradaDetalle;
use App\Models\Transferencia;
use App\Models\TransferenciaDetalle;
use Tests\Support\TestEnv;

beforeEach(function () {
    $this->env = TestEnv::crear();
    $this->actingAs($this->env->admin);
});

it('eliminar entrada en borrador deja registro de auditoría con snapshot de productos', function () {
    $producto = $this->env->crearProducto(['nombre' => 'Vacuna canina', 'precio_costo' => 15]);

    $entrada = Entrada::create([
        'empresa_id'       => $this->env->empresa->id,
        'almacen_id'       => $this->env->almacen->id,
        'user_id'          => $this->env->admin->id,
        'numero_documento' => 'F001-100',
        'tipo'             => 'compra',
        'fecha'            => now(),
        'estado'           => 'borrador',
        'total'            => 0,
    ]);
    EntradaDetalle::create([
        'entrada_id'       => $entrada->id,
        'producto_id'      => $producto->id,
        'unidad_medida_id' => $this->env->unidad->id,
        'cantidad'         => 5,
        'cantidad_base'    => 5,
        'precio_costo'     => 15,
        'subtotal'         => 75,
    ]);

    $this->delete(route('inventario.entradas.destroy', $entrada))
        ->assertRedirect();

    expect(Entrada::find($entrada->id))->toBeNull(); // efectivamente borrada

    $audit = Auditoria::where('accion', 'entrada.eliminada')->latest('id')->first();
    expect($audit)->not->toBeNull();
    expect($audit->contexto['almacen_id'])->toBe($this->env->almacen->id);
    expect($audit->contexto['total_items'])->toBe(1);
    expect($audit->contexto['detalles'][0]['producto_id'])->toBe($producto->id);
    expect($audit->contexto['detalles'][0]['producto_nombre'])->toBe('Vacuna canina');
    expect((float) $audit->contexto['detalles'][0]['cantidad_base'])->toBe(5.0);
});

it('intentar eliminar una entrada CONFIRMADA da 403 y NO audita borrado', function () {
    $producto = $this->env->crearProducto();
    $entrada = Entrada::create([
        'empresa_id' => $this->env->empresa->id,
        'almacen_id' => $this->env->almacen->id,
        'user_id'    => $this->env->admin->id,
        'tipo'       => 'compra',
        'fecha'      => now(),
        'estado'     => 'confirmado', // ← ya no se puede borrar
        'total'      => 0,
    ]);

    $this->withoutExceptionHandling();
    try {
        $this->delete(route('inventario.entradas.destroy', $entrada));
        $this->fail('Debió abortar con 403');
    } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
        expect($e->getStatusCode())->toBe(403);
    }

    expect(Entrada::find($entrada->id))->not->toBeNull(); // sigue ahí
    expect(Auditoria::where('accion', 'entrada.eliminada')->exists())->toBeFalse();
});

it('eliminar transferencia en borrador audita con snapshot origen/destino y productos', function () {
    // Transferencias necesitan modo_almacen=central_y_local + almacén central
    $this->env->empresa->update(['modo_almacen' => 'central_y_local']);
    $almacenCentral = Almacen::create([
        'empresa_id' => $this->env->empresa->id,
        'local_id'   => null,
        'nombre'     => 'Central',
        'tipo'       => 'central',
        'activo'     => true,
    ]);
    $producto = $this->env->crearProducto(['nombre' => 'Shampoo']);

    $transferencia = Transferencia::create([
        'empresa_id'         => $this->env->empresa->id,
        'almacen_origen_id'  => $almacenCentral->id,
        'almacen_destino_id' => $this->env->almacen->id,
        'user_id'            => $this->env->admin->id,
        'fecha'              => now(),
        'estado'             => 'borrador',
    ]);
    TransferenciaDetalle::create([
        'transferencia_id'       => $transferencia->id,
        'producto_id'            => $producto->id,
        'unidad_medida_id'       => $this->env->unidad->id,
        'cantidad_enviada'       => 3,
        'cantidad_base_enviada'  => 3,
    ]);

    $this->delete(route('inventario.transferencias.destroy', $transferencia))
        ->assertRedirect();

    expect(Transferencia::find($transferencia->id))->toBeNull();

    $audit = Auditoria::where('accion', 'transferencia.eliminada')->latest('id')->first();
    expect($audit)->not->toBeNull();
    expect($audit->contexto['almacen_origen_id'])->toBe($almacenCentral->id);
    expect($audit->contexto['almacen_destino_id'])->toBe($this->env->almacen->id);
    expect($audit->contexto['detalles'][0]['producto_nombre'])->toBe('Shampoo');
    expect((float) $audit->contexto['detalles'][0]['cantidad_base_enviada'])->toBe(3.0);
});
