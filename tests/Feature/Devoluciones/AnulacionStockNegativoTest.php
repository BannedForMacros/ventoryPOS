<?php

use App\Models\Auditoria;
use App\Models\Stock;
use App\Services\DevolucionService;
use App\Services\VentaService;
use Tests\Support\TestEnv;

beforeEach(function () {
    $this->env    = TestEnv::crear();
    $this->turno  = $this->env->abrirTurno();
    $this->actingAs($this->env->admin);
});

it('anular devolución que deja stock negativo audita con genero_stock_negativo=true y detalles', function () {
    $producto = $this->env->crearProducto([
        'precio_venta'  => 10,
        'stock_inicial' => 5,
        'incluye_igv'   => false,
        'nombre'        => 'Crema dental',
    ]);

    // 1) Venta de 5 → stock 0
    $venta = app(VentaService::class)->crear([
        'tipo_comprobante' => 'ticket',
        'items' => [[
            'producto_id'        => $producto->id,
            'producto_unidad_id' => $producto->unidadBase->id,
            'cantidad'           => 5,
            'precio_unitario'    => 10,
        ]],
        'pagos' => [[
            'metodo_pago_id' => $this->env->metodo('efectivo')->id,
            'monto'          => 50,
        ]],
    ], $this->env->admin, $this->turno);

    // 2) Devolución de 5 con restock → stock vuelve a 5
    $devolucion = app(DevolucionService::class)->crear([
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
    ], $this->env->admin, $this->turno);

    expect((float) Stock::where('producto_id', $producto->id)->first()->cantidad)->toBe(5.0);

    // 3) Otra venta de 5 — vacía el stock de nuevo (esto es el "entre tanto").
    app(VentaService::class)->crear([
        'tipo_comprobante' => 'ticket',
        'items' => [[
            'producto_id'        => $producto->id,
            'producto_unidad_id' => $producto->unidadBase->id,
            'cantidad'           => 5,
            'precio_unitario'    => 10,
        ]],
        'pagos' => [[
            'metodo_pago_id' => $this->env->metodo('efectivo')->id,
            'monto'          => 50,
        ]],
    ], $this->env->admin, $this->turno);

    expect((float) Stock::where('producto_id', $producto->id)->first()->cantidad)->toBe(0.0);

    // 4) Anular la primera devolución: revierte 5 unidades, pero ya no estaban → -5
    $devolucion->fresh()->anular();

    $stockFinal = (float) Stock::where('producto_id', $producto->id)->first()->cantidad;
    expect($stockFinal)->toBe(-5.0);

    // La auditoria debe reflejar la inconsistencia generada
    $audit = Auditoria::where('accion', 'devolucion.anulada')
        ->where('modelo_id', $devolucion->id)
        ->latest('id')->first();
    expect($audit)->not->toBeNull();
    expect($audit->contexto['genero_stock_negativo'])->toBeTrue();
    expect($audit->contexto['stocks_negativos'])->toHaveCount(1);
    expect($audit->contexto['stocks_negativos'][0]['producto_id'])->toBe($producto->id);
    expect((float) $audit->contexto['stocks_negativos'][0]['cantidad_final'])->toBe(-5.0);
});

it('anular devolución SIN dejar stock negativo audita genero_stock_negativo=false', function () {
    $producto = $this->env->crearProducto(['precio_venta' => 10, 'stock_inicial' => 50, 'incluye_igv' => false]);

    $venta = app(VentaService::class)->crear([
        'tipo_comprobante' => 'ticket',
        'items' => [[
            'producto_id'        => $producto->id,
            'producto_unidad_id' => $producto->unidadBase->id,
            'cantidad'           => 3,
            'precio_unitario'    => 10,
        ]],
        'pagos' => [[
            'metodo_pago_id' => $this->env->metodo('efectivo')->id,
            'monto'          => 30,
        ]],
    ], $this->env->admin, $this->turno);

    $devolucion = app(DevolucionService::class)->crear([
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

    $devolucion->fresh()->anular();

    $audit = Auditoria::where('accion', 'devolucion.anulada')
        ->where('modelo_id', $devolucion->id)->latest('id')->first();
    expect($audit->contexto['genero_stock_negativo'])->toBeFalse();
    expect($audit->contexto['stocks_negativos'])->toBeNull();
});
