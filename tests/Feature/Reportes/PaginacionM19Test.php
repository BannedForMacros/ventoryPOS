<?php

use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\TestEnv;

/**
 * M19 — los 4 controllers ahora retornan estructura paginada (`->paginate(25)`).
 * El FE consume {data, current_page, last_page, per_page, total}.
 *
 * Usamos `assertInertia` (Inertia\Testing) en lugar de inspeccionar
 * $response->original->getData() porque en entorno test sin Vite manifest
 * el HTML render falla con 500. La rama Inertia evita el render del view raíz
 * y trabaja directamente con el page object.
 */
beforeEach(function () {
    $this->env = TestEnv::crear();
    $this->actingAs($this->env->admin);
});

it('DevolucionController::index devuelve estructura paginada', function () {
    $this->get(route('devoluciones.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Devoluciones/Index')
            ->has('devoluciones.data')
            ->has('devoluciones.current_page')
            ->has('devoluciones.last_page')
            ->where('devoluciones.per_page', 25)
        );
});

it('EntradaController::index devuelve paginado', function () {
    $this->get(route('inventario.entradas.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Inventario/Entradas/Index')
            ->has('entradas.data')
            ->where('entradas.per_page', 25)
        );
});

it('SalidaController::index devuelve paginado', function () {
    $this->get(route('inventario.salidas.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Inventario/Salidas/Index')
            ->has('salidas.data')
            ->where('salidas.per_page', 25)
        );
});

it('TransferenciaController::index devuelve paginado', function () {
    $this->env->empresa->update(['modo_almacen' => 'central_y_local']);

    $this->get(route('inventario.transferencias.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Inventario/Transferencias/Index')
            ->has('transferencias.data')
            ->where('transferencias.per_page', 25)
        );
});
