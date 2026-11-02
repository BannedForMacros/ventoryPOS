<?php

use App\Models\Cliente;
use App\Models\ClienteAnticipo;
use App\Models\Conductor;
use App\Models\MovimientoInventario;
use App\Models\Stock;
use App\Models\Vehiculo;
use App\Models\Venta;
use App\Models\Viaje;
use App\Models\ViajeParada;
use Tests\Support\TestEnv;

/**
 * Flota y viajes: un vehículo con su chofer lleva ventas a los clientes.
 * El viaje NO mueve stock por sí mismo: sale al marcar cada entrega, con la
 * misma entrega de Despachos.
 */
beforeEach(function () {
    $this->env = TestEnv::crear(['modo_cierre_caja' => 'rapido']);
    $this->turno = $this->env->abrirTurno();
    $this->actingAs($this->env->admin);
    $this->env->empresa->update(['usa_flota' => true, 'usa_entregas' => true, 'entrega_config' => ['ruta_obligatoria' => false, 'fecha_obligatoria' => false]]);

    $this->cliente = Cliente::create([
        'empresa_id' => $this->env->empresa->id, 'nombres' => 'Obra', 'apellidos' => 'Pomalca',
        'tipo_documento' => 'DNI', 'numero_documento' => '16789099', 'activo' => true,
        'direccion' => 'Av. Obra 123',
    ]);
    // Ladrillo de 2.8 kg; 100 en stock.
    $this->ladrillo = $this->env->crearProducto(['nombre' => 'King Kong', 'precio_venta' => 10, 'stock_inicial' => 100]);
    $this->ladrillo->update(['peso_kg' => 2.8]);

    $this->chofer = Conductor::create([
        'empresa_id' => $this->env->empresa->id, 'tipo' => 'chofer', 'nombres' => 'Jonathan', 'apellidos' => 'Saavedra',
        'numero_documento' => '44556677', 'licencia' => 'Q44556677', 'licencia_categoria' => 'A-IIIa',
    ]);
    $this->ayudante = Conductor::create([
        'empresa_id' => $this->env->empresa->id, 'tipo' => 'ayudante', 'nombres' => 'Lucas',
    ]);
    $this->camion = Vehiculo::create([
        'empresa_id' => $this->env->empresa->id, 'placa' => 'M9C-822', 'marca' => 'Hyundai', 'modelo' => 'EX10',
        'capacidad_kg' => 13000, 'conductor_id' => $this->chofer->id, 'estado' => 'disponible',
    ]);
});

function flStock($test): float
{
    return (float) Stock::where('almacen_id', $test->env->almacen->id)->where('producto_id', $test->ladrillo->id)->value('cantidad');
}

/** Venta de envío que queda por entregar (pedido). */
function flPedido($test, float $cantidad = 50, array $extra = []): ClienteAnticipo
{
    $test->post(route('ventas.store'), array_merge([
        'tipo_comprobante' => 'ticket',
        'cliente_id'       => $test->cliente->id,
        'tipo_entrega'     => 'envio',
        'cliente_direccion' => 'Av. Obra 123',
        'items' => [['producto_id' => $test->ladrillo->id, 'producto_unidad_id' => $test->ladrillo->unidadBase->id, 'cantidad' => $cantidad, 'precio_unitario' => 10]],
        'pagos' => [['metodo_pago_id' => $test->env->metodo('efectivo')->id, 'monto' => $cantidad * 10]],
    ], $extra))->assertSessionHasNoErrors();

    $venta = Venta::where('empresa_id', $test->env->empresa->id)->latest('id')->firstOrFail();

    return ClienteAnticipo::where('venta_id', $venta->id)->with('items')->firstOrFail();
}

function flViaje($test, ClienteAnticipo $pedido, float $cantidad, array $extra = []): \Illuminate\Testing\TestResponse
{
    return $test->post(route('viajes.store'), array_merge([
        'tipo' => 'reparto', 'fecha' => now()->toDateString(), 'vehiculo_id' => $test->camion->id,
        'conductor_id' => $test->chofer->id, 'ayudantes' => [$test->ayudante->id],
        'paradas' => [['cliente_anticipo_id' => $pedido->id, 'items' => [['cliente_anticipo_item_id' => $pedido->items[0]->id, 'cantidad' => $cantidad]]]],
    ], $extra));
}

it('con la flota apagada las pantallas no abren', function () {
    $this->env->empresa->update(['usa_flota' => false]);

    $this->get(route('viajes.index'))->assertForbidden();
    $this->get(route('flota.vehiculos.index'))->assertForbidden();
});

