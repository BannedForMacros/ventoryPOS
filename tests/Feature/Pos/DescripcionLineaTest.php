<?php

use App\Models\Venta;
use App\Models\VentaComprobante;
use App\Services\TicketPrintService;
use Tests\Support\TestEnv;

/**
 * Descripción opcional por línea: distingue varias líneas del mismo producto
 * (empresas que venden el mismo producto con precios distintos). Sale en el
 * ticket interno; el comprobante electrónico lleva solo el nombre.
 */
beforeEach(function () {
    $this->env = TestEnv::crear(['modo_cierre_caja' => 'rapido']);
    $this->env->empresa->update(['permite_duplicar_items_venta' => true]);
    $this->turno = $this->env->abrirTurno();
    $this->actingAs($this->env->admin);
    $this->producto = $this->env->crearProducto(['nombre' => 'Pulsera', 'precio_venta' => 10, 'stock_inicial' => 50]);
});

function dlLinea($test, float $cantidad, float $precio, ?string $descripcion = null): array
{
    return array_filter([
        'producto_id'        => $test->producto->id,
        'producto_unidad_id' => $test->producto->unidadBase->id,
        'cantidad'           => $cantidad,
        'precio_unitario'    => $precio,
        'descripcion'        => $descripcion,
    ], fn ($v) => $v !== null);
}

function dlVenta($test, array $items): array
{
    $total = array_sum(array_map(fn ($i) => $i['cantidad'] * $i['precio_unitario'], $items));

    return [
        'tipo_comprobante' => 'ticket',
        'cliente_id'       => $test->env->clienteGeneral->id,
        'items'            => $items,
        'pagos'            => [['metodo_pago_id' => $test->env->metodo('efectivo')->id, 'monto' => $total]],
    ];
}

function dlUltima($test): Venta
{
    return Venta::where('empresa_id', $test->env->empresa->id)->latest('id')->firstOrFail();
}

it('guarda la descripción de cada línea del mismo producto por separado', function () {
    $this->post(route('ventas.store'), dlVenta($this, [
        dlLinea($this, 2, 9, '  roja  '),
        dlLinea($this, 1, 13.5, 'tejida'),
        dlLinea($this, 1, 7),
    ]))->assertSessionHasNoErrors();

    $items = dlUltima($this)->items()->orderBy('id')->get();
    expect($items->pluck('descripcion')->all())->toBe(['roja', 'tejida', null])
        ->and($items->pluck('precio_unitario')->map(fn ($p) => (float) $p)->all())->toBe([9.0, 13.5, 7.0]);
});

it('la descripción sale en el ticket interno junto al nombre', function () {
    $this->post(route('ventas.store'), dlVenta($this, [dlLinea($this, 2, 9, 'roja'), dlLinea($this, 1, 7)]))
        ->assertSessionHasNoErrors();

    $payload = app(TicketPrintService::class)->payloadDeVenta(dlUltima($this));

    expect(array_column($payload['items'], 'desc'))->toBe(['Pulsera - roja', 'Pulsera']);
});

it('el comprobante electrónico lleva solo el nombre del producto', function () {
    $this->post(route('ventas.store'), dlVenta($this, [dlLinea($this, 2, 9, 'roja')]))->assertSessionHasNoErrors();
    $venta = dlUltima($this);
    // Una boleta ya emitida (se fuerza en BD: aquí no se habla con FacturaMac).
    $venta->update(['tipo_comprobante' => 'boleta']);
    VentaComprobante::create([
        'venta_id' => $venta->id, 'tipo' => '03', 'estado' => 'aceptado',
        'facturamac_id' => 901, 'numero' => 'B001-00000031', 'idempotency_key' => 'dl-' . $venta->id,
    ]);

    $payload = app(TicketPrintService::class)->payloadDeVenta($venta->fresh());

    expect($payload['documento']['electronico'])->toBeTrue()
        ->and(array_column($payload['items'], 'desc'))->toBe(['Pulsera']);
});

it('editar la venta conserva y cambia la descripción de cada línea', function () {
    $this->post(route('ventas.store'), dlVenta($this, [dlLinea($this, 2, 9, 'roja'), dlLinea($this, 1, 7, 'azul')]))
        ->assertSessionHasNoErrors();
    $venta = dlUltima($this);

    // Al abrir la edición, cada línea trae su descripción.
    $this->get(route('pos.index', ['venta_id' => $venta->id]))
        ->assertInertia(fn ($page) => $page
            ->where('ventaEnEdicion.items.0.descripcion', 'roja')
            ->where('ventaEnEdicion.items.1.descripcion', 'azul'));

    $this->put(route('ventas.update', $venta), dlVenta($this, [dlLinea($this, 2, 9, 'roja'), dlLinea($this, 1, 8)]))
        ->assertSessionHasNoErrors();

    $items = $venta->fresh()->items()->orderBy('id')->get();
    expect($items->pluck('descripcion')->all())->toBe(['roja', null])
        ->and((float) $items[1]->precio_unitario)->toBe(8.0);
});

it('rechaza una descripción demasiado larga', function () {
    $this->post(route('ventas.store'), dlVenta($this, [dlLinea($this, 1, 9, str_repeat('x', 121))]))
        ->assertSessionHasErrors('items.0.descripcion');
});
