<?php

use App\Models\Cliente;
use App\Models\ClienteAnticipo;
use App\Models\RutaEntrega;
use App\Models\Stock;
use App\Models\Venta;
use App\Services\TicketPrintService;
use Tests\Support\TestEnv;

/**
 * Entregas: recojo en tienda o envío, con ruta y fecha programada. Función
 * opcional por empresa: apagada, la venta, el stock y el ticket quedan igual.
 */
beforeEach(function () {
    $this->env = TestEnv::crear(['modo_cierre_caja' => 'rapido']);
    $this->turno = $this->env->abrirTurno();
    $this->actingAs($this->env->admin);

    $this->cliente = Cliente::create([
        'empresa_id' => $this->env->empresa->id, 'nombres' => 'Dagoberto', 'apellidos' => 'Rodríguez',
        'tipo_documento' => 'DNI', 'numero_documento' => '16789012', 'activo' => true,
    ]);
    $this->producto = $this->env->crearProducto(['nombre' => 'Cemento Sol', 'precio_venta' => 20, 'stock_inicial' => 50]);
});

function enActivar($test, array $config = []): void
{
    $test->env->empresa->update(['usa_entregas' => true, 'entrega_config' => $config]);
}

function enRuta($test, string $nombre = 'Ruta 3', ?string $zona = 'Pomalca'): RutaEntrega
{
    return RutaEntrega::create(['empresa_id' => $test->env->empresa->id, 'nombre' => $nombre, 'zona' => $zona, 'orden' => 1]);
}

function enVenta($test, array $extra = [], float $pago = 200): array
{
    return array_merge([
        'tipo_comprobante' => 'ticket',
        'cliente_id'       => $test->cliente->id,
        'items' => [['producto_id' => $test->producto->id, 'producto_unidad_id' => $test->producto->unidadBase->id, 'cantidad' => 10, 'precio_unitario' => 20]],
        'pagos' => $pago > 0 ? [['metodo_pago_id' => $test->env->metodo('efectivo')->id, 'monto' => $pago]] : [],
    ], $extra);
}

function enStock($test): float
{
    return (float) Stock::where('almacen_id', $test->env->almacen->id)->where('producto_id', $test->producto->id)->value('cantidad');
}

function enUltimaVenta($test): Venta
{
    return Venta::where('empresa_id', $test->env->empresa->id)->latest('id')->firstOrFail();
}

it('con la función apagada la venta ignora los datos de entrega', function () {
    $this->post(route('ventas.store'), enVenta($this, [
        'tipo_entrega' => 'envio', 'entrega_programada' => now()->addDay()->format('Y-m-d\TH:i'),
    ]))->assertSessionHasNoErrors();

    $venta = enUltimaVenta($this);
    expect($venta->tipo_entrega)->toBeNull()
        ->and($venta->entrega_programada)->toBeNull()
        ->and(enStock($this))->toBe(40.0)
        ->and(ClienteAnticipo::where('venta_id', $venta->id)->exists())->toBeFalse();
});

it('toda venta nueva es recojo en tienda y sale del stock al vender', function () {
    enActivar($this);

    $this->post(route('ventas.store'), enVenta($this))->assertSessionHasNoErrors();

    expect(enUltimaVenta($this)->tipo_entrega)->toBe('recojo')
        ->and(enStock($this))->toBe(40.0);
});

it('un envío exige cliente, dirección, ruta y fecha', function () {
    enActivar($this);
    enRuta($this);

    $this->post(route('ventas.store'), enVenta($this, ['tipo_entrega' => 'envio', 'cliente_id' => $this->env->clienteGeneral->id]))
        ->assertSessionHasErrors(['cliente_id', 'cliente_direccion', 'ruta_entrega_id', 'entrega_programada']);

    // La dirección puede venir de la ficha del cliente
    $this->cliente->update(['direccion' => 'Calle Los Cedros 245']);
    $this->post(route('ventas.store'), enVenta($this, ['tipo_entrega' => 'envio']))
        ->assertSessionHasErrors(['ruta_entrega_id', 'entrega_programada'])
        ->assertSessionDoesntHaveErrors(['cliente_id', 'cliente_direccion']);

    expect(Venta::where('empresa_id', $this->env->empresa->id)->count())->toBe(0);
});

