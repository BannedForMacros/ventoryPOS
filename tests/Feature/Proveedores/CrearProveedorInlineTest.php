<?php

use App\Models\Proveedor;
use Tests\Support\TestEnv;

/**
 * Alta de proveedor inline (desde la entrada): store responde JSON con el modelo
 * creado. Sirve para persona natural (DNI) y jurídica (RUC).
 */

beforeEach(function () {
    $this->env = TestEnv::crear();
    $this->actingAs($this->env->admin);
});

it('crea un proveedor jurídico (RUC) y devuelve el modelo en JSON', function () {
    $resp = $this->postJson(route('proveedores.store'), [
        'tipo_documento'   => 'RUC',
        'numero_documento' => '20123456789',
        'razon_social'     => 'Ferretería y Aceros SAC',
        'nombre_comercial' => 'FerreMax',
        'contacto'         => 'Karen',
        'telefono'         => '987654321',
        'activo'           => true,
    ]);

    $resp->assertOk()
        ->assertJsonPath('razon_social', 'Ferretería y Aceros SAC')
        ->assertJsonPath('tipo_documento', 'RUC');

    expect(Proveedor::where('empresa_id', $this->env->empresa->id)
        ->where('numero_documento', '20123456789')->exists())->toBeTrue();
});

it('crea un proveedor persona natural (DNI) y devuelve el modelo en JSON', function () {
    $resp = $this->postJson(route('proveedores.store'), [
        'tipo_documento'   => 'DNI',
        'numero_documento' => '45678912',
        'razon_social'     => 'Juan Pérez Quispe', // en DNI el nombre va en razon_social
        'activo'           => true,
    ]);

    $resp->assertOk()->assertJsonPath('razon_social', 'Juan Pérez Quispe');

    $prov = Proveedor::where('empresa_id', $this->env->empresa->id)
        ->where('numero_documento', '45678912')->first();
    expect($prov)->not->toBeNull();
    expect($prov->nombre_comercial)->toBeNull(); // sin nombre comercial: no rompe nada
});

it('rechaza documento duplicado en la misma empresa', function () {
    Proveedor::create([
        'empresa_id' => $this->env->empresa->id,
        'tipo_documento' => 'RUC', 'numero_documento' => '20999999999',
        'razon_social' => 'Existente SAC', 'activo' => true,
    ]);

    $this->postJson(route('proveedores.store'), [
        'tipo_documento' => 'RUC', 'numero_documento' => '20999999999',
        'razon_social' => 'Otra SAC', 'activo' => true,
    ])->assertStatus(422)->assertJsonValidationErrors('numero_documento');
});
