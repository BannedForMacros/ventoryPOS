<?php

use App\Models\Almacen;
use App\Models\Caja;
use App\Models\Local;
use App\Models\Turno;
use Tests\Support\TestEnv;

/**
 * Eliminar un local borraba su historia: ventas, turnos, cajas y gastos
 * cuelgan de locales con ON DELETE CASCADE, y con un almacén tipo local el
 * borrado reventaba con 23514 (500). Un local con historia se desactiva; solo
 * uno vacío se elimina (junto con su caja y almacén sin movimientos).
 */
beforeEach(function () {
    $this->env = TestEnv::crear();
    $this->actingAs($this->env->admin);
});

test('un local con turnos no se borra: se desactiva y su historia queda intacta', function () {
    $turno = $this->env->abrirTurno();

    $this->delete(route('configuracion.locales.destroy', $this->env->local->id))
        ->assertRedirect()
        ->assertSessionHas('success');

    $local = Local::find($this->env->local->id);
    expect($local)->not->toBeNull()
        ->and($local->activo)->toBeFalse()
        ->and(Turno::find($turno->id))->not->toBeNull()
        ->and(Caja::find($this->env->caja->id))->not->toBeNull();
});

test('un local vacío se elimina junto con su caja y su almacén sin movimientos', function () {
    $vacio = Local::create([
        'empresa_id' => $this->env->empresa->id,
        'nombre'     => 'Local vacío',
        'activo'     => true,
    ]);
    $almacen = Almacen::create([
        'empresa_id' => $this->env->empresa->id,
        'local_id'   => $vacio->id,
        'nombre'     => 'Almacén vacío',
        'tipo'       => 'local',
        'activo'     => true,
    ]);
    $caja = Caja::create([
        'empresa_id' => $this->env->empresa->id,
        'local_id'   => $vacio->id,
        'nombre'     => 'Caja vacía',
        'activo'     => true,
    ]);

    $this->delete(route('configuracion.locales.destroy', $vacio->id))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(Local::find($vacio->id))->toBeNull()
        ->and(Almacen::find($almacen->id))->toBeNull()
        ->and(Caja::find($caja->id))->toBeNull();
});