it('lo que se exige en un envío se puede relajar', function () {
    enActivar($this, ['ruta_obligatoria' => false, 'fecha_obligatoria' => false]);
    enRuta($this);

    $this->post(route('ventas.store'), enVenta($this, ['tipo_entrega' => 'envio', 'cliente_direccion' => 'Obra Pomalca']))
        ->assertSessionHasNoErrors();

    expect(enUltimaVenta($this)->only(['tipo_entrega', 'ruta_entrega_id', 'entrega_programada']))
        ->toBe(['tipo_entrega' => 'envio', 'ruta_entrega_id' => null, 'entrega_programada' => null]);
});

it('en un envío la mercadería queda por entregar y sale del stock al despacharla', function () {
    enActivar($this);
    $ruta = enRuta($this);
    $cuando = now()->addDay()->setTime(9, 0);

    $this->post(route('ventas.store'), enVenta($this, [
        'tipo_entrega' => 'envio', 'ruta_entrega_id' => $ruta->id, 'entrega_programada' => $cuando->format('Y-m-d\TH:i'),
        'cliente_direccion' => 'Calle Los Cedros 245', 'cliente_telefono' => '979555012',
    ]))->assertSessionHasNoErrors();

    $venta = enUltimaVenta($this);
    $pedido = ClienteAnticipo::where('venta_id', $venta->id)->with('items')->firstOrFail();

    expect($venta->tipo_entrega)->toBe('envio')
        ->and($venta->ruta_entrega_id)->toBe($ruta->id)
        ->and($venta->entrega_programada->format('Y-m-d H:i'))->toBe($cuando->format('Y-m-d H:i'))
        ->and(enStock($this))->toBe(50.0)                                     // nada salió todavía
        ->and((float) $pedido->items[0]->cantidad_pendiente)->toBe(10.0)
        ->and($pedido->fecha_entrega_estimada->toDateString())->toBe($cuando->toDateString());

    // Entrega parcial: 4 de 10
    $this->post(route('despachos.confirmar', $pedido->id), [
        'fecha' => now()->toDateString(), 'imprimir' => true,
        'items' => [['id' => $pedido->items[0]->id, 'cantidad' => 4]],
    ])->assertSessionHas('despacho_entrega');

    expect(enStock($this))->toBe(46.0)
        ->and((float) $pedido->items[0]->fresh()->cantidad_pendiente)->toBe(6.0);
});

it('si la empresa lo decide, el envío sale del stock al vender', function () {
    enActivar($this, ['envio_sale_al_entregar' => false, 'ruta_obligatoria' => false, 'fecha_obligatoria' => false]);

    $this->post(route('ventas.store'), enVenta($this, ['tipo_entrega' => 'envio', 'cliente_direccion' => 'Obra Pomalca']))
        ->assertSessionHasNoErrors();

    expect(enStock($this))->toBe(40.0)
        ->and(ClienteAnticipo::where('venta_id', enUltimaVenta($this)->id)->exists())->toBeFalse();
});

it('la cajera puede marcar lo que el cliente se lleva ahora en un envío', function () {
    enActivar($this, ['ruta_obligatoria' => false, 'fecha_obligatoria' => false]);

    $payload = enVenta($this, ['tipo_entrega' => 'envio', 'cliente_direccion' => 'Obra Pomalca']);
    $payload['items'][0]['cantidad_pendiente'] = 7;   // se lleva 3
    $this->post(route('ventas.store'), $payload)->assertSessionHasNoErrors();
    expect(enStock($this))->toBe(47.0);

    // Si se lo lleva todo, no es un envío
    $payload['items'][0]['cantidad_pendiente'] = 0;
    $payload['idempotency_key'] = 'envio-sin-pendiente';
    $this->post(route('ventas.store'), $payload)->assertSessionHasErrors('entrega_pendiente');
});

