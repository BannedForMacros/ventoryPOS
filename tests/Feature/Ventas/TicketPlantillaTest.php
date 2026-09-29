<?php

use App\Models\Cliente;
use App\Models\Cotizacion;
use App\Services\TicketPrintService;
use App\Services\VentaService;
use App\Support\PlantillaTicket;
use Tests\Support\TestEnv;

/**
 * Ticket por plantilla. Con la plantilla estándar el ticket no cambia; con la
 * detallada viajan los bloques (y también los campos de siempre, para los
 * agentes de impresión anteriores a 1.3.0).
 */
beforeEach(function () {
    $this->env = TestEnv::crear();
    $this->turno = $this->env->abrirTurno();
    $this->actingAs($this->env->admin);
    $this->tickets = app(TicketPrintService::class);

    $this->cliente = Cliente::create([
        'empresa_id' => $this->env->empresa->id, 'nombres' => 'Dagoberto', 'apellidos' => 'Rodríguez',
        'tipo_documento' => 'DNI', 'numero_documento' => '16789012',
        'telefono' => '979555012', 'direccion' => 'Av. Balta 100', 'activo' => true,
    ]);
});

function tpVenta($test, array $extra = [], float $pago = 200): \App\Models\Venta
{
    $p = $test->env->crearProducto(['nombre' => 'Cemento Sol', 'precio_venta' => 20, 'stock_inicial' => 50]);

    return app(VentaService::class)->crear(array_merge([
        'tipo_comprobante' => 'ticket',
        'cliente_id'       => $test->cliente->id,
        'items' => [['producto_id' => $p->id, 'producto_unidad_id' => $p->unidadBase->id, 'cantidad' => 10, 'precio_unitario' => 20]],
        'pagos' => $pago > 0 ? [['metodo_pago_id' => $test->env->metodo('efectivo')->id, 'monto' => $pago]] : [],
    ], $extra), $test->env->admin, $test->turno);
}

function tpDetallada($test, array $plantilla = []): void
{
    $test->env->empresa->update(['ticket_plantilla' => ['plantilla' => 'detallada'] + $plantilla]);
}

/** Todos los textos que salen en los bloques, para buscar sin depender del orden. */
function tpTextos(array $bloques): array
{
    $textos = [];
    array_walk_recursive($bloques, function ($v, $k) use (&$textos) {
        if (in_array($k, ['texto', 'etiqueta', 'valor'], true) && is_string($v)) {
            $textos[] = $v;
        }
    });

    return $textos;
}

function tpBloque(array $bloques, string $tipo, int $n = 0): ?array
{
    return array_values(array_filter($bloques, fn ($b) => $b['tipo'] === $tipo))[$n] ?? null;
}

it('con la plantilla estándar el ticket no lleva bloques', function () {
    $payload = $this->tickets->payloadDeVenta(tpVenta($this));

    expect($payload)->not->toHaveKey('bloques')
        ->and($payload['cliente']['telefono'])->toBe('979555012')
        ->and($payload['pago']['metodo'])->toBe('Efectivo');
});

it('la detallada manda los bloques y conserva los campos de siempre', function () {
    tpDetallada($this);
    $this->env->admin->update(['telefono' => '974123456']);

    $payload = $this->tickets->payloadDeVenta(tpVenta($this, ['observacion' => 'Dejar por la puerta lateral'], 250));
    $textos = tpTextos($payload['bloques']);

    // Un agente anterior imprime con esto
    expect($payload['items'])->toHaveCount(1)
        ->and((float) $payload['totales']['total'])->toBe(200.0);

    expect($textos)->toContain('Cel. cajero:', '974123456', 'Dejar por la puerta lateral', 'DATOS DEL CLIENTE', 'FORMA DE PAGO');

    // Teléfono y dirección del cliente, destacados en un recuadro
    expect(collect(tpBloque($payload['bloques'], 'recuadro')['lineas'])->pluck('texto')->filter()->values()->all())
        ->toBe(['TELÉFONO', '979555012', 'DIRECCIÓN', 'Av. Balta 100']);

    // Pago por medio (sin el vuelto), recibido y vuelto
    $pagos = collect($payload['bloques'])->first(fn ($b) => $b['tipo'] === 'pares' && ($b['estilo'] ?? '') === 'extremos'
        && collect($b['items'])->contains('etiqueta', 'Efectivo:'));
    expect(collect($pagos['items'])->pluck('valor', 'etiqueta')->all())
        ->toBe(['Efectivo:' => 'S/ 200.00', 'Recibido:' => 'S/ 250.00', 'Vuelto:' => 'S/ 50.00']);

    // Pagado, en grande
    expect(tpBloque($payload['bloques'], 'banda')['lineas'])->toBe([['texto' => 'PAGADO', 'tamano' => 'grande', 'negrita' => true]]);

    // Productos: nombre con su unidad y los números como texto
    expect(tpBloque($payload['bloques'], 'tabla')['filas'][0])->toBe(['Cemento Sol x ' . $payload['items'][0]['unidad'], '10', '20.00', '200.00']);
});

