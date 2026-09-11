<?php

use App\Models\ClienteAnticipo;
use App\Services\DevolucionService;
use App\Services\VentaService;
use Illuminate\Support\Facades\DB;
use Tests\Support\TestEnv;

/**
 * Vale/Crédito a favor → ANTICIPO real del cliente. Antes era solo una
 * etiqueta; ahora la devolución completada crea automáticamente un anticipo
 * de dinero (sin tesorería) usable en POS y CxC, y anular la devolución
 * revierte el vale (solo si no se usó).
 */

beforeEach(function () {
    $this->env    = TestEnv::crear();
    $this->turno  = $this->env->abrirTurno();
    $this->devols = app(DevolucionService::class);
    $this->actingAs($this->env->admin);
});

function ventaParaVale(TestEnv $env, $turno, float $precio = 10, float $cantidad = 5): \App\Models\Venta
{
    $producto = $env->crearProducto([
        'precio_venta'  => $precio,
        'stock_inicial' => 50,
        'incluye_igv'   => false,
    ]);

    return app(VentaService::class)->crear([
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
}

function devolucionConVale($test, \App\Models\Venta $venta, float $cantidad = 5)
{
    return $test->devols->crear([
        'venta_id'        => $venta->id,
        'motivo_id'       => $test->env->motivo('producto_equivocado')->id,
        'forma_reembolso' => 'vale_credito',
        'items' => [[
            'venta_item_id'   => $venta->items->first()->id,
            'cantidad'        => $cantidad,
            'estado_producto' => 'bueno',
            'restock'         => true,
        ]],
        'pagos' => [],
    ], $test->env->admin, $test->turno);
}

it('devolución con vale crea automáticamente un anticipo del cliente, sin tesorería', function () {
    $venta      = ventaParaVale($this->env, $this->turno); // total 50
    $devolucion = devolucionConVale($this, $venta, 5);

    expect($devolucion->estado)->toBe('completada');

    $anticipo = ClienteAnticipo::where('devolucion_id', $devolucion->id)->firstOrFail();
    expect($anticipo->cliente_id)->toBe($venta->cliente_id);
    expect((float) $anticipo->monto)->toBe(50.0);
    expect((float) $anticipo->saldo)->toBe(50.0);
    expect($anticipo->tipo_valorizacion)->toBe('monto');
    expect($anticipo->estado)->toBe('activo');

    // SIN movimientos de tesorería: ni egreso de reembolso ni ingreso de anticipo.
    expect(DB::table('cuenta_movimientos')->where('ref_tipo', 'devolucion')->where('ref_id', $devolucion->id)->count())->toBe(0);
    expect(DB::table('cuenta_movimientos')->where('ref_tipo', 'cliente_anticipo')->where('ref_id', $anticipo->id)->count())->toBe(0);
});

it('anular la devolución anula el vale si NO se ha usado', function () {
    $venta      = ventaParaVale($this->env, $this->turno);
    $devolucion = devolucionConVale($this, $venta, 5);
    $anticipo   = ClienteAnticipo::where('devolucion_id', $devolucion->id)->firstOrFail();

    $devolucion->anular();

    expect($devolucion->fresh()->estado)->toBe('anulada');
    expect($anticipo->fresh()->estado)->toBe('anulado');
});

it('NO se puede anular la devolución si el vale ya fue usado en parte', function () {
    $venta      = ventaParaVale($this->env, $this->turno);
    $devolucion = devolucionConVale($this, $venta, 5);
    $anticipo   = ClienteAnticipo::where('devolucion_id', $devolucion->id)->firstOrFail();

    // Simular consumo parcial del vale (ej. lo usó en el POS).
    $anticipo->update(['saldo' => 20]);

    expect(fn () => $devolucion->anular())->toThrow(LogicException::class);
    expect($devolucion->fresh()->estado)->toBe('completada');
    expect($anticipo->fresh()->estado)->toBe('activo');
});

it('devolución en efectivo NO crea anticipo (comportamiento intacto)', function () {
    $venta = ventaParaVale($this->env, $this->turno);

    $devolucion = $this->devols->crear([
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

    expect(ClienteAnticipo::where('devolucion_id', $devolucion->id)->count())->toBe(0);
});