it('el ticket dice cómo se entrega, por dónde y si hay que cobrar', function () {
    enActivar($this, ['textos' => ['envio' => 'ENVÍO A OBRA', 'cobrar_entrega' => 'COBRAR EN OBRA']]);
    $this->env->empresa->update(['ticket_plantilla' => ['plantilla' => 'detallada']]);
    $ruta = enRuta($this);
    $cuando = now()->addDay()->setTime(9, 0);

    $this->post(route('ventas.store'), enVenta($this, [
        'tipo_entrega' => 'envio', 'ruta_entrega_id' => $ruta->id, 'entrega_programada' => $cuando->format('Y-m-d\TH:i'),
        'cliente_direccion' => 'Calle Los Cedros 245', 'es_credito' => true,
    ], 50))->assertSessionHasNoErrors();
    $venta = enUltimaVenta($this);

    $bloques = app(TicketPrintService::class)->payloadDeVenta($venta)['bloques'];
    $lineas = fn (string $tipo) => collect($bloques)->where('tipo', $tipo)->map(fn ($b) => array_column($b['lineas'], 'texto'))->values()->all();

    expect($lineas('banda'))->toBe([['RUTA 3', 'POMALCA'], ['POR CANCELAR', 'COBRAR EN OBRA', 'S/ 150.00']]);
    expect($lineas('recuadro'))->toContain(['ZONA', 'Pomalca', 'DIRECCIÓN', 'Calle Los Cedros 245'], ['ENVÍO A OBRA']);
    expect(json_encode($bloques, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES))->toContain('Entrega programada:', TicketPrintService::fechaHoraCorta($cuando));

    $tabla = collect($bloques)->first(fn ($b) => $b['tipo'] === 'tabla' && $b['columnas'][1]['titulo'] === 'Vendida');
    expect($tabla['filas'])->toBe([['Cemento Sol x ' . $venta->items[0]->unidad_nombre, '10', '0', '10']]);

    // Un recojo sin pendientes: recuadro de recojo y sin tabla de entrega
    $this->post(route('ventas.store'), enVenta($this, ['idempotency_key' => 'venta-de-recojo']))->assertSessionHasNoErrors();
    $bloques = app(TicketPrintService::class)->payloadDeVenta(enUltimaVenta($this))['bloques'];
    expect(json_encode($bloques, JSON_UNESCAPED_UNICODE))->toContain('RECOJO EN TIENDA')->not->toContain('Vendida');
});

it('el ticket de despacho muestra lo que se entrega y lo que falta', function () {
    enActivar($this, ['ruta_obligatoria' => false, 'fecha_obligatoria' => false]);
    $this->env->empresa->update(['ticket_plantilla' => ['plantilla' => 'detallada']]);

    $this->post(route('ventas.store'), enVenta($this, ['tipo_entrega' => 'envio', 'cliente_direccion' => 'Obra Pomalca']))->assertSessionHasNoErrors();
    $pedido = ClienteAnticipo::where('venta_id', enUltimaVenta($this)->id)->with('items')->firstOrFail();

    $this->post(route('despachos.confirmar', $pedido->id), [
        'fecha' => now()->toDateString(), 'items' => [['id' => $pedido->items[0]->id, 'cantidad' => 4]],
    ])->assertSessionMissing('despacho_entrega');   // no pidió imprimir

    $entrega = $pedido->aplicaciones()->latest('id')->firstOrFail();
    $ticket = $this->getJson(route('despachos.entrega.ticket', $entrega->id))->assertOk()->json();

    expect($ticket['items'])->toHaveCount(1);   // los agentes anteriores siguen imprimiendo
    $textos = json_encode($ticket['bloques'], JSON_UNESCAPED_UNICODE);
    expect($textos)->toContain('DESPACHO DE MERCADERÍA', 'ENVÍO A DOMICILIO', 'Obra Pomalca', 'PAGADO', 'Recibí conforme')
        ->not->toContain('FORMA DE PAGO');

    $tabla = collect($ticket['bloques'])->firstWhere('tipo', 'tabla');
    expect(array_column($tabla['columnas'], 'titulo'))->toBe(['Producto', 'Vendida', 'Entrega', 'Pendiente'])
        ->and(array_slice($tabla['filas'][0], 1))->toBe(['10', '4', '6']);
});