it('una venta con saldo sale POR CANCELAR con el monto a cobrar', function () {
    tpDetallada($this, ['textos' => ['por_cancelar_detalle' => 'COBRAR AL ENTREGAR']]);

    $venta = tpVenta($this, ['es_credito' => true, 'fecha_vencimiento' => now()->addDays(15)->toDateString()], 50);
    $payload = $this->tickets->payloadDeVenta($venta);

    expect(collect(tpBloque($payload['bloques'], 'banda')['lineas'])->pluck('texto')->all())
        ->toBe(['POR CANCELAR', 'COBRAR AL ENTREGAR', 'S/ 150.00']);
    expect(tpTextos($payload['bloques']))->toContain('A cuenta:', 'S/ 50.00', 'Saldo:', 'S/ 150.00', 'Vence:');
});

it('usa el teléfono y la dirección con que se atendió la venta, no los de la ficha', function () {
    tpDetallada($this);

    $venta = tpVenta($this);
    $venta->update(['cliente_telefono' => '900111222', 'cliente_direccion' => 'Obra: Calle Los Cedros 245']);
    $payload = $this->tickets->payloadDeVenta($venta->fresh());

    expect($payload['cliente']['telefono'])->toBe('900111222')
        ->and(tpTextos($payload['bloques']))->toContain('Obra: Calle Los Cedros 245')
        ->and(tpTextos($payload['bloques']))->not->toContain('Av. Balta 100');
});

it('respeta las secciones apagadas, el orden y los textos de la empresa', function () {
    tpDetallada($this, [
        'secciones' => [
            ['clave' => 'estado_pago', 'activa' => true],
            ['clave' => 'logo', 'activa' => true],
            ['clave' => 'negocio', 'activa' => true],
            ['clave' => 'documento', 'activa' => false],   // fija: no se puede apagar
            ['clave' => 'cliente', 'activa' => false],
            ['clave' => 'items', 'activa' => true],
            ['clave' => 'totales', 'activa' => true],
            ['clave' => 'pagos', 'activa' => false],
            ['clave' => 'pie', 'activa' => true],
        ],
        'textos'   => ['pagado' => 'CANCELADO'],
        'opciones' => ['recuadros_texto' => true],
    ]);

    $bloques = $this->tickets->payloadDeVenta(tpVenta($this))['bloques'];
    $textos = tpTextos($bloques);

    expect($bloques[0])->toBe(['tipo' => 'banda', 'modo' => 'texto', 'lineas' => [['texto' => 'CANCELADO', 'tamano' => 'grande', 'negrita' => true]]]);
    expect($textos)->toContain('Fecha:')
        ->not->toContain('DATOS DEL CLIENTE')
        ->not->toContain('FORMA DE PAGO');
    expect(tpBloque($bloques, 'tabla'))->not->toBeNull();
});

it('la cotización lleva los datos del cliente pero no el estado de pago', function () {
    tpDetallada($this);
    $this->env->admin->update(['telefono' => '974123456']);
    $p = $this->env->crearProducto(['nombre' => 'Fierro 1/2', 'precio_venta' => 35]);

    $cot = Cotizacion::create([
        'empresa_id' => $this->env->empresa->id, 'local_id' => $this->env->local->id, 'user_id' => $this->env->admin->id,
        'cliente_id' => $this->cliente->id, 'numero' => 'COT-0001', 'estado' => 'vigente', 'fecha' => now()->toDateString(),
        'subtotal' => 70, 'descuento_total' => 0, 'igv' => 0, 'total' => 70,
        'observacion' => 'Entregar en la mañana', 'cliente_direccion' => 'Obra Pomalca',
    ]);
    $cot->items()->create([
        'producto_id' => $p->id, 'producto_unidad_id' => $p->unidadBase->id, 'producto_nombre' => 'Fierro 1/2', 'unidad_nombre' => 'UND',
        'cantidad' => 2, 'precio_unitario' => 35, 'descuento_item' => 0, 'subtotal' => 70,
    ]);

    $payload = $this->tickets->payloadDeCotizacion($cot, $this->env->admin);
    $textos = tpTextos($payload['bloques']);

    expect($textos)->toContain('Obra Pomalca', 'Entregar en la mañana', '974123456', 'COTIZACIÓN / PROFORMA' . "\n" . 'COT-0001');
    expect(tpBloque($payload['bloques'], 'banda'))->toBeNull();
    expect($textos)->not->toContain('FORMA DE PAGO');
    // La observación va con el cliente: no se repite en el pie
    expect(collect($payload['bloques'])->last()['texto'])->toStartWith('Proforma');
});

it('una sección nueva entra en su lugar aunque la empresa guardó su plantilla antes', function () {
    $pl = PlantillaTicket::resolver(['plantilla' => 'detallada', 'secciones' => [
        ['clave' => 'negocio', 'activa' => true],
        ['clave' => 'items', 'activa' => true],
        ['clave' => 'seccion_que_ya_no_existe', 'activa' => true],
        ['clave' => 'pie', 'activa' => false],
    ]]);

    expect(array_column($pl['secciones'], 'clave'))
        ->toBe(['logo', 'negocio', 'documento', 'cliente', 'items', 'totales', 'pagos', 'estado_pago', 'pie']);
    expect(collect($pl['secciones'])->firstWhere('clave', 'pie')['activa'])->toBeFalse();

    expect(PlantillaTicket::resolver(null)['plantilla'])->toBe('estandar');
    expect(PlantillaTicket::resolver(['plantilla' => 'inventada'])['plantilla'])->toBe('estandar');
});
