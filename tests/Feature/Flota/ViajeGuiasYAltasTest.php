<?php

use App\Models\Cliente;
use App\Models\ClienteAnticipo;
use App\Models\Conductor;
use App\Models\Guia;
use App\Models\Modulo;
use App\Models\Vehiculo;
use App\Models\Venta;
use App\Models\Viaje;
use App\Services\ViajeService;
use Illuminate\Support\Facades\Queue;
use Tests\Support\TestEnv;

/**
 * Desde el viaje, sin ir de pantalla en pantalla: camión y chofer se dan de
 * alta ahí mismo, y las guías de las paradas salen una tras otra.
 */
beforeEach(function () {
    Queue::fake();
    $this->env = TestEnv::crear(['modo_cierre_caja' => 'rapido']);
    $this->turno = $this->env->abrirTurno();
    $this->actingAs($this->env->admin);
    $this->env->empresa->update(['usa_flota' => true, 'usa_entregas' => true, 'entrega_config' => ['ruta_obligatoria' => false, 'fecha_obligatoria' => false]]);
    Modulo::where('slug', 'guias')->update(['activo' => true]);

    $this->cliente = Cliente::create([
        'empresa_id' => $this->env->empresa->id, 'nombres' => 'Obra', 'apellidos' => 'Pomalca',
        'tipo_documento' => 'DNI', 'numero_documento' => '16789099', 'activo' => true, 'direccion' => 'Av. Obra 123',
    ]);
    $this->ladrillo = $this->env->crearProducto(['nombre' => 'King Kong', 'precio_venta' => 10, 'stock_inicial' => 100]);
    $this->chofer = Conductor::create([
        'empresa_id' => $this->env->empresa->id, 'tipo' => 'chofer', 'nombres' => 'Jonathan',
        'numero_documento' => '44556677', 'licencia' => 'Q44556677',
    ]);
    $this->camion = Vehiculo::create([
        'empresa_id' => $this->env->empresa->id, 'placa' => 'M9C-822', 'capacidad_kg' => 13000,
        'conductor_id' => $this->chofer->id, 'estado' => 'disponible',
    ]);
});

function vgPedido($test, float $cantidad = 10): ClienteAnticipo
{
    $test->post(route('ventas.store'), [
        'tipo_comprobante'  => 'ticket',
        'cliente_id'        => $test->cliente->id,
        'tipo_entrega'      => 'envio',
        'cliente_direccion' => 'Av. Obra 123',
        'items' => [['producto_id' => $test->ladrillo->id, 'producto_unidad_id' => $test->ladrillo->unidadBase->id, 'cantidad' => $cantidad, 'precio_unitario' => 10]],
        'pagos' => [['metodo_pago_id' => $test->env->metodo('efectivo')->id, 'monto' => $cantidad * 10]],
    ])->assertSessionHasNoErrors();

    $venta = Venta::where('empresa_id', $test->env->empresa->id)->latest('id')->firstOrFail();

    return ClienteAnticipo::where('venta_id', $venta->id)->with('items')->firstOrFail();
}

function vgViaje($test, array $pedidos, array $extra = []): \Illuminate\Testing\TestResponse
{
    return $test->post(route('viajes.store'), array_merge([
        'tipo' => 'reparto', 'fecha' => now()->toDateString(), 'vehiculo_id' => $test->camion->id,
        'conductor_id' => $test->chofer->id,
        'paradas' => array_map(fn (ClienteAnticipo $p) => [
            'cliente_anticipo_id' => $p->id,
            'items' => [['cliente_anticipo_item_id' => $p->items[0]->id, 'cantidad' => (float) $p->items[0]->cantidad_pendiente]],
        ], $pedidos),
    ], $extra));
}

function vgGuia($test, int $paradaId, bool $seguir): \Illuminate\Testing\TestResponse
{
    return $test->post(route('guias.store'), [
        'motivo' => 'VENTA', 'modalidad' => 'PRIVADO', 'fecha_inicio' => now()->toDateString(), 'peso_total' => 28,
        'partida' => ['direccion' => 'Av. Uno 1', 'ubigeo' => '140101'],
        'llegada' => ['direccion' => 'Av. Obra 123', 'ubigeo' => '140101'],
        'vehiculo'  => ['placa' => 'M9C822', 'tarjeta_circulacion' => ''],
        'conductor' => ['tipo_documento' => 'DNI', 'numero_documento' => '44556677', 'nombres' => 'Jonathan', 'apellidos' => 'X', 'licencia' => 'Q44556677'],
        'items'     => [['descripcion' => 'King Kong', 'cantidad' => 10, 'unidad' => 'NIU']],
        'cliente_id' => $test->cliente->id,
        'viaje_parada_id' => $paradaId,
        'seguir' => $seguir,
    ]);
}

it('«Programar y hacer guías» abre de frente la guía de la primera parada', function () {
    $a = vgPedido($this);
    $b = vgPedido($this);

    $res = vgViaje($this, [$a, $b], ['hacer_guias' => true])->assertSessionHasNoErrors();
    $viaje = Viaje::where('empresa_id', $this->env->empresa->id)->firstOrFail();
    $primera = $viaje->paradas()->orderBy('orden')->orderBy('id')->first();

    $res->assertRedirect(route('guias.create', ['parada' => $primera->id]));
});