it('los despachos salen ordenados por hora y se filtran por día y por ruta', function () {
    enActivar($this, ['ruta_obligatoria' => false, 'fecha_obligatoria' => false]);
    $norte = enRuta($this, 'Norte', null);
    $sur = enRuta($this, 'Sur', null);

    $crear = function (?string $cuando, ?int $rutaId, string $clave) {
        $this->post(route('ventas.store'), enVenta($this, [
            'tipo_entrega' => 'envio', 'cliente_direccion' => 'Obra', 'ruta_entrega_id' => $rutaId,
            'entrega_programada' => $cuando, 'idempotency_key' => "despacho-{$clave}-0001",
        ]))->assertSessionHasNoErrors();

        return enUltimaVenta($this)->numero;
    };
    $tarde   = $crear(now()->setTime(16, 0)->format('Y-m-d\TH:i'), $sur->id, 'a');
    $manana  = $crear(now()->addDay()->setTime(8, 0)->format('Y-m-d\TH:i'), $norte->id, 'b');
    $temprano = $crear(now()->setTime(9, 30)->format('Y-m-d\TH:i'), $norte->id, 'c');
    $sinFecha = $crear(null, null, 'd');
    $ayer    = $crear(now()->subDay()->setTime(10, 0)->format('Y-m-d\TH:i'), $sur->id, 'e');

    $ver = fn (array $filtros = []) => collect($this->get(route('despachos.index', $filtros))->assertOk()
        ->original->getData()['page']['props']['pendientes']['data'])->pluck('venta.numero')->all();

    expect($ver())->toBe([$ayer, $temprano, $tarde, $manana, $sinFecha]);
    expect($ver(['cuando' => 'hoy']))->toBe([$temprano, $tarde]);
    expect($ver(['cuando' => 'atrasados']))->toBe([$ayer]);
    expect($ver(['cuando' => 'sin_fecha']))->toBe([$sinFecha]);
    expect($ver(['fecha' => now()->addDay()->toDateString()]))->toBe([$manana]);
    expect($ver(['ruta_id' => $norte->id]))->toBe([$temprano, $manana]);

    $props = $this->get(route('despachos.index'))->original->getData()['page']['props'];
    expect(collect($props['conteos'])->all())->toBe(['todos' => 5, 'hoy' => 2, 'manana' => 1, 'atrasados' => 1, 'sin_fecha' => 1]);
});

it('Configuración → Entregas guarda la configuración y las rutas', function () {
    $this->put(route('configuracion.entregas.update'), [
        'activo' => true, 'aviso_monto' => 800, 'ruta_obligatoria' => false, 'fecha_obligatoria' => true, 'envio_sale_al_entregar' => true,
        'textos' => ['envio' => '  Envío a obra ', 'recojo' => ''],
    ])->assertSessionHasNoErrors();
    expect($this->env->empresa->fresh()->entrega_config['textos'])->toMatchArray(['envio' => 'Envío a obra', 'recojo' => 'RECOJO EN TIENDA']);

    $this->post(route('configuracion.entregas.rutas.store'), ['nombre' => 'Ruta 3', 'zona' => 'Pomalca'])->assertSessionHasNoErrors();
    $this->post(route('configuracion.entregas.rutas.store'), ['nombre' => 'Ruta 3'])->assertSessionHasErrors('nombre');
    $ruta = RutaEntrega::where('empresa_id', $this->env->empresa->id)->firstOrFail();

    $pos = $this->get(route('pos.index'))->original->getData()['page']['props']['entregas'];
    expect($pos['aviso_monto'])->toEqual(800)
        ->and($pos['ruta_obligatoria'])->toBeFalse()
        ->and($pos['texto_envio'])->toBe('Envío a obra')
        ->and($pos['texto_recojo'])->toBe('RECOJO EN TIENDA')
        ->and(collect($pos['rutas'])->pluck('nombre')->all())->toBe(['Ruta 3']);

    // Sin ventas se elimina; con ventas se desactiva y conserva su historial
    $otra = enRuta($this, 'Ruta 9');
    $this->delete(route('configuracion.entregas.rutas.destroy', $otra->id));
    expect(RutaEntrega::find($otra->id))->toBeNull();

    $this->post(route('ventas.store'), enVenta($this, [
        'tipo_entrega' => 'envio', 'ruta_entrega_id' => $ruta->id, 'cliente_direccion' => 'Obra',
        'entrega_programada' => now()->addDay()->format('Y-m-d\TH:i'),
    ]))->assertSessionHasNoErrors();
    $this->delete(route('configuracion.entregas.rutas.destroy', $ruta->id));
    expect($ruta->fresh()->activo)->toBeFalse();

    // Sin aviso
    $this->put(route('configuracion.entregas.update'), [
        'activo' => true, 'aviso_monto' => null, 'ruta_obligatoria' => true, 'fecha_obligatoria' => true, 'envio_sale_al_entregar' => true,
    ]);
    expect($this->get(route('pos.index'))->original->getData()['page']['props']['entregas']['aviso_monto'])->toBeNull();

    // Una ruta de otra empresa no se toca
    $ajena = RutaEntrega::create(['empresa_id' => TestEnv::crear()->empresa->id, 'nombre' => 'Ajena']);
    $this->put(route('configuracion.entregas.rutas.update', $ajena->id), ['nombre' => 'Mía'])->assertForbidden();
});
