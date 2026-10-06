<?php

use App\Jobs\EmitirComprobanteElectronico;
use App\Models\Cliente;
use App\Models\ClienteAnticipo;
use App\Models\Stock;
use App\Models\Venta;
use App\Models\VentaComprobante;
use App\Services\EntregaPendienteService;
use App\Services\Facturacion\FacturacionEmpresa;
use App\Services\Facturacion\VentaAContrato;
use App\Services\VentaService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\Support\TestEnv;

/**
 * Revisión de devoluciones (octubre 2026), segunda parte: emisión a SUNAT de
 * ventas anuladas, despachos y guías. Una prueba por bug confirmado.
 */
beforeEach(function () {
    Queue::fake();
    $this->env   = TestEnv::crear();
    $this->turno = $this->env->abrirTurno();
    $this->actingAs($this->env->admin);
});

/** Venta con 7 de 10 pendientes de entrega (anticipo material del POS). */
function revVentaConPendiente(TestEnv $env, $turno): array
{
    $cliente = Cliente::create([
        'empresa_id' => $env->empresa->id, 'nombres' => 'Cliente', 'apellidos' => 'Despacho',
        'tipo_documento' => 'DNI', 'numero_documento' => (string) random_int(10000000, 89999999), 'activo' => true,
    ]);
    $fierro = $env->crearProducto(['precio_venta' => 20, 'stock_inicial' => 50]);
    $venta = app(VentaService::class)->crear([
        'tipo_comprobante'  => 'ticket',
        'cliente_id'        => $cliente->id,
        'entrega_pendiente' => true,
        'items' => [[
            'producto_id' => $fierro->id, 'producto_unidad_id' => $fierro->unidadBase->id,
            'cantidad' => 10, 'precio_unitario' => 20, 'cantidad_pendiente' => 7,
        ]],
        'pagos' => [['metodo_pago_id' => $env->metodo('efectivo')->id, 'monto' => 200]],
    ], $env->admin, $turno);

    return [$venta, $fierro, ClienteAnticipo::where('venta_id', $venta->id)->with('items')->first()];
}

it('BUG 5 — una venta anulada no se emite a SUNAT ni admite reintento', function () {
    config(['facturamac.base_url' => 'http://emisor.test']);
    $this->env->conectarFacturacion();
    Http::fake();

    $producto = $this->env->crearProducto(['precio_venta' => 75, 'incluye_igv' => true]);
    $venta = app(VentaService::class)->crear([
        'tipo_comprobante' => 'boleta',
        'cliente_id'       => $this->env->clienteGeneral->id,
        'items' => [['producto_id' => $producto->id, 'producto_unidad_id' => $producto->unidadBase->id, 'cantidad' => 1, 'precio_unitario' => 75]],
        'pagos' => [['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => 75]],
    ], $this->env->admin, $this->turno);

    // Quedó en error de envío y, mientras esperaba el reintento, se anuló.
    $ce = VentaComprobante::create([
        'venta_id' => $venta->id, 'tipo' => '03', 'estado' => 'error_envio',
        'idempotency_key' => 'rev-anulada-' . $venta->id, 'intentos' => 1,
    ]);
    Venta::whereKey($venta->id)->update(['estado' => 'anulada']);

    // El rechazo de negocio vuelve a la pantalla con el motivo, sin encolar nada.
    $this->from(route('ventas.show', $venta))
        ->post(route('ventas.comprobante.reintentar', $venta))
        ->assertRedirect(route('ventas.show', $venta))
        ->assertSessionHasErrors();
    expect(collect(session('errors')->all())->implode(' '))->toContain('anulada');
    Queue::assertNotPushed(EmitirComprobanteElectronico::class);

    // El job encolado ANTES de anular (lleva la venta vieja en memoria) tampoco emite.
    (new EmitirComprobanteElectronico($venta))
        ->handle(app(VentaAContrato::class), app(FacturacionEmpresa::class));

    Http::assertNothingSent();
    expect($ce->fresh()->estado)->toBe(VentaComprobante::ESTADO_NO_EMITIDO);
    Queue::assertNotPushed(EmitirComprobanteElectronico::class);
});

it('BUG 8 — una confirmación de despacho con datos viejos no descuenta el stock dos veces', function () {
    [$venta, $fierro, $anticipo] = revVentaConPendiente($this->env, $this->turno);
    $item = $anticipo->items->first();
    expect((float) Stock::where('producto_id', $fierro->id)->value('cantidad'))->toBe(47.0);

    // Dos pantallas abiertas leyeron "7 pendientes" a la vez.
    $vistaA = ClienteAnticipo::with('items')->find($anticipo->id);
    $vistaB = ClienteAnticipo::with('items')->find($anticipo->id);
    $datos  = ['fecha' => now()->toDateString(), 'items' => [['id' => $item->id, 'cantidad' => 7]]];

    app(EntregaPendienteService::class)->aplicarEntregaMaterial($vistaA, $datos, $this->env->admin);

    expect(fn () => app(EntregaPendienteService::class)->aplicarEntregaMaterial($vistaB, $datos, $this->env->admin))
        ->toThrow(ValidationException::class);

    // Salieron 3 al vender + 7 al entregar, una sola vez.
    expect((float) Stock::where('producto_id', $fierro->id)->value('cantidad'))->toBe(40.0)
        ->and($anticipo->aplicaciones()->count())->toBe(1);
});

it('BUG 10 — confirmar un despacho sin fecha ni ítems responde con errores en español, no con un 500', function () {
    [, , $anticipo] = revVentaConPendiente($this->env, $this->turno);

    $this->from(route('despachos.index'))
        ->post(route('despachos.confirmar', $anticipo), [])
        ->assertRedirect(route('despachos.index'))
        ->assertSessionHasErrors([
            'fecha' => 'Indica la fecha de la entrega.',
            'items' => 'Indica qué productos se entregan.',
        ]);
});

it('BUG 12 — una guía no se puede colgar de una venta, cliente o transferencia de otra empresa', function () {
    $otra = TestEnv::crear();
    $otroTurno = $otra->abrirTurno();
    $producto = $otra->crearProducto(['precio_venta' => 10]);
    $ventaAjena = app(VentaService::class)->crear([
        'tipo_comprobante' => 'ticket',
        'items' => [['producto_id' => $producto->id, 'producto_unidad_id' => $producto->unidadBase->id, 'cantidad' => 1, 'precio_unitario' => 10]],
        'pagos' => [['metodo_pago_id' => $otra->metodo('efectivo')->id, 'monto' => 10]],
    ], $otra->admin, $otroTurno);

    $this->actingAs($this->env->admin)
        ->from(route('guias.index'))
        ->post(route('guias.store'), [
            'motivo' => 'venta', 'modalidad' => 'privado', 'fecha_inicio' => now()->toDateString(),
            'peso_total' => 1,
            'partida' => ['direccion' => 'Av. Uno 1', 'ubigeo' => '150101'],
            'llegada' => ['direccion' => 'Av. Dos 2', 'ubigeo' => '150101'],
            'items'   => [['descripcion' => 'Fierro', 'cantidad' => 1]],
            'venta_id'   => $ventaAjena->id,
            'cliente_id' => $otra->clienteGeneral->id,
        ])
        ->assertSessionHasErrors([
            'venta_id'   => 'La venta indicada no existe en tu empresa.',
            'cliente_id' => 'El cliente indicado no existe en tu empresa.',
        ]);

    expect(\App\Models\Guia::where('venta_id', $ventaAjena->id)->exists())->toBeFalse();
});
