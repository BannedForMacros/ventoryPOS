<?php

use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\TestEnv;

/**
 * El token de la impresora de la caja viajaba al navegador en cada página
 * dentro de turno_activo.caja. El ticket lo recibe ya armado por el servidor
 * (TicketPrintService), así que el share no lo necesita.
 */
test('turno_activo.caja no expone el token de la impresora', function () {
    $env = TestEnv::crear();
    $env->caja->update(['token_impresora' => 'secreto-de-la-caja']);
    $env->abrirTurno();

    $this->actingAs($env->admin)
        ->get('/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->where('turno_activo.caja.id', $env->caja->id)
            ->missing('turno_activo.caja.token_impresora')
            ->etc());
});
