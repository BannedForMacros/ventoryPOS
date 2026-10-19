<?php

use App\Jobs\ConsultarEstadoComprobante;
use App\Jobs\EmitirNotaCreditoElectronica;
use App\Models\Auditoria;
use App\Models\Devolucion;
use App\Models\VentaComprobante;
use App\Services\AnulacionConBajaBoleta;
use App\Services\VentaService;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Support\TestEnv;

/**
 * Anular una venta con BOLETA sin nota de crédito (baja por Resumen Diario).
 *
 * El caso real: la boleta del día todavía no salió en el resumen de las 23:55 y el
 * POS solo ofrecía nota de crédito —que además no se puede emitir hasta que SUNAT
 * conozca la boleta—. Ahora, si la boleta no se entregó al cliente, se anula al
 * instante: FacturaMac informa y da de baja la boleta solo, y aquí vuelven el stock
 * y la caja. Si FacturaMac no acepta la baja, no se toca nada.
 */

beforeEach(function () {
    Queue::fake();
    config(['facturamac.base_url' => 'http://facturamac.test']);
    $this->env = TestEnv::crear(['modo_cierre_caja' => 'rapido']);
    $this->env->conectarFacturacion();
    $this->actingAs($this->env->admin);
    $this->producto = $this->env->crearProducto(['precio_venta' => 10, 'precio_costo' => 6, 'stock_inicial' => 100]);
    $this->turno = $this->env->abrirTurno($this->env->admin);
    $this->llamadasBaja = [];
});

/** Venta de 3 x S/ 10 con boleta en el estado dado, ya registrada en FacturaMac (id 777). */
function ventaConBoleta($test, string $estado = 'pendiente_resumen', string $tipo = '03', array $pagos = null)
{
    $venta = app(VentaService::class)->crear([
        'tipo_comprobante' => $tipo === '01' ? 'factura' : 'boleta',
        'items' => [[
            'producto_id' => $test->producto->id, 'producto_unidad_id' => $test->producto->unidadBase->id,
            'cantidad' => 3, 'precio_unitario' => 10,
        ]],
        'pagos' => $pagos ?? [['metodo_pago_id' => $test->env->metodo('efectivo')->id, 'monto' => 30]],
    ], $test->env->admin, $test->turno);

    VentaComprobante::create([
        'venta_id' => $venta->id, 'tipo' => $tipo, 'estado' => $estado,
        'facturamac_id' => 777, 'numero' => $tipo === '01' ? 'F001-00000005' : 'B001-00000085',
        'idempotency_key' => 'baja-' . $venta->id,
    ]);

    return $venta->fresh();
}

/** FacturaMac falso: anota cada POST /baja y responde como el real. */
function fingirFacturaMac($test, int $status = 200, array $cuerpo = null): void
{
    Http::fake([
        '*/api/v1/ventas/777/baja' => function (HttpRequest $request) use ($test, $status, $cuerpo) {
            $test->llamadasBaja[] = $request->data();

            return Http::response($cuerpo ?? [
                'emitido' => true, 'modo' => 'produccion', 'id' => 777, 'estado' => 'pendiente_anulacion',
                'comprobante' => ['tipo' => 'boleta', 'serie' => 'B001', 'numero' => 85, 'numero_completo' => 'B001-00000085', 'fecha_emision' => now()->toDateString()],
                'reutilizado' => false,
            ], $status);
        },
        '*/api/v1/configuracion' => Http::response(['modo' => 'produccion', 'emision_activa' => true, 'envia_a_sunat' => true], 200),
    ]);
}

function stockBaja($test): float
{
    return (float) DB::table('stock')->where('producto_id', $test->producto->id)->sum('cantidad');
}

// ─── El caso de Farmacix: boleta del día, aún sin informar ───────────────────

