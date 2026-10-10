<?php

use App\Jobs\EmitirNotaCreditoElectronica;
use App\Models\Devolucion;
use App\Models\VentaComprobante;
use App\Services\VentaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Support\TestEnv;

/**
 * Anular una venta cuya boleta/factura ya está en SUNAT: el mismo botón Anular
 * hace la devolución total (vuelve el stock, sale el dinero por el mismo medio)
 * y la devolución emite sola la Nota de Crédito.
 */

beforeEach(function () {
    Queue::fake();
    $this->env = TestEnv::crear(['modo_cierre_caja' => 'rapido']);
    $this->actingAs($this->env->admin);
    $this->producto = $this->env->crearProducto(['precio_venta' => 10, 'precio_costo' => 6, 'stock_inicial' => 100]);
    $this->turno = $this->env->abrirTurno($this->env->admin);
});

function ventaConBoletaEnSunat($test, array $pagos = null, bool $credito = false)
{
    $venta = app(VentaService::class)->crear([
        'tipo_comprobante' => 'boleta',
        'es_credito'       => $credito,
        'items' => [[
            'producto_id' => $test->producto->id, 'producto_unidad_id' => $test->producto->unidadBase->id,
            'cantidad' => 3, 'precio_unitario' => 10,
        ]],
        'pagos' => $pagos ?? [['metodo_pago_id' => $test->env->metodo('efectivo')->id, 'monto' => 30]],
    ], $test->env->admin, $test->turno);
    VentaComprobante::create([
        'venta_id' => $venta->id, 'tipo' => '03', 'estado' => 'aceptado',
        'facturamac_id' => 777, 'numero' => 'B001-00000018', 'idempotency_key' => 'anc-' . $venta->id,
    ]);

    return $venta->fresh();
}

function stockDe($test): float
{
    return (float) DB::table('stock')->where('producto_id', $test->producto->id)->sum('cantidad');
}

it('anular una boleta en SUNAT hace la devolución total, devuelve el dinero y emite la nota de crédito', function () {
    $venta = ventaConBoletaEnSunat($this);
    $antes = stockDe($this);

    $this->post(route('ventas.anular', $venta), ['motivo' => 'El cliente se arrepintió de la compra'])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', fn ($m) => str_contains($m, 'nota de crédito'));

    $dev = Devolucion::where('venta_id', $venta->id)->with('pagos')->first();
    expect($dev)->not->toBeNull()
        ->and($dev->estado)->toBe('completada')
        ->and((float) $dev->monto_devolucion)->toBe(30.0)
        ->and((float) $dev->monto_reembolso)->toBe(30.0)
        ->and($dev->pagos->first()->metodo_pago_id)->toBe($this->env->metodo('efectivo')->id);
    expect(stockDe($this))->toBe($antes + 3);
    Queue::assertPushed(EmitirNotaCreditoElectronica::class);

    // Después, el detalle de la venta abre bien y ya no ofrece anularla otra vez.
    $this->get(route('ventas.show', $venta))->assertOk()->assertInertia(fn ($p) => $p
        ->where('anulacionNc.bloqueo', 'Todo lo de esta venta ya se devolvió.')
        ->where('bloqueoFiscal', fn ($m) => str_contains($m, 'se devolvió todo')));

    // La caja del turno ya no cuenta esa venta: entró 30 y salió 30.
    expect((float) $this->turno->fresh()->calcularMontoEsperado())->toBe((float) $this->turno->monto_apertura);
});

it('pagada con dos medios: devuelve a cada uno lo suyo', function () {
    $venta = ventaConBoletaEnSunat($this, [
        ['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => 10],
        ['metodo_pago_id' => $this->env->metodo('yape')->id, 'monto' => 20],
    ]);
    $plan = app(\App\Services\AnulacionConNotaCredito::class)->plan($venta);
    expect(collect($plan['pagos'])->pluck('monto', 'metodo_pago_id')->all())->toBe([
        $this->env->metodo('efectivo')->id => 10.0,
        $this->env->metodo('yape')->id     => 20.0,
    ]);

    $this->post(route('ventas.anular', $venta), ['motivo' => 'Se cobró a la persona equivocada'])->assertSessionHasNoErrors();
    expect((float) Devolucion::where('venta_id', $venta->id)->first()->monto_reembolso)->toBe(30.0);
});

it('una venta con productos por entregar no se anula en un paso: pide Devoluciones', function () {
    $venta = ventaConBoletaEnSunat($this);
    DB::table('cliente_anticipos')->insert([
        'empresa_id' => $this->env->empresa->id, 'cliente_id' => $venta->cliente_id ?? $this->env->clienteGeneral()->id,
        'venta_id' => $venta->id, 'monto' => 10, 'saldo' => 10, 'estado' => 'activo', 'fecha' => today(),
        'tipo_valorizacion' => 'material', 'user_id' => $this->env->admin->id, 'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->post(route('ventas.anular', $venta), ['motivo' => 'El cliente ya no quiere nada'])
        ->assertSessionHasErrors(['venta' => 'Esta venta tiene productos pendientes por entregar. Hazlo desde Devoluciones, eligiendo qué se devuelve y cómo.']);
    expect(Devolucion::where('venta_id', $venta->id)->exists())->toBeFalse();
});

it('el detalle de la venta ofrece anular con nota de crédito y dice cuánto vuelve', function () {
    $venta = ventaConBoletaEnSunat($this);
    $this->get(route('ventas.show', $venta))->assertInertia(fn ($p) => $p
        ->where('bloqueoFiscal', null)
        ->where('anulacionNc.comprobante', 'B001-00000018')
        ->where('anulacionNc.reembolso', 30)
        ->where('anulacionNc.bloqueo', null));
});

it('una venta sin comprobante en SUNAT se anula como siempre (sin devolución)', function () {
    $venta = app(VentaService::class)->crear([
        'tipo_comprobante' => 'ticket',
        'items' => [['producto_id' => $this->producto->id, 'producto_unidad_id' => $this->producto->unidadBase->id, 'cantidad' => 1, 'precio_unitario' => 10]],
        'pagos' => [['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => 10]],
    ], $this->env->admin, $this->turno);

    $this->post(route('ventas.anular', $venta), ['motivo' => 'Se registró dos veces'])->assertSessionHasNoErrors();
    expect($venta->fresh()->estado)->toBe('anulada')
        ->and(Devolucion::where('venta_id', $venta->id)->exists())->toBeFalse();
});
