<?php

use App\Models\Cliente;
use App\Services\DevolucionService;
use App\Services\UtilidadService;
use App\Services\VentaService;
use Tests\Support\TestEnv;

/**
 * INV-2 / INV-7 — la utilidad resta de una devolución lo MISMO que movió de
 * verdad (reembolso o CxC cancelada), no el bruto de sus líneas.
 *
 *  - Con descuento global el reembolso es neto (monto_devolucion) y se restaba
 *    el bruto (dd.subtotal): la utilidad bajaba por la parte del descuento.
 *  - "Sin reembolso" en una venta al crédito cancela la CxC, pero la utilidad
 *    seguía contando la venta entera: quedaba inflada por lo que nunca se cobra.
 */
beforeEach(function () {
    $this->env   = TestEnv::crear();
    $this->turno = $this->env->abrirTurno();
    $this->actingAs($this->env->admin);
    $this->producto = $this->env->crearProducto(['precio_venta' => 10, 'precio_costo' => 6, 'stock_inicial' => 50, 'incluye_igv' => false]);
    $this->hoy = now()->toDateString();
});

function rudVender($test, float $cantidad, array $extra): \App\Models\Venta
{
    return app(VentaService::class)->crear(array_merge([
        'tipo_comprobante' => 'ticket',
        'items' => [[
            'producto_id'        => $test->producto->id,
            'producto_unidad_id' => $test->producto->unidadBase->id,
            'cantidad'           => $cantidad,
            'precio_unitario'    => 10,
        ]],
    ], $extra), $test->env->admin, $test->turno)->fresh('items');
}

function rudDevolver($test, \App\Models\Venta $venta, float $cantidad, string $forma, ?float $pago): \App\Models\Devolucion
{
    return app(DevolucionService::class)->crear([
        'venta_id'        => $venta->id,
        'motivo_id'       => $test->env->motivo('producto_equivocado')->id,
        'forma_reembolso' => $forma,
        'items' => [[
            'venta_item_id' => $venta->items->first()->id, 'cantidad' => $cantidad,
            'estado_producto' => 'bueno', 'restock' => true,
        ]],
        'pagos' => $pago ? [['metodo_pago_id' => $test->env->metodo('efectivo')->id, 'monto' => $pago]] : [],
    ], $test->env->admin, $test->turno);
}

it('con descuento global devolver todo deja la utilidad en cero (resta el reembolso neto)', function () {
    // 5 × 10 = 50 con S/ 5 de descuento global → pagó 45; costo 5 × 6 = 30.
    $venta = rudVender($this, 5, [
        'descuento_total' => 5,
        'pagos' => [['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => 45]],
    ]);
    $d = rudDevolver($this, $venta, 5, 'efectivo', 45);
    expect((float) $d->monto_devolucion)->toBe(45.0);

    $r = app(UtilidadService::class)->resumen($this->env->empresa->id, $this->hoy, $this->hoy);
    expect($r['devuelto'])->toBe(45.0)          // antes 50 (bruto)
        ->and($r['ventas'])->toBe(0.0)
        ->and($r['utilidad_bruta'])->toBe(0.0);  // antes −5

    $u = app(UtilidadService::class)->deVenta($venta->fresh());
    expect($u['devuelto'])->toBe(45.0)->and($u['utilidad'])->toBe(0.0);
});

it('sin reembolso en una venta al crédito resta lo que canceló de la CxC', function () {
    $cliente = Cliente::create([
        'empresa_id' => $this->env->empresa->id, 'nombres' => 'Cliente', 'apellidos' => 'Crédito',
        'tipo_documento' => 'DNI', 'numero_documento' => (string) random_int(10000000, 89999999), 'activo' => true,
    ]);
    // Al crédito por 100, pagó 0 → debe 100.
    $venta = rudVender($this, 10, ['cliente_id' => $cliente->id, 'es_credito' => true, 'pagos' => []]);
    expect((float) $venta->saldo_pendiente)->toBe(100.0);

    rudDevolver($this, $venta, 10, 'sin_reembolso', null);
    expect((float) $venta->fresh()->saldo_pendiente)->toBe(0.0);   // la deuda se canceló

    $r = app(UtilidadService::class)->resumen($this->env->empresa->id, $this->hoy, $this->hoy);
    expect($r['devuelto'])->toBe(100.0)          // antes 0
        ->and($r['ventas'])->toBe(0.0)
        ->and($r['utilidad_bruta'])->toBe(0.0);  // antes 100 (venta que nunca se cobra)
});

it('sin reembolso al contado sigue sin restar dinero', function () {
    $venta = rudVender($this, 5, ['pagos' => [['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => 50]]]);
    rudDevolver($this, $venta, 2, 'sin_reembolso', null);

    $r = app(UtilidadService::class)->resumen($this->env->empresa->id, $this->hoy, $this->hoy);
    expect($r['devuelto'])->toBe(0.0)->and($r['ventas'])->toBe(50.0);
});
