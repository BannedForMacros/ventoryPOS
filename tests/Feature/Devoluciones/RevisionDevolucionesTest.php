<?php

use App\Jobs\EmitirNotaCreditoElectronica;
use App\Models\Cliente;
use App\Models\ClienteAnticipo;
use App\Models\Devolucion;
use App\Models\Stock;
use App\Models\Venta;
use App\Models\VentaComprobante;
use App\Services\DevolucionService;
use App\Services\VentaService;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\Support\TestEnv;

/**
 * Revisión de devoluciones (octubre 2026). Una prueba por bug confirmado:
 * cada una falla con el código anterior y pasa con el arreglo.
 */
beforeEach(function () {
    $this->env    = TestEnv::crear();
    $this->turno  = $this->env->abrirTurno();
    $this->ventas = app(VentaService::class);
    $this->devols = app(DevolucionService::class);
    $this->actingAs($this->env->admin);

    $this->cliente = Cliente::create([
        'empresa_id'       => $this->env->empresa->id,
        'nombres'          => 'Constructora',
        'apellidos'        => 'Revisión',
        'tipo_documento'   => 'DNI',
        'numero_documento' => (string) random_int(10000000, 89999999),
        'activo'           => true,
    ]);
});

/** Venta de contado de un producto exonerado (total = cantidad × precio). */
function revVenta(TestEnv $env, $turno, array $extra = [], float $precio = 10, float $cantidad = 5): array
{
    $producto = $env->crearProducto(['precio_venta' => $precio, 'stock_inicial' => 50, 'incluye_igv' => false]);
    $venta = app(VentaService::class)->crear(array_merge([
        'tipo_comprobante' => 'ticket',
        'items' => [[
            'producto_id'        => $producto->id,
            'producto_unidad_id' => $producto->unidadBase->id,
            'cantidad'           => $cantidad,
            'precio_unitario'    => $precio,
        ]],
        'pagos' => [['metodo_pago_id' => $env->metodo('efectivo')->id, 'monto' => $precio * $cantidad]],
    ], $extra), $env->admin, $turno);

    return [$venta->fresh('items'), $producto];
}

function revDevolver(Venta $venta, float $cantidad, string $forma = 'efectivo', ?float $pago = null): Devolucion
{
    return app(DevolucionService::class)->crear([
        'venta_id'        => $venta->id,
        'motivo_id'       => test()->env->motivo('producto_equivocado')->id,
        'forma_reembolso' => $forma,
        'items' => [[
            'venta_item_id'   => $venta->items->first()->id,
            'cantidad'        => $cantidad,
            'estado_producto' => 'bueno',
            'restock'         => true,
        ]],
        'pagos' => $pago !== null && $pago > 0
            ? [['metodo_pago_id' => test()->env->metodo('efectivo')->id, 'monto' => $pago]]
            : [],
    ], test()->env->admin, test()->turno);
}