it('sin pedir guías, programar vuelve al viaje como siempre', function () {
    $res = vgViaje($this, [vgPedido($this)], ['hacer_guias' => false])->assertSessionHasNoErrors();
    $viaje = Viaje::where('empresa_id', $this->env->empresa->id)->firstOrFail();

    $res->assertRedirect(route('viajes.show', $viaje));
});

it('con el módulo de guías apagado no se desvía a la guía', function () {
    Modulo::where('slug', 'guias')->update(['activo' => false]);
    $res = vgViaje($this, [vgPedido($this)], ['hacer_guias' => true])->assertSessionHasNoErrors();
    $viaje = Viaje::where('empresa_id', $this->env->empresa->id)->firstOrFail();

    $res->assertRedirect(route('viajes.show', $viaje));
});

it('las guías se encadenan parada tras parada y al final vuelven al viaje', function () {
    vgViaje($this, [vgPedido($this), vgPedido($this)])->assertSessionHasNoErrors();
    $viaje = Viaje::where('empresa_id', $this->env->empresa->id)->firstOrFail();
    [$p1, $p2] = $viaje->paradas()->orderBy('orden')->orderBy('id')->get()->all();

    expect(app(ViajeService::class)->paradasSinGuia($viaje)->pluck('id')->all())->toBe([$p1->id, $p2->id]);

    vgGuia($this, $p1->id, true)->assertSessionHasNoErrors()
        ->assertRedirect(route('guias.create', ['parada' => $p2->id]));

    expect(Guia::where('viaje_parada_id', $p1->id)->exists())->toBeTrue()
        ->and(app(ViajeService::class)->paradasSinGuia($viaje)->pluck('id')->all())->toBe([$p2->id]);

    vgGuia($this, $p2->id, true)->assertSessionHasNoErrors()
        ->assertRedirect(route('viajes.show', $viaje));

    expect(app(ViajeService::class)->paradasSinGuia($viaje))->toHaveCount(0);
});

it('una guía rechazada vuelve a quedar por hacer', function () {
    vgViaje($this, [vgPedido($this)])->assertSessionHasNoErrors();
    $viaje = Viaje::where('empresa_id', $this->env->empresa->id)->firstOrFail();
    $parada = $viaje->paradas()->first();

    vgGuia($this, $parada->id, false)->assertRedirect(route('viajes.show', $viaje));
    expect(app(ViajeService::class)->paradasSinGuia($viaje))->toHaveCount(0);

    Guia::where('viaje_parada_id', $parada->id)->update(['estado' => Guia::ESTADO_ERROR_MAPEO, 'puede_trasladar' => false]);
    expect(app(ViajeService::class)->paradasSinGuia($viaje)->pluck('id')->all())->toBe([$parada->id]);
});

it('la guía desde la parada dice cuál va y cuántas faltan', function () {
    vgViaje($this, [vgPedido($this), vgPedido($this), vgPedido($this)])->assertSessionHasNoErrors();
    $viaje = Viaje::where('empresa_id', $this->env->empresa->id)->firstOrFail();
    $segunda = $viaje->paradas()->orderBy('orden')->orderBy('id')->skip(1)->first();

    $this->get(route('guias.create', ['parada' => $segunda->id]))->assertOk()
        ->assertInertia(fn ($page) => $page->component('Guias/Create')
            ->where('traslado.viaje_id', $viaje->id)
            ->where('traslado.posicion', 2)
            ->where('traslado.total', 3)
            ->where('traslado.restantes', 2));
});

it('el formulario del viaje permite dar de alta camión y chofer ahí mismo', function () {
    $this->get(route('viajes.create'))->assertOk()
        ->assertInertia(fn ($page) => $page->component('Viajes/Form')
            ->where('puede.vehiculo', true)
            ->where('puede.conductor', true)
            ->where('puede.guia', true));

    // El alta vuelve al mismo viaje con la lista nueva (no saca al usuario).
    $this->from(route('viajes.create'))->post(route('flota.vehiculos.store'), ['placa' => 'abc 123', 'estado' => 'disponible', 'capacidad_kg' => 5000])
        ->assertSessionHasNoErrors()->assertRedirect(route('viajes.create'));
    $this->from(route('viajes.create'))->post(route('flota.conductores.store'), ['tipo' => 'ayudante', 'nombres' => 'Lucas', 'tipo_documento' => 'DNI'])
        ->assertSessionHasNoErrors()->assertRedirect(route('viajes.create'));

    $this->get(route('viajes.create'))->assertInertia(fn ($page) => $page
        ->where('vehiculos', fn ($v) => collect($v)->contains('placa', 'ABC-123') || collect($v)->contains('placa', 'ABC123'))
        ->where('conductores', fn ($c) => collect($c)->contains('nombre', 'Lucas')));
});

it('la ficha del viaje lista las paradas que aún necesitan guía', function () {
    vgViaje($this, [vgPedido($this), vgPedido($this)])->assertSessionHasNoErrors();
    $viaje = Viaje::where('empresa_id', $this->env->empresa->id)->firstOrFail();
    $ids = $viaje->paradas()->orderBy('orden')->orderBy('id')->pluck('id')->all();

    $this->get(route('viajes.show', $viaje))->assertOk()
        ->assertInertia(fn ($page) => $page->where('sin_guia', $ids));
});