it('armar un viaje no mueve stock; entregar la parada sí', function () {
    $pedido = flPedido($this, 50);
    expect(flStock($this))->toBe(100.0);

    flViaje($this, $pedido, 50)->assertSessionHasNoErrors()->assertRedirect();
    $viaje = Viaje::where('empresa_id', $this->env->empresa->id)->firstOrFail();

    expect($viaje->numero)->toBe('VJ-0001')
        ->and($viaje->estado)->toBe('programado')
        ->and($viaje->ayudantes()->pluck('conductores.id')->all())->toBe([$this->ayudante->id])
        ->and(flStock($this))->toBe(100.0);

    // Peso congelado: 50 × 2.8 kg
    $parada = $viaje->paradas()->with('items')->first();
    expect((float) $parada->items[0]->peso_kg)->toBe(140.0);

    $this->post(route('viajes.paradas.entregar', $parada->id), [
        'fecha' => now()->toDateString(),
        'items' => [['id' => $parada->items[0]->id, 'cantidad' => 50]],
    ])->assertSessionHasNoErrors();

    $parada->refresh();
    expect($parada->estado)->toBe('entregado')
        ->and($parada->cliente_anticipo_aplicacion_id)->not->toBeNull()
        ->and($viaje->fresh()->estado)->toBe('en_ruta')               // entregar marca la salida
        ->and(flStock($this))->toBe(50.0)
        ->and((float) $pedido->items[0]->fresh()->cantidad_pendiente)->toBe(0.0)
        ->and($pedido->fresh()->estado)->toBe('aplicado');

    // El kardex registra la salida como entrega de la venta, no como "viaje".
    expect(MovimientoInventario::where('producto_id', $this->ladrillo->id)->where('tipo', 'entrega_pendiente')->sum('cantidad'))->toEqual(-50);
});

it('un pedido grande se reparte en varios viajes sin cargar de más', function () {
    $pedido = flPedido($this, 80);

    flViaje($this, $pedido, 45)->assertSessionHasNoErrors();
    // El camión sigue disponible para otro viaje programado.
    flViaje($this, $pedido, 35)->assertSessionHasNoErrors();
    // Ya no queda nada por cargar.
    flViaje($this, $pedido, 1)->assertSessionHasErrors('paradas.0.items.0');

    expect(Viaje::where('empresa_id', $this->env->empresa->id)->count())->toBe(2);
});

it('entrega parcial: lo que no recibió vuelve a quedar por cargar', function () {
    $pedido = flPedido($this, 50);
    flViaje($this, $pedido, 50)->assertSessionHasNoErrors();
    $parada = ViajeParada::with('items')->firstOrFail();

    $this->post(route('viajes.paradas.entregar', $parada->id), [
        'fecha' => now()->toDateString(),
        'items' => [['id' => $parada->items[0]->id, 'cantidad' => 30]],
    ])->assertSessionHasNoErrors();

    expect(flStock($this))->toBe(70.0)
        ->and((float) $pedido->items[0]->fresh()->cantidad_pendiente)->toBe(20.0)
        ->and($parada->fresh()->motivo)->toContain('parcial');

    // Las 20 que volvieron se pueden cargar en otro viaje.
    $this->get(route('viajes.pendientes'))->assertOk()->assertJsonPath('0.items.0.disponible', 20);
});

it('no se puede entregar más de lo cargado', function () {
    $pedido = flPedido($this, 50);
    flViaje($this, $pedido, 20)->assertSessionHasNoErrors();
    $parada = ViajeParada::with('items')->firstOrFail();

    $this->post(route('viajes.paradas.entregar', $parada->id), [
        'fecha' => now()->toDateString(),
        'items' => [['id' => $parada->items[0]->id, 'cantidad' => 25]],
    ])->assertSessionHasErrors('items.0.cantidad');

    expect(flStock($this))->toBe(100.0);
});