it('el detalle ofrece anular sin nota de crédito una boleta que aún no sale en el resumen', function () {
    $venta = ventaConBoleta($this);

    $this->get(route('ventas.show', $venta))->assertOk()->assertInertia(fn ($p) => $p
        ->where('bloqueoFiscal', null)
        ->where('anulacionNc', null)
        ->where('anulacionBaja.comprobante', 'B001-00000085')
        ->where('anulacionBaja.en_tramite', false)
        ->where('anulacionBaja.bloqueo', null)
        ->where('anulacionBaja.pagos.0.monto', 30));
});

it('anula la venta al instante: pide la baja, vuelve el stock y la caja, sin nota de crédito', function () {
    $venta = ventaConBoleta($this);
    fingirFacturaMac($this);
    $antes = stockBaja($this);

    $this->post(route('ventas.anular', $venta), ['motivo' => 'Se cobró dos veces por error', 'via' => 'baja'])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', fn ($m) => str_contains($m, 'sin nota de crédito'));

    expect($venta->fresh()->estado)->toBe('anulada')
        ->and(stockBaja($this))->toBe($antes + 3)
        ->and((float) $this->turno->fresh()->calcularMontoEsperado())->toBe((float) $this->turno->monto_apertura)
        ->and(Devolucion::where('venta_id', $venta->id)->exists())->toBeFalse()
        ->and($venta->comprobanteElectronico()->first()->estado)->toBe('pendiente_anulacion')
        ->and($this->llamadasBaja)->toHaveCount(1)
        ->and($this->llamadasBaja[0]['motivo'])->toBe('Se cobró dos veces por error');

    Queue::assertNotPushed(EmitirNotaCreditoElectronica::class);
    Queue::assertPushed(ConsultarEstadoComprobante::class);

    $log = Auditoria::where('accion', 'venta.anulada')->where('modelo_id', $venta->id)->first();
    expect($log->contexto['baja_boleta'] ?? null)->toBeTrue();
});

it('si FacturaMac rechaza la baja no se toca nada: ni stock, ni caja, ni la venta', function () {
    $venta = ventaConBoleta($this);
    fingirFacturaMac($this, 409, [
        'error' => 'baja_no_permitida', 'mensaje' => 'La boleta B001-00000085 se emitió hace más de 7 días: anúlala con una nota de crédito.',
        'reintentable' => false,
    ]);
    $antes = stockBaja($this);

    $this->post(route('ventas.anular', $venta), ['motivo' => 'Se cobró dos veces por error', 'via' => 'baja'])
        ->assertSessionHasErrors('venta');

    expect(session('errors')->first('venta'))->toContain('anúlala con una nota de crédito')
        ->and($venta->fresh()->estado)->toBe('completada')
        ->and(stockBaja($this))->toBe($antes)
        ->and((float) $this->turno->fresh()->calcularMontoEsperado())->toBe((float) $this->turno->monto_apertura + 30)
        ->and($venta->comprobanteElectronico()->first()->estado)->toBe('pendiente_resumen');
    expect(Auditoria::where('accion', 'venta.anulada')->where('modelo_id', $venta->id)->exists())->toBeFalse();
});

it('si FacturaMac no responde tampoco se anula a medias', function () {
    $venta = ventaConBoleta($this);
    fingirFacturaMac($this, 503, ['error' => 'sunat_no_disponible', 'mensaje' => 'SUNAT no responde.', 'reintentable' => true]);
    $antes = stockBaja($this);

    $this->post(route('ventas.anular', $venta), ['motivo' => 'Se cobró dos veces por error', 'via' => 'baja'])
        ->assertSessionHasErrors('venta');

    expect($venta->fresh()->estado)->toBe('completada')->and(stockBaja($this))->toBe($antes);
});

it('sin elegir la baja, una boleta sin informar sigue sin poder anularse "a secas"', function () {
    $venta = ventaConBoleta($this);
    fingirFacturaMac($this);

    $this->post(route('ventas.anular', $venta), ['motivo' => 'Se cobró dos veces por error'])
        ->assertSessionHasErrors();

    expect($venta->fresh()->estado)->toBe('completada')->and($this->llamadasBaja)->toBeEmpty();
});

