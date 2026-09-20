<?php

use App\Models\ConexionFacturacion;
use App\Models\Venta;
use App\Models\VentaComprobante;
use App\Services\VentaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Support\TestEnv;

/**
 * Política de ventas: qué se puede anular o editar y qué ya no.
 *
 * Complementa a GuardaVentaEmitidaTest, que cubre los estados del comprobante.
 * Aquí van los tres casos que ese no veía y que dejaban pasar una anulación
 * sobre algo que SUNAT ya conoce —o que está a punto de conocer—:
 *
 *   1. `pendiente` con la emisión ENCENDIDA. El contrato lo deja fuera de los
 *      que bloquean, y tiene razón: "creado en el emisor, todavía sin salir".
 *      Pero entre que la venta se registra y el job la manda hay segundos, y
 *      anular en ese hueco no cancela el envío: SUNAT termina con un documento
 *      de una venta que aquí ya no existe. Con la emisión apagada no hay carrera
 *      que perder, así que ahí NO se bloquea.
 *
 *   2. Una guía de remisión que ya autoriza el traslado es un documento fiscal
 *      igual que la factura: anular la venta la dejaría amparando el viaje de
 *      una mercadería que la empresa dice no haber vendido.
 *
 *   3. Las facturas y boletas cargadas a mano NO bloquean —decisión del
 *      negocio—, pero avisan: su nota de crédito se emite por fuera.
 */

beforeEach(function () {
    Http::fake(['*/api/v1/configuracion' => Http::response([
        'modo' => 'produccion', 'emision_activa' => true, 'envia_a_sunat' => true,
        'umbral_boleta_identificada' => 700.0,
    ], 200)]);

    $this->env     = TestEnv::crear(['modo_cierre_caja' => 'rapido']);
    $this->service = app(VentaService::class);
    $this->actingAs($this->env->admin);

    $this->producto = $this->env->crearProducto([
        'precio_venta' => 10, 'precio_costo' => 6, 'stock_inicial' => 100,
    ]);
    $this->efectivo = $this->env->metodo('efectivo');
    $this->turno    = $this->env->abrirTurno($this->env->admin);
});

/** Deja la empresa emitiendo de verdad (producción + token + interruptor). */
function emisionEncendida(): void
{
    ConexionFacturacion::updateOrCreate(
        ['empresa_id' => test()->env->empresa->id],
        [
            'token'               => 'token-de-prueba',
            'ruc_emisor'          => test()->env->empresa->ruc,
            'razon_social_emisor' => test()->env->empresa->razon_social,
            'modo'                => 'produccion',
            'emision_activa'      => true,
        ],
    );
}

function ventaDePrueba(string $tipo = 'boleta', ?string $numeroExterno = null): Venta
{
    $venta = test()->service->crear([
        'tipo_comprobante'   => $tipo,
        'numero_comprobante' => $numeroExterno,
        'items' => [[
            'producto_id'        => test()->producto->id,
            'producto_unidad_id' => test()->producto->unidadBase->id,
            'cantidad'           => 2,
            'precio_unitario'    => 10,
        ]],
        'pagos' => [['metodo_pago_id' => test()->efectivo->id, 'monto' => 20]],
    ], test()->env->admin, test()->turno);

    return $venta->fresh();
}

function conComprobante(Venta $venta, string $estado): Venta
{
    VentaComprobante::create([
        'venta_id'    => $venta->id,
        'tipo'        => '03',
        'serie'       => 'B002',
        'correlativo' => 77,
        'numero'      => 'B002-00000077',
        'estado'      => $estado,
    ]);

    return $venta->fresh();
}

it('NO deja anular mientras el comprobante se está enviando a SUNAT', function () {
    emisionEncendida();
    $venta = conComprobante(ventaDePrueba(), 'pendiente');

    $this->post(route('ventas.anular', $venta), [
        'motivo' => 'La cajera se equivocó de cliente',
    ])->assertStatus(422);

    expect($venta->fresh()->estado)->toBe('completada');
});

it('SÍ deja anular un comprobante pendiente si la empresa no emite', function () {
    // Contraprueba: sin emisión encendida ese comprobante no va a salir nunca
    // hacia SUNAT, así que no hay carrera y bloquear sería estorbar.
    $venta = conComprobante(ventaDePrueba(), 'pendiente');

    $this->post(route('ventas.anular', $venta), [
        'motivo' => 'La cajera se equivocó de cliente',
    ])->assertSessionHasNoErrors();

    expect($venta->fresh()->estado)->toBe('anulada');
});

it('NO deja anular una venta con guía de remisión que autoriza el traslado', function () {
    $venta = ventaDePrueba('ticket');

    DB::table('guias')->insert([
        'empresa_id'      => $this->env->empresa->id,
        'venta_id'        => $venta->id,
        'numero'          => 'T001-00000012',
        'estado'          => 'aceptado',
        'puede_trasladar' => true,
        'created_at'      => now(),
        'updated_at'      => now(),
    ]);

    $this->post(route('ventas.anular', $venta), [
        'motivo' => 'El cliente ya no quiere la mercadería',
    ])->assertStatus(422);

    expect($venta->fresh()->estado)->toBe('completada');
});

it('SÍ deja anular si la guía todavía no autoriza ningún traslado', function () {
    // Se mira `puede_trasladar`, no el estado: una guía que no habilita el viaje
    // no ampara nada, y bloquear por ella dejaría ventas muertas cada vez que una
    // guía sale rechazada.
    $venta = ventaDePrueba('ticket');

    DB::table('guias')->insert([
        'empresa_id'      => $this->env->empresa->id,
        'venta_id'        => $venta->id,
        'numero'          => 'T001-00000013',
        'estado'          => 'rechazado',
        'puede_trasladar' => false,
        'created_at'      => now(),
        'updated_at'      => now(),
    ]);

    $this->post(route('ventas.anular', $venta), [
        'motivo' => 'SUNAT rechazó la guía y se rehará todo',
    ])->assertSessionHasNoErrors();

    expect($venta->fresh()->estado)->toBe('anulada');
});

it('una factura cargada a mano NO bloquea, pero avisa de que su nota de crédito va por fuera', function () {
    $venta = ventaDePrueba('factura_externa', 'F001-000123');

    // El aviso existe…
    $aviso = VentaService::avisoComprobanteExterno($venta);
    expect($aviso)->toContain('F001-000123')
        ->and($aviso)->toContain('nota de crédito');

    // …y aun así la anulación pasa: es un aviso, no una guarda.
    $this->post(route('ventas.anular', $venta), [
        'motivo' => 'Se facturó por error a este cliente',
    ])->assertSessionHasNoErrors();

    expect($venta->fresh()->estado)->toBe('anulada');
});

it('una venta normal no trae aviso de comprobante externo', function () {
    expect(VentaService::avisoComprobanteExterno(ventaDePrueba('ticket')))->toBeNull();
});

it('la pantalla recibe el mismo motivo que corta en el servidor', function () {
    // El punto de todo esto: una sola fuente. Si la pantalla dedujera el bloqueo
    // por su cuenta, las dos versiones acabarían discrepando.
    $venta = conComprobante(ventaDePrueba(), 'aceptado');

    $motivo = app(VentaService::class)->motivoBloqueoFiscal($venta);
    expect($motivo)->toContain('B002-00000077');

    $this->get(route('ventas.show', $venta))
        ->assertInertia(fn ($page) => $page->where('bloqueoFiscal', $motivo));
});