it('no entregada y cerrar el viaje liberan lo cargado', function () {
    $a = flPedido($this, 10);
    $b = flPedido($this, 15);

    $this->post(route('viajes.store'), [
        'tipo' => 'reparto', 'fecha' => now()->toDateString(), 'vehiculo_id' => $this->camion->id, 'conductor_id' => $this->chofer->id,
        'paradas' => [
            ['cliente_anticipo_id' => $a->id, 'items' => [['cliente_anticipo_item_id' => $a->items[0]->id, 'cantidad' => 10]]],
            ['cliente_anticipo_id' => $b->id, 'items' => [['cliente_anticipo_item_id' => $b->items[0]->id, 'cantidad' => 15]]],
        ],
    ])->assertSessionHasNoErrors();
    $viaje = Viaje::firstOrFail();
    [$pa, $pb] = $viaje->paradas()->get()->all();

    $this->post(route('viajes.iniciar', $viaje->id))->assertSessionHasNoErrors();
    $this->post(route('viajes.paradas.no-entregada', $pa->id), ['motivo' => 'Obra cerrada'])->assertSessionHasNoErrors();
    $this->post(route('viajes.cerrar', $viaje->id))->assertSessionHasNoErrors();

    expect($viaje->fresh()->estado)->toBe('cerrado')
        ->and($pa->fresh()->estado)->toBe('no_entregado')
        ->and($pb->fresh()->estado)->toBe('no_entregado')
        ->and($pb->fresh()->motivo)->toBe('Volvió sin entregar')
        ->and(flStock($this))->toBe(100.0);

    // Los dos pedidos vuelven a estar disponibles completos.
    expect(collect($this->get(route('viajes.pendientes'))->json())->sum(fn ($p) => $p['items'][0]['disponible']))->toEqual(25);
});

it('un viaje con entregas no se anula; sin entregas sí', function () {
    $pedido = flPedido($this, 50);
    flViaje($this, $pedido, 10)->assertSessionHasNoErrors();
    flViaje($this, $pedido, 10)->assertSessionHasNoErrors();
    [$v1, $v2] = Viaje::orderBy('id')->get()->all();

    $p1 = $v1->paradas()->with('items')->first();
    $this->post(route('viajes.paradas.entregar', $p1->id), ['fecha' => now()->toDateString(), 'items' => [['id' => $p1->items[0]->id, 'cantidad' => 10]]])
        ->assertSessionHasNoErrors();

    $this->post(route('viajes.anular', $v1->id), ['motivo' => 'error'])->assertSessionHasErrors('viaje');
    $this->post(route('viajes.anular', $v2->id), ['motivo' => 'No sale hoy'])->assertSessionHasNoErrors();

    expect($v2->fresh()->estado)->toBe('anulado')
        ->and(flStock($this))->toBe(90.0);
});

it('para salir hace falta chofer y que el camión no esté en otra ruta', function () {
    $pedido = flPedido($this, 50);
    flViaje($this, $pedido, 10, ['conductor_id' => null])->assertSessionHasNoErrors();
    flViaje($this, $pedido, 10)->assertSessionHasNoErrors();
    [$sinChofer, $otro] = Viaje::orderBy('id')->get()->all();

    $this->post(route('viajes.iniciar', $sinChofer->id))->assertSessionHasErrors('conductor_id');

    $this->post(route('viajes.iniciar', $otro->id))->assertSessionHasNoErrors();
    $sinChofer->update(['conductor_id' => $this->chofer->id]);
    $this->post(route('viajes.iniciar', $sinChofer->id))->assertSessionHasErrors('vehiculo_id');
});

it('un camión en el taller no recibe viajes nuevos', function () {
    $this->camion->update(['estado' => 'taller']);
    $pedido = flPedido($this, 10);

    flViaje($this, $pedido, 10)->assertSessionHasErrors('vehiculo_id');
});