it('anular dos veces no devuelve el stock dos veces ni pide otra baja', function () {
    $venta = ventaConBoleta($this);
    fingirFacturaMac($this);
    $antes = stockBaja($this);

    $this->post(route('ventas.anular', $venta), ['motivo' => 'Se cobró dos veces por error', 'via' => 'baja'])->assertSessionHasNoErrors();
    $this->post(route('ventas.anular', $venta), ['motivo' => 'Se cobró dos veces por error', 'via' => 'baja'])->assertSessionHasErrors('venta');

    expect(stockBaja($this))->toBe($antes + 3)->and($this->llamadasBaja)->toHaveCount(1);
});

// ─── Boleta ya aceptada: las dos salidas ─────────────────────────────────────

it('una boleta aceptada ofrece las dos: baja o nota de crédito, y la baja no emite nota', function () {
    $venta = ventaConBoleta($this, 'aceptado');
    fingirFacturaMac($this);

    $this->get(route('ventas.show', $venta))->assertInertia(fn ($p) => $p
        ->where('bloqueoFiscal', null)
        ->where('anulacionBaja.bloqueo', null)
        ->where('anulacionNc.bloqueo', null));

    $this->post(route('ventas.anular', $venta), ['motivo' => 'Error al marcar la venta', 'via' => 'baja'])->assertSessionHasNoErrors();

    expect($venta->fresh()->estado)->toBe('anulada')->and(Devolucion::where('venta_id', $venta->id)->exists())->toBeFalse();
    Queue::assertNotPushed(EmitirNotaCreditoElectronica::class);
});

it('una boleta aceptada sin elegir sigue yendo por nota de crédito, como antes', function () {
    $venta = ventaConBoleta($this, 'aceptado');
    fingirFacturaMac($this);

    $this->post(route('ventas.anular', $venta), ['motivo' => 'El cliente devolvió todo'])->assertSessionHasNoErrors();

    expect(Devolucion::where('venta_id', $venta->id)->exists())->toBeTrue()->and($this->llamadasBaja)->toBeEmpty();
});

// ─── Lo que NO admite baja ───────────────────────────────────────────────────

it('una factura no se da de baja por resumen', function () {
    $venta = ventaConBoleta($this, 'aceptado', '01');
    fingirFacturaMac($this);

    expect(app(AnulacionConBajaBoleta::class)->plan($venta))->toBeNull();
    $this->post(route('ventas.anular', $venta), ['motivo' => 'Error al marcar la venta', 'via' => 'baja'])->assertSessionHasErrors('venta');
    expect($this->llamadasBaja)->toBeEmpty()->and($venta->fresh()->estado)->toBe('completada');
});

it('pasado el plazo de SUNAT ya no se ofrece la baja', function () {
    $venta = ventaConBoleta($this, 'aceptado');
    $venta->update(['fecha_venta' => now()->subDays(AnulacionConBajaBoleta::PLAZO_DIAS + 1)]);

    expect(app(AnulacionConBajaBoleta::class)->plan($venta->fresh()))->toBeNull();
});