it('BUG 1 — lo pendiente de entrega no se devuelve con reingreso: no infla el stock ni deja vivo el pendiente', function () {
    $fierro = $this->env->crearProducto(['precio_venta' => 20, 'stock_inicial' => 50, 'incluye_igv' => false]);
    $venta = $this->ventas->crear([
        'tipo_comprobante'  => 'ticket',
        'cliente_id'        => $this->cliente->id,
        'entrega_pendiente' => true,
        'items' => [[
            'producto_id'        => $fierro->id,
            'producto_unidad_id' => $fierro->unidadBase->id,
            'cantidad'           => 10,
            'precio_unitario'    => 20,
            'cantidad_pendiente' => 7, // se lleva 3
        ]],
        'pagos' => [['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => 200]],
    ], $this->env->admin, $this->turno)->fresh('items');

    expect((float) Stock::where('producto_id', $fierro->id)->value('cantidad'))->toBe(47.0);

    // Devolver las 10: 7 nunca salieron del almacén.
    expect(fn () => revDevolver($venta, 10, 'efectivo', 200))
        ->toThrow(ValidationException::class, 'pendientes de entrega');

    expect((float) Stock::where('producto_id', $fierro->id)->value('cantidad'))->toBe(47.0);
    $anticipo = ClienteAnticipo::where('venta_id', $venta->id)->with('items')->first();
    expect((float) $anticipo->items->first()->cantidad_pendiente)->toBe(7.0)
        ->and((float) $anticipo->saldo)->toBe(140.0);

    // Lo ENTREGADO (3) sí se devuelve y vuelve al almacén: 47 + 3 = 50.
    revDevolver($venta, 3, 'efectivo', 60);
    expect((float) Stock::where('producto_id', $fierro->id)->value('cantidad'))->toBe(50.0);
});

it('BUG 2 — no admite una devolución sobre una venta anulada', function () {
    [$venta, $producto] = revVenta($this->env, $this->turno);
    $this->ventas->anular($venta, $this->env->admin);
    $stockTrasAnular = (float) Stock::where('producto_id', $producto->id)->value('cantidad');

    expect(fn () => revDevolver($venta, 5, 'efectivo', 50))
        ->toThrow(ValidationException::class, 'anulada');

    expect(Devolucion::where('venta_id', $venta->id)->count())->toBe(0)
        ->and((float) Stock::where('producto_id', $producto->id)->value('cantidad'))->toBe($stockTrasAnular);
});

it('BUG 3 — el reembolso descuenta el descuento global de la venta, igual que la nota de crédito', function () {
    // 5 × 10 = 50 con S/ 5 de descuento global → el cliente pagó 45.
    [$venta] = revVenta($this->env, $this->turno, [
        'descuento_total' => 5,
        'pagos'           => [['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => 45]],
    ]);
    expect((float) $venta->total)->toBe(45.0);

    // Por HTTP: un reembolso de 50 (precio de lista) ya no pasa.
    $this->from(route('devoluciones.create'))->post(route('devoluciones.store'), [
        'venta_id' => $venta->id, 'motivo_id' => $this->env->motivo('producto_equivocado')->id,
        'forma_reembolso' => 'efectivo', 'turno_id' => $this->turno->id,
        'items' => [['venta_item_id' => $venta->items->first()->id, 'cantidad' => 2, 'estado_producto' => 'bueno', 'restock' => true]],
        'pagos' => [['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => 20]],
    ])->assertSessionHasErrors('pagos');

    // 2 de 5 → 20 × 0.9 = 18.
    $devolucion = revDevolver($venta, 2, 'efectivo', 18);
    expect((float) $devolucion->monto_devolucion)->toBe(18.0)
        // El detalle conserva el bruto: es lo que la NC prorratea.
        ->and((float) $devolucion->detalles->first()->subtotal)->toBe(20.0);
});

it('BUG 4 — en una venta al crédito lo devuelto baja primero la deuda y solo se reembolsa lo pagado', function () {
    // Venta al crédito de 100 con 30 pagados al contado → debe 70.
    [$venta] = revVenta($this->env, $this->turno, [
        'cliente_id' => $this->cliente->id,
        'es_credito' => true,
        'pagos'      => [['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => 30]],
    ], precio: 10, cantidad: 10);
    expect((float) $venta->saldo_pendiente)->toBe(70.0);

    // Devuelve 8 (S/ 80): 70 cancelan la deuda, se le reembolsan 10.
    $this->from(route('devoluciones.create'))->post(route('devoluciones.store'), [
        'venta_id' => $venta->id, 'motivo_id' => $this->env->motivo('producto_equivocado')->id,
        'forma_reembolso' => 'efectivo', 'turno_id' => $this->turno->id,
        'items' => [['venta_item_id' => $venta->items->first()->id, 'cantidad' => 8, 'estado_producto' => 'bueno', 'restock' => true]],
        'pagos' => [['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => 80]],
    ])->assertSessionHasErrors('pagos');

    $devolucion = revDevolver($venta, 8, 'efectivo', 10);

    $venta->refresh();
    expect($devolucion->estado)->toBe('completada')
        ->and((float) $devolucion->monto_reembolso)->toBe(10.0)
        ->and((float) $venta->saldo_pendiente)->toBe(0.0)
        ->and((float) $venta->monto_pagado)->toBe(100.0);

    // Queda trazado como abono SIN dinero contra el anticipo de la devolución.
    $abono = $venta->abonos()->first();
    expect((float) $abono->monto)->toBe(70.0)
        ->and($abono->metodo_pago_id)->toBeNull()
        ->and($abono->anticipo->devolucion_id)->toBe($devolucion->id);

    // Anular la devolución devuelve la deuda a la venta.
    $devolucion->fresh()->anular();
    $venta->refresh();
    expect((float) $venta->saldo_pendiente)->toBe(70.0)
        ->and($venta->abonos()->count())->toBe(0)
        ->and($abono->anticipo->fresh()->estado)->toBe('anulado');
});

it('BUG 6 — la nota de crédito no se encola mientras la devolución espera aprobación', function () {
    Queue::fake();
    $this->env->empresa->update(['requiere_aprobacion_devolucion' => true]);

    [$venta] = revVenta($this->env, $this->turno);
    VentaComprobante::create([
        'venta_id' => $venta->id, 'tipo' => '03', 'estado' => 'aceptado',
        'facturamac_id' => 999, 'numero' => 'B001-00000001', 'idempotency_key' => 'rev-' . $venta->id,
    ]);

    $devolucion = revDevolver($venta, 2, 'efectivo', 20);
    expect($devolucion->estado)->toBe('pendiente')
        ->and($devolucion->nota_credito_estado)->toBeNull();
    Queue::assertNotPushed(EmitirNotaCreditoElectronica::class);

    // Aprobada (y completada) recién se encola.
    $this->post(route('devoluciones.aprobar', $devolucion))->assertSessionHasNoErrors();
    expect($devolucion->fresh()->estado)->toBe('completada')
        ->and($devolucion->fresh()->nota_credito_estado)->toBe(Devolucion::NC_PENDIENTE);
    Queue::assertPushed(EmitirNotaCreditoElectronica::class, 1);
});

it('BUG 7 — no se anula una devolución cuya nota de crédito ya se emitió', function () {
    [$venta, $producto] = revVenta($this->env, $this->turno);
    $devolucion = revDevolver($venta, 2, 'efectivo', 20);
    $devolucion->anotarNotaCredito(Devolucion::NC_EMITIDA, null, ['nota_credito_numero' => 'BC01-00000007']);

    expect(fn () => $devolucion->fresh()->anular())
        ->toThrow(LogicException::class, 'BC01-00000007');

    expect($devolucion->fresh()->estado)->toBe('completada')
        ->and((float) Stock::where('producto_id', $producto->id)->value('cantidad'))->toBe(47.0);
});

it('BUG 9 — el buscador exige el número exacto y ofrece elegir si hay varias ventas con el mismo', function () {
    [$v1] = revVenta($this->env, $this->turno);
    $this->turno->update(['estado' => 'cerrado']);
    $otroTurno = $this->env->abrirTurno();
    [$v2] = revVenta($this->env, $otroTurno);
    // Correlativo por turno: el mismo número en dos turnos distintos.
    Venta::whereKey($v2->id)->update(['numero' => $v1->numero]);

    $r = $this->getJson(route('devoluciones.buscar-venta', ['q' => strtolower($v1->numero)]))->assertOk();
    expect($r->json('coincidencias'))->toHaveCount(2)
        ->and($r->json('venta'))->toBeNull();

    // Elegida de la lista, por id.
    $this->getJson(route('devoluciones.buscar-venta', ['venta_id' => $v1->id]))
        ->assertOk()->assertJsonPath('venta.id', $v1->id);

    // Un pedazo del número ya no "encuentra" la más reciente.
    $this->getJson(route('devoluciones.buscar-venta', ['q' => substr($v1->numero, 0, 3)]))->assertNotFound();
});

it('BUG 11 — aprobar dos veces (doble clic) devuelve un aviso, no un error 500', function () {
    Queue::fake();
    $this->env->empresa->update(['requiere_aprobacion_devolucion' => true]);
    [$venta, $producto] = revVenta($this->env, $this->turno);
    $devolucion = revDevolver($venta, 2, 'efectivo', 20);

    $this->post(route('devoluciones.aprobar', $devolucion))->assertSessionHasNoErrors();
    $this->post(route('devoluciones.aprobar', $devolucion))
        ->assertRedirect()
        ->assertSessionHasErrors(['general' => 'Esta devolución ya fue aprobada y completada.']);
    $this->post(route('devoluciones.rechazar', $devolucion))
        ->assertRedirect()
        ->assertSessionHasErrors('general');

    // Un solo restock: 45 + 2.
    expect((float) Stock::where('producto_id', $producto->id)->value('cantidad'))->toBe(47.0);
});
