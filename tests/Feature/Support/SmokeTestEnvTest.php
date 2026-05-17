<?php

use App\Models\Stock;
use App\Models\Venta;
use Tests\Support\TestEnv;

it('boots a complete TestEnv with empresa, local, caja, admin and métodos', function () {
    $env = TestEnv::crear();

    expect($env->empresa->id)->toBeGreaterThan(0);
    expect($env->local->es_principal)->toBeTrue();
    expect($env->caja->local_id)->toBe($env->local->id);
    expect($env->almacen->tipo)->toBe('local');
    expect($env->admin->rol->es_admin)->toBeTrue();
    expect($env->clienteGeneral->numero_documento)->toBe('99999999');
    expect($env->metodo('efectivo')->admite_vuelto)->toBeTrue();
    expect($env->metodo('tarjeta_debito')->admite_vuelto)->toBeFalse();
    expect($env->motivo('vencido')->afecta_restock_default)->toBe('obliga_merma');
});

it('crearProducto inserta producto + unidad base + stock inicial', function () {
    $env = TestEnv::crear();
    $producto = $env->crearProducto(['nombre' => 'Cocacola', 'precio_venta' => 5, 'stock_inicial' => 50]);

    expect($producto->unidadBase)->not->toBeNull();
    $stock = Stock::where('almacen_id', $env->almacen->id)->where('producto_id', $producto->id)->first();
    expect((float) $stock->cantidad)->toBe(50.0);
});

it('abrirTurno deja un turno en estado abierto', function () {
    $env = TestEnv::crear();
    $turno = $env->abrirTurno();
    expect($turno->estado)->toBe('abierto');
    expect($turno->user_id)->toBe($env->admin->id);
});
