<?php

use App\Models\Stock;
use App\Services\DevolucionService;
use App\Services\VentaService;
use Tests\Support\TestEnv;

beforeEach(function () {
    $this->env     = TestEnv::crear();
    $this->turno   = $this->env->abrirTurno();
    $this->ventas  = app(VentaService::class);
    $this->devols  = app(DevolucionService::class);
    $this->actingAs($this->env->admin);
});

/**
 * Helper local: crea una venta con un producto físico (precio_venta=10, exonerado
 * para que total == cantidad * precio sin IGV de por medio).
 */
function ventaConProducto(TestEnv $env, $turno, float $precio = 10, float $cantidad = 5, float $stockInicial = 50): array
{
    $producto = $env->crearProducto([
        'precio_venta'  => $precio,
        'stock_inicial' => $stockInicial,
        'incluye_igv'   => false,
    ]);
    $venta = app(VentaService::class)->crear([
        'tipo_comprobante' => 'ticket',
        'items' => [[
            'producto_id'        => $producto->id,
            'producto_unidad_id' => $producto->unidadBase->id,
            'cantidad'           => $cantidad,
            'precio_unitario'    => $precio,
        ]],
        'pagos' => [[
            'metodo_pago_id' => $env->metodo('efectivo')->id,
            'monto'          => $precio * $cantidad,
        ]],
    ], $env->admin, $turno);

    return [$venta, $producto];
}

it('devolución TOTAL con restock devuelve todo al stock y la marca como completada', function () {
    [$venta, $producto] = ventaConProducto($this->env, $this->turno, precio: 10, cantidad: 5);
    $stockTrasVenta = (float) Stock::where('producto_id', $producto->id)->first()->cantidad;
    expect($stockTrasVenta)->toBe(45.0); // 50 - 5

    $devolucion = $this->devols->crear([
        'venta_id'        => $venta->id,
        'motivo_id'       => $this->env->motivo('producto_equivocado')->id, // afecta=permite → restockea
        'forma_reembolso' => 'efectivo',
        'items' => [[
            'venta_item_id'    => $venta->items->first()->id,
            'cantidad'         => 5, // toda la venta
            'estado_producto'  => 'bueno',
            'restock'          => true,
        ]],
        'pagos' => [[
            'metodo_pago_id' => $this->env->metodo('efectivo')->id,
            'monto'          => 50,
        ]],
    ], $this->env->admin, $this->turno);

    expect($devolucion->estado)->toBe('completada');
    expect((float) $devolucion->monto_devolucion)->toBe(50.0);
    expect((float) $devolucion->monto_reembolso)->toBe(50.0);
    expect((float) Stock::where('producto_id', $producto->id)->first()->cantidad)->toBe(50.0);
});

it('devolución PARCIAL solo repone las unidades devueltas', function () {
    [$venta, $producto] = ventaConProducto($this->env, $this->turno, precio: 10, cantidad: 5);
    // Stock tras venta: 45

    $devolucion = $this->devols->crear([
        'venta_id'        => $venta->id,
        'motivo_id'       => $this->env->motivo('producto_equivocado')->id,
        'forma_reembolso' => 'efectivo',
        'items' => [[
            'venta_item_id'    => $venta->items->first()->id,
            'cantidad'         => 2, // solo 2 de las 5
            'estado_producto'  => 'bueno',
            'restock'          => true,
        ]],
        'pagos' => [[
            'metodo_pago_id' => $this->env->metodo('efectivo')->id,
            'monto'          => 20,
        ]],
    ], $this->env->admin, $this->turno);

    expect((float) $devolucion->monto_devolucion)->toBe(20.0);
    expect((float) Stock::where('producto_id', $producto->id)->first()->cantidad)->toBe(47.0); // 45 + 2
});