it('desde el POS: un envío entregado con placa entra al viaje del día del camión', function () {
    $venta = fn () => $this->post(route('ventas.store'), [
        'tipo_comprobante' => 'ticket', 'cliente_id' => $this->cliente->id,
        'tipo_entrega' => 'envio', 'envio_entregado' => true, 'cliente_direccion' => 'Av. Obra 123',
        'vehiculo_id' => $this->camion->id,
        'items' => [['producto_id' => $this->ladrillo->id, 'producto_unidad_id' => $this->ladrillo->unidadBase->id, 'cantidad' => 10, 'precio_unitario' => 10]],
        'pagos' => [['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => 100]],
    ])->assertSessionHasNoErrors();

    $venta();
    $venta();

    $viajes = Viaje::where('empresa_id', $this->env->empresa->id)->get();
    expect($viajes)->toHaveCount(1)
        ->and($viajes[0]->vehiculo_id)->toBe($this->camion->id)
        ->and($viajes[0]->conductor_id)->toBe($this->chofer->id)       // el chofer asignado al camión
        ->and($viajes[0]->paradas()->count())->toBe(2)
        ->and($viajes[0]->paradas()->pluck('modo')->unique()->all())->toBe(['directa'])
        ->and($viajes[0]->paradas()->pluck('estado')->unique()->all())->toBe(['entregado'])
        ->and(flStock($this))->toBe(80.0);                              // salió al vender, una sola vez
});

it('desde el POS: un envío por entregar con placa va al viaje como pedido', function () {
    $pedido = flPedido($this, 30, ['vehiculo_id' => $this->camion->id]);

    $parada = ViajeParada::where('cliente_anticipo_id', $pedido->id)->with('items')->firstOrFail();
    expect($parada->modo)->toBe('pendiente')
        ->and($parada->estado)->toBe('por_entregar')
        ->and((float) $parada->items[0]->cantidad)->toBe(30.0)
        ->and(flStock($this))->toBe(100.0);
});

it('sin flota el POS ignora la placa', function () {
    $this->env->empresa->update(['usa_flota' => false]);
    flPedido($this, 5, ['vehiculo_id' => $this->camion->id]);

    expect(Viaje::count())->toBe(0);
});

it('no se usa el vehículo ni el pedido de otra empresa', function () {
    $otra = TestEnv::crear();
    $ajeno = Vehiculo::create(['empresa_id' => $otra->empresa->id, 'placa' => 'XYZ-999', 'estado' => 'disponible']);
    $pedido = flPedido($this, 10);

    flViaje($this, $pedido, 5, ['vehiculo_id' => $ajeno->id])->assertSessionHasErrors('vehiculo_id');
    $this->post(route('ventas.store'), [
        'tipo_comprobante' => 'ticket', 'cliente_id' => $this->cliente->id, 'tipo_entrega' => 'envio', 'envio_entregado' => true,
        'cliente_direccion' => 'x', 'vehiculo_id' => $ajeno->id,
        'items' => [['producto_id' => $this->ladrillo->id, 'producto_unidad_id' => $this->ladrillo->unidadBase->id, 'cantidad' => 1, 'precio_unitario' => 10]],
        'pagos' => [['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => 10]],
    ])->assertSessionHasErrors('vehiculo_id');

    // Y la otra empresa no ve el viaje de esta.
    flViaje($this, $pedido, 5)->assertSessionHasNoErrors();
    $viaje = Viaje::where('empresa_id', $this->env->empresa->id)->firstOrFail();
    $otra->empresa->update(['usa_flota' => true]);
    $this->actingAs($otra->admin)->get(route('viajes.show', $viaje->id))->assertNotFound();
});

it('vehículos y conductores: placa normalizada, únicos y con licencia para choferes', function () {
    $this->post(route('flota.vehiculos.store'), ['placa' => ' m8b 893 ', 'estado' => 'disponible'])->assertSessionHasNoErrors();
    expect(Vehiculo::where('placa', 'M8B893')->exists())->toBeTrue();
    $this->post(route('flota.vehiculos.store'), ['placa' => 'M9C-822', 'estado' => 'disponible'])->assertSessionHasErrors('placa');

    $this->post(route('flota.conductores.store'), ['tipo' => 'chofer', 'nombres' => 'Jordy', 'tipo_documento' => 'DNI'])
        ->assertSessionHasErrors(['numero_documento', 'licencia']);
    $this->post(route('flota.conductores.store'), ['tipo' => 'ayudante', 'nombres' => 'Genderson', 'tipo_documento' => 'DNI'])
        ->assertSessionHasNoErrors();

    // Con viajes, quitar un vehículo lo desactiva en vez de borrarlo.
    $pedido = flPedido($this, 5);
    flViaje($this, $pedido, 5)->assertSessionHasNoErrors();
    $this->delete(route('flota.vehiculos.destroy', $this->camion->id))->assertSessionHas('error');
});

it('las pantallas de flota abren', function () {
    $this->withoutVite();
    $pedido = flPedido($this, 5);
    flViaje($this, $pedido, 5)->assertSessionHasNoErrors();
    $viaje = Viaje::firstOrFail();

    $this->get(route('viajes.index'))->assertOk();
    $this->get(route('viajes.create', ['anticipo' => $pedido->id]))->assertOk();
    $this->get(route('viajes.show', $viaje->id))->assertOk();
    $this->get(route('viajes.edit', $viaje->id))->assertOk();
    $this->get(route('flota.vehiculos.index'))->assertOk();
    $this->get(route('flota.conductores.index'))->assertOk();
    $this->get(route('despachos.index'))->assertOk()->assertInertia(fn ($p) => $p->where('usaFlota', true)->has("enViaje.{$pedido->id}"));
    $this->get(route('viajes.ventas'))->assertOk();
});
