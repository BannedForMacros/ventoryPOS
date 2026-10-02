<?php

use Illuminate\Support\Facades\Http;
use Tests\Support\TestEnv;

/**
 * Configuración → Empresas: "Buscar" por RUC trae de SUNAT la razón social y
 * la dirección fiscal completa (lo que sale en boletas y facturas).
 */
beforeEach(function () {
    config(['services.decolecta.token' => 'token-prueba']);
    $this->env = TestEnv::crear();
    $this->actingAs($this->env->admin);
    Http::fake(['*/v1/sunat/ruc*' => Http::response([
        'razon_social' => 'MACSOFT E.I.R.L.', 'estado' => 'ACTIVO', 'condicion' => 'HABIDO',
        'direccion' => 'CAL. JUAN BUENDIA NRO 341 URB. LA PRIMAVERA ',
        'distrito' => 'CHICLAYO', 'provincia' => 'CHICLAYO', 'departamento' => 'LAMBAYEQUE',
    ])]);
});

it('trae razón social, dirección fiscal completa y estado del RUC', function () {
    $this->postJson(route('decolecta.ruc'), ['ruc' => '20614911051'])
        ->assertOk()
        ->assertJson([
            'razon_social'       => 'MACSOFT E.I.R.L.',
            'direccion'          => 'CAL. JUAN BUENDIA NRO 341 URB. LA PRIMAVERA',
            'direccion_completa' => 'CAL. JUAN BUENDIA NRO 341 URB. LA PRIMAVERA - CHICLAYO - CHICLAYO - LAMBAYEQUE',
            'estado'             => 'ACTIVO',
            'condicion'          => 'HABIDO',
        ]);
});