it('con una devolución registrada no se da de baja (sería revertir dos veces)', function () {
    $venta = ventaConBoleta($this, 'aceptado');
    // Devolución parcial real (1 de 3), por el mismo servicio que usa la pantalla.
    $motivo = \App\Models\DevolucionMotivo::firstOrCreate(
        ['empresa_id' => $this->env->empresa->id, 'slug' => 'prueba_baja'],
        ['nombre' => 'Prueba', 'afecta_restock_default' => 'permite', 'es_sistema' => false, 'activo' => true, 'orden' => 0],
    );
    $dev = app(\App\Services\DevolucionService::class)->crear([
        'venta_id' => $venta->id, 'motivo_id' => $motivo->id, 'forma_reembolso' => 'sin_reembolso',
        'items' => [['venta_item_id' => $venta->items->first()->id, 'cantidad' => 1, 'estado_producto' => 'bueno', 'restock' => true]],
        'pagos' => [],
    ], $this->env->admin, $this->turno);
    fingirFacturaMac($this);

    expect(app(AnulacionConBajaBoleta::class)->plan($venta)['bloqueo'])->toContain($dev->numero);
    $this->post(route('ventas.anular', $venta), ['motivo' => 'Error al marcar la venta', 'via' => 'baja'])->assertSessionHasErrors('venta');
    expect($this->llamadasBaja)->toBeEmpty()->and($venta->fresh()->estado)->toBe('completada');
});

it('con la facturación desactivada no se puede comunicar la baja', function () {
    $venta = ventaConBoleta($this);
    $this->env->conectarFacturacion(['emision_activa' => false]);

    expect(app(AnulacionConBajaBoleta::class)->plan($venta)['bloqueo'])->toContain('desactivada');
});

// ─── Baja pedida desde el portal de FacturaMac ───────────────────────────────

it('si la boleta ya se anuló desde FacturaMac, aquí se completa: vuelve el stock y la caja', function () {
    $venta = ventaConBoleta($this, 'anulado');
    fingirFacturaMac($this, 200, ['estado' => 'anulado', 'id' => 777, 'reutilizado' => true]);
    $antes = stockBaja($this);

    $this->get(route('ventas.show', $venta))->assertInertia(fn ($p) => $p
        ->where('bloqueoFiscal', null)
        ->where('anulacionBaja.en_tramite', true));

    $this->post(route('ventas.anular', $venta), ['motivo' => 'Ya se anuló la boleta en FacturaMac', 'via' => 'baja'])->assertSessionHasNoErrors();

    expect($venta->fresh()->estado)->toBe('anulada')
        ->and(stockBaja($this))->toBe($antes + 3)
        ->and($venta->comprobanteElectronico()->first()->estado)->toBe('anulado');
    Queue::assertNotPushed(ConsultarEstadoComprobante::class);
});

// ─── Después de la baja ──────────────────────────────────────────────────────

it('una venta anulada por baja no se puede restablecer ni vuelve a ofrecer anular', function () {
    $venta = ventaConBoleta($this);
    fingirFacturaMac($this);
    $this->post(route('ventas.anular', $venta), ['motivo' => 'Se cobró dos veces por error', 'via' => 'baja'])->assertSessionHasNoErrors();

    $this->get(route('ventas.show', $venta))->assertInertia(fn ($p) => $p
        ->where('anulacionBaja', null)
        ->where('restablecer.bloqueo', fn ($m) => str_contains($m, 'comprobante')));
});

it('el seguimiento refleja cuando FacturaMac termina la baja', function () {
    $venta = ventaConBoleta($this, 'pendiente_anulacion');
    Http::fake(['*/api/v1/ventas/777' => Http::response(['estado' => 'anulado', 'id' => 777], 200)]);

    $ce = $venta->comprobanteElectronico()->first();
    (new ConsultarEstadoComprobante($ce->id, $this->env->empresa->id))->handle(app(\App\Services\Facturacion\FacturacionEmpresa::class));

    expect($ce->fresh()->estado)->toBe('anulado');
});

it('la lista marca qué boletas admiten la baja', function () {
    $venta = ventaConBoleta($this);
    $hoy = now()->toDateString();

    $fila = collect($this->get(route('ventas.index', ['fecha_desde' => $hoy, 'fecha_hasta' => $hoy]))
        ->viewData('page')['props']['ventas']['data'])->firstWhere('id', $venta->id);

    expect($fila['comprobante_electronico']['baja'])->toBeTrue()
        ->and($fila['comprobante_electronico']['emitido'])->toBeFalse();
});
