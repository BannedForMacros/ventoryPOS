<?php

use App\Services\VentaService;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\TestEnv;

/**
 * Pantallas de turnos: cuánto entró por cada medio de pago y cuánto efectivo
 * debería haber en el cajón, para cada turno abierto.
 */
beforeEach(function () {
    $this->env = TestEnv::crear();
    $this->turno = $this->env->abrirTurno(apertura: 50.0);
    $this->actingAs($this->env->admin);

    $producto = $this->env->crearProducto(['precio_venta' => 30, 'precio_costo' => 10, 'stock_inicial' => 20]);
    // Una venta de S/ 30 pagada S/ 10 en efectivo y S/ 20 por Yape.
    app(VentaService::class)->crear([
        'tipo_comprobante' => 'ticket',
        'items' => [[
            'producto_id'        => $producto->id,
            'producto_unidad_id' => $producto->unidadBase->id,
            'cantidad'           => 1,
            'precio_unitario'    => 30,
        ]],
        'pagos' => [
            ['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => 10],
            ['metodo_pago_id' => $this->env->metodo('yape')->id, 'monto' => 20],
        ],
    ], $this->env->admin, $this->turno);
});

it('desglosa lo cobrado por medio de pago, con el efectivo primero', function () {
    $cobros = $this->turno->fresh()->cobrosPorMetodo();

    expect(collect($cobros)->pluck('nombre')->all())->toBe(['Efectivo', 'Yape']);
    expect($cobros[0]['es_efectivo'])->toBeTrue();
    expect($cobros[0]['total'])->toBe(10.0);
    expect($cobros[1]['total'])->toBe(20.0);
});

it('la pantalla de turnos lista cada turno abierto con su esperado y sus cobros', function () {
    $this->get(route('turnos.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('turnosAbiertos', 1)
            ->where('turnosAbiertos.0.id', $this->turno->id)
            ->where('turnosAbiertos.0.es_mio', true)
            ->where('turnosAbiertos.0.ventas_count', 1)
            ->where('turnosAbiertos.0.efectivo_esperado', 60) // 50 de apertura + 10 en efectivo
            ->has('turnosAbiertos.0.cobros', 2));
});

it('el cierre recibe lo cobrado por cada medio', function () {
    $this->get(route('turnos.cerrar.page', $this->turno->id))
        ->assertInertia(fn (Assert $page) => $page
            ->has('cobrosPorMetodo', 2)
            ->where('cobrosPorMetodo.1.nombre', 'Yape')
            ->where('cobrosPorMetodo.1.total', 20));
});