it('motivo "vencido" (obliga_merma) FUERZA restock=false aunque el cajero lo marque true', function () {
    [$venta, $producto] = ventaConProducto($this->env, $this->turno, precio: 10, cantidad: 5);
    $stockPre = (float) Stock::where('producto_id', $producto->id)->first()->cantidad;
    expect($stockPre)->toBe(45.0);

    $devolucion = $this->devols->crear([
        'venta_id'        => $venta->id,
        'motivo_id'       => $this->env->motivo('vencido')->id, // obliga_merma → no restockea
        'forma_reembolso' => 'efectivo',
        'items' => [[
            'venta_item_id'    => $venta->items->first()->id,
            'cantidad'         => 3,
            'estado_producto'  => 'vencido', // CHECK constraint: bueno|defectuoso|vencido|dañado
            'restock'          => true, // intentamos forzar restock, pero el motivo manda
        ]],
        'pagos' => [[
            'metodo_pago_id' => $this->env->metodo('efectivo')->id,
            'monto'          => 30,
        ]],
    ], $this->env->admin, $this->turno);

    expect($devolucion->detalles->first()->restock)->toBeFalse();
    // Stock NO debe haberse repuesto: sigue en 45
    expect((float) Stock::where('producto_id', $producto->id)->first()->cantidad)->toBe(45.0);
});

it('producto NO retornable no restockea aunque el motivo lo permita', function () {
    $producto = $this->env->crearProducto([
        'precio_venta'  => 10,
        'stock_inicial' => 50,
        'incluye_igv'   => false,
        'es_retornable' => false, // explícito: no retorna al inventario
    ]);
    $venta = $this->ventas->crear([
        'tipo_comprobante' => 'ticket',
        'items' => [[
            'producto_id'        => $producto->id,
            'producto_unidad_id' => $producto->unidadBase->id,
            'cantidad'           => 4,
            'precio_unitario'    => 10,
        ]],
        'pagos' => [[
            'metodo_pago_id' => $this->env->metodo('efectivo')->id,
            'monto'          => 40,
        ]],
    ], $this->env->admin, $this->turno);

    $this->devols->crear([
        'venta_id'        => $venta->id,
        'motivo_id'       => $this->env->motivo('producto_equivocado')->id, // permite
        'forma_reembolso' => 'efectivo',
        'items' => [[
            'venta_item_id'   => $venta->items->first()->id,
            'cantidad'        => 4,
            'estado_producto' => 'bueno',
            'restock'         => true, // intento explícito
        ]],
        'pagos' => [[
            'metodo_pago_id' => $this->env->metodo('efectivo')->id,
            'monto'          => 40,
        ]],
    ], $this->env->admin, $this->turno);

    // Stock sigue en 46 (50 vendido 4, no se restockea aunque el motivo permita)
    expect((float) Stock::where('producto_id', $producto->id)->first()->cantidad)->toBe(46.0);
});

it('devolver más cantidad de la disponible lanza ValidationException', function () {
    [$venta] = ventaConProducto($this->env, $this->turno, precio: 10, cantidad: 3);

    $this->devols->crear([
        'venta_id'        => $venta->id,
        'motivo_id'       => $this->env->motivo('producto_equivocado')->id,
        'forma_reembolso' => 'efectivo',
        'items' => [[
            'venta_item_id'   => $venta->items->first()->id,
            'cantidad'        => 10, // se vendieron 3 nomás
            'estado_producto' => 'bueno',
            'restock'         => true,
        ]],
        'pagos' => [[
            'metodo_pago_id' => $this->env->metodo('efectivo')->id,
            'monto'          => 100,
        ]],
    ], $this->env->admin, $this->turno);
})->throws(\Illuminate\Validation\ValidationException::class);

it('anular una devolución completada revierte el restock', function () {
    [$venta, $producto] = ventaConProducto($this->env, $this->turno, precio: 10, cantidad: 5);

    $devolucion = $this->devols->crear([
        'venta_id'        => $venta->id,
        'motivo_id'       => $this->env->motivo('producto_equivocado')->id,
        'forma_reembolso' => 'efectivo',
        'items' => [[
            'venta_item_id'   => $venta->items->first()->id,
            'cantidad'        => 3,
            'estado_producto' => 'bueno',
            'restock'         => true,
        ]],
        'pagos' => [[
            'metodo_pago_id' => $this->env->metodo('efectivo')->id,
            'monto'          => 30,
        ]],
    ], $this->env->admin, $this->turno);

    expect((float) Stock::where('producto_id', $producto->id)->first()->cantidad)->toBe(48.0); // 45 + 3

    $devolucion->anular();

    expect($devolucion->fresh()->estado)->toBe('anulada');
    expect((float) Stock::where('producto_id', $producto->id)->first()->cantidad)->toBe(45.0); // revertido
});

