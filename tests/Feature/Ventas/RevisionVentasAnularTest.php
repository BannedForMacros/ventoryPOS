<?php

use App\Models\Cita;
use App\Models\Cotizacion;
use App\Models\Devolucion;
use App\Models\Entrada;
use App\Models\EntradaPago;
use App\Models\Stock;
use App\Models\Venta;
use App\Services\CompensacionCxcCxpService;
use App\Services\VentaService;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\TestEnv;

/**
 * Revisión de la anulación de ventas: lo que ya se movió por otro lado
 * (devoluciones, compensaciones, SUNAT) no puede revertirse dos veces, y lo
 * que la venta "consumió" (cotización, cita) vuelve a quedar disponible.
 */

beforeEach(function () {
    $this->env      = TestEnv::crear(['modo_cierre_caja' => 'rapido']);
    $this->turno    = $this->env->abrirTurno();
    $this->service  = app(VentaService::class);
    $this->producto = $this->env->crearProducto(['precio_venta' => 10, 'stock_inicial' => 100]);
    $this->actingAs($this->env->admin);
});

function rvaVenta(array $extra = []): Venta
{
    return test()->service->crear(array_merge([
        'tipo_comprobante' => 'ticket',
        'items' => [[
            'producto_id' => test()->producto->id, 'producto_unidad_id' => test()->producto->unidadBase->id,
            'cantidad' => 2, 'precio_unitario' => 10,
        ]],
        'pagos' => [['metodo_pago_id' => test()->env->metodo('efectivo')->id, 'monto' => 20]],
    ], $extra), test()->env->admin, test()->turno);
}

function rvaStock(): float
{
    return (float) Stock::where('producto_id', test()->producto->id)->value('cantidad');
}

it('no deja anular una venta que tiene una devolución vigente', function () {
    $venta = rvaVenta();
    Devolucion::create([
        'empresa_id' => $this->env->empresa->id, 'local_id' => $this->env->local->id,
        'venta_id' => $venta->id, 'user_id' => $this->env->admin->id, 'fecha' => now(),
        'motivo_id' => $this->env->motivo('no_gusto')->id, 'estado' => 'completada',
        'monto_devolucion' => 10, 'monto_reembolso' => 10,
    ]);

    expect(fn () => $this->service->anular($venta, $this->env->admin, 'Cliente se arrepintió'))
        ->toThrow(HttpException::class, 'Anula primero la devolución');
    expect($venta->fresh()->estado)->toBe('completada');
});

it('anular una venta compensada contra una compra devuelve el saldo a la compra', function () {
    $venta = Venta::create([
        'empresa_id' => $this->env->empresa->id, 'local_id' => $this->env->local->id,
        'turno_id' => $this->turno->id, 'caja_id' => $this->env->caja->id, 'user_id' => $this->env->admin->id,
        'cliente_id' => $this->env->clienteGeneral->id, 'numero' => 'V-0900', 'tipo_comprobante' => 'ticket',
        'subtotal' => 50, 'descuento_total' => 0, 'igv' => 0, 'total' => 50, 'estado' => 'completada',
        'es_credito' => true, 'monto_pagado' => 0, 'saldo_pendiente' => 50, 'fecha_venta' => now(),
    ]);
    $entrada = Entrada::create([
        'empresa_id' => $this->env->empresa->id, 'almacen_id' => $this->env->almacen->id,
        'user_id' => $this->env->admin->id, 'proveedor' => 'Proveedor SAC', 'tipo' => 'compra',
        'fecha' => now()->toDateString(), 'estado' => 'confirmado', 'total' => 50,
        'monto_pagado' => 0, 'estado_pago' => 'pendiente',
    ]);
    app(CompensacionCxcCxpService::class)->crear($venta, $entrada, 50, now()->toDateString(), null, $this->env->admin);
    expect($entrada->fresh()->estado_pago)->toBe('pagado');

    $this->service->anular($venta->fresh(), $this->env->admin, 'Venta registrada por error');

    expect($entrada->fresh()->estado_pago)->toBe('pendiente')
        ->and((float) $entrada->fresh()->monto_pagado)->toBe(0.0)
        ->and(EntradaPago::where('entrada_id', $entrada->id)->count())->toBe(0);
});

it('dos anulaciones de la misma venta no devuelven el stock dos veces', function () {
    $venta = rvaVenta(); // stock 100 → 98
    $copia = Venta::find($venta->id); // la otra pestaña la tenía abierta

    $this->service->anular($venta, $this->env->admin, 'Primera anulación');

    expect(fn () => $this->service->anular($copia, $this->env->admin, 'Segunda anulación'))
        ->toThrow(RuntimeException::class, 'ya está anulada');
    expect(rvaStock())->toBe(100.0);
});

it('no deja anular una boleta cuyo comprobante sigue en cola para SUNAT', function () {
    $this->env->conectarFacturacion();
    $venta = rvaVenta(['tipo_comprobante' => 'boleta']);

    expect($venta->comprobanteElectronico()->exists())->toBeFalse();
    expect(fn () => $this->service->anular($venta, $this->env->admin, 'Se cobró mal'))
        ->toThrow(HttpException::class, 'en cola');
});

it('anular libera la cotización convertida y la cita completada para volver a cobrarlas', function () {
    $cliente = $this->env->clienteGeneral;
    $venta   = rvaVenta();
    $cotizacion = Cotizacion::create([
        'empresa_id' => $this->env->empresa->id, 'user_id' => $this->env->admin->id,
        'cliente_id' => $cliente->id, 'numero' => 'COT-' . random_int(1000, 9999), 'fecha' => now()->toDateString(),
        'estado' => Cotizacion::ESTADO_CONVERTIDA, 'venta_id' => $venta->id,
    ]);
    $cita = Cita::create([
        'empresa_id' => $this->env->empresa->id, 'local_id' => $this->env->local->id,
        'cliente_id' => $cliente->id, 'fecha_hora' => now(), 'estado' => Cita::ESTADO_COMPLETADA,
        'venta_id' => $venta->id, 'completada_at' => now(),
    ]);

    $this->service->anular($venta, $this->env->admin, 'Se registró dos veces');

    expect($cotizacion->fresh()->venta_id)->toBeNull()
        ->and($cotizacion->fresh()->esConvertible())->toBeTrue()
        ->and($cita->fresh()->venta_id)->toBeNull()
        ->and($cita->fresh()->estaActiva())->toBeTrue();
});