it('rechaza una devolución en efectivo sin pagos', function () {
    [$venta] = ventaConProducto($this->env, $this->turno, precio: 10, cantidad: 5);

    $response = $this->from(route('devoluciones.create'))
        ->post(route('devoluciones.store'), [
            'venta_id'        => $venta->id,
            'motivo_id'       => $this->env->motivo('producto_equivocado')->id,
            'forma_reembolso' => 'efectivo',
            'items' => [[
                'venta_item_id'   => $venta->items->first()->id,
                'cantidad'        => 5,
                'estado_producto' => 'bueno',
                'restock'         => true,
            ]],
            'pagos' => [],
            'turno_id' => $this->turno->id,
        ]);

    $response->assertSessionHasErrors('pagos');
});

it('rechaza una devolución en efectivo cuando el total de pagos no coincide con el monto a devolver', function () {
    [$venta] = ventaConProducto($this->env, $this->turno, precio: 10, cantidad: 5);

    $response = $this->from(route('devoluciones.create'))
        ->post(route('devoluciones.store'), [
            'venta_id'        => $venta->id,
            'motivo_id'       => $this->env->motivo('producto_equivocado')->id,
            'forma_reembolso' => 'efectivo',
            'items' => [[
                'venta_item_id'   => $venta->items->first()->id,
                'cantidad'        => 5,
                'estado_producto' => 'bueno',
                'restock'         => true,
            ]],
            'pagos' => [[
                'metodo_pago_id' => $this->env->metodo('efectivo')->id,
                'monto'          => 30, // debería ser 50
            ]],
            'turno_id' => $this->turno->id,
        ]);

    $response->assertSessionHasErrors('pagos');
});

it('permite una devolución sin reembolso sin pagos', function () {
    [$venta, $producto] = ventaConProducto($this->env, $this->turno, precio: 10, cantidad: 5);

    $response = $this->from(route('devoluciones.create'))
        ->post(route('devoluciones.store'), [
            'venta_id'        => $venta->id,
            'motivo_id'       => $this->env->motivo('producto_equivocado')->id,
            'forma_reembolso' => 'sin_reembolso',
            'items' => [[
                'venta_item_id'   => $venta->items->first()->id,
                'cantidad'        => 5,
                'estado_producto' => 'bueno',
                'restock'         => true,
            ]],
            'pagos' => [],
            'turno_id' => $this->turno->id,
        ]);

    $response->assertRedirect();
    expect((float) Stock::where('producto_id', $producto->id)->first()->cantidad)->toBe(50.0);
});

it('una devolución en efectivo con pagos correctos registra el reembolso y afecta el cálculo del turno', function () {
    [$venta, $producto] = ventaConProducto($this->env, $this->turno, precio: 10, cantidad: 5);

    $response = $this->from(route('devoluciones.create'))
        ->post(route('devoluciones.store'), [
            'venta_id'        => $venta->id,
            'motivo_id'       => $this->env->motivo('producto_equivocado')->id,
            'forma_reembolso' => 'efectivo',
            'items' => [[
                'venta_item_id'   => $venta->items->first()->id,
                'cantidad'        => 5,
                'estado_producto' => 'bueno',
                'restock'         => true,
            ]],
            'pagos' => [[
                'metodo_pago_id' => $this->env->metodo('efectivo')->id,
                'monto'          => 50,
            ]],
            'turno_id' => $this->turno->id,
        ]);

    $response->assertRedirect();

    $devolucion = \App\Models\Devolucion::where('venta_id', $venta->id)->latest('id')->first();
    expect($devolucion)->not->toBeNull();
    expect((float) $devolucion->monto_reembolso)->toBe(50.0);
    expect($devolucion->turno_id)->toBe($this->turno->id);

    // Verifica que el cálculo del turno descuente el reembolso en efectivo
    $esperado = $this->turno->fresh()->calcularMontoEsperado();
    expect((float) $esperado)->toBe((float) $this->turno->monto_apertura); // solo apertura, venta y devolución se anulan
});
