<?php

use App\Jobs\EmitirNotaCreditoElectronica;
use App\Models\Devolucion;
use App\Models\Venta;
use App\Models\VentaComprobante;
use App\Services\VentaService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Support\TestEnv;

/**
 * Que una nota de crédito a medias NO quede invisible.
 *
 * EL PROBLEMA QUE RESUELVE: la NC se emite en segundo plano y puede agotar sus
 * reintentos. Cuando eso pasaba, la devolución quedaba hecha —el stock volvió y
 * el dinero salió, y así debe ser— pero SUNAT seguía viendo declarado el importe
 * original de la venta, y el único rastro era una línea en el log y otra en la
 * auditoría: había que ir a buscarlas sabiendo qué buscar. Nadie lo sabía.
 *
 * LA REGLA DE ORO NO CAMBIA y estos tests la protegen: pase lo que pase con la
 * nota de crédito, la devolución NO se revierte.
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

/** Una devolución cualquiera, con el estado de NC que se quiera probar. */
function devolucionConNc(?string $estadoNc, ?string $error = null): Devolucion
{
    $venta = test()->service->crear([
        'tipo_comprobante' => 'boleta',
        'items' => [[
            'producto_id'        => test()->producto->id,
            'producto_unidad_id' => test()->producto->unidadBase->id,
            'cantidad'           => 2,
            'precio_unitario'    => 10,
        ]],
        'pagos' => [['metodo_pago_id' => test()->efectivo->id, 'monto' => 20]],
    ], test()->env->admin, test()->turno);

    $devolucion = Devolucion::create([
        'empresa_id'       => test()->env->empresa->id,
        'local_id'         => test()->env->local->id,
        'venta_id'         => $venta->id,
        'user_id'          => test()->env->admin->id,
        'numero'           => 'DEV-TEST-' . $venta->id,
        'fecha'            => now(),
        'motivo_id'        => \App\Models\DevolucionMotivo::deEmpresa(test()->env->empresa->id)->value('id'),
        'forma_reembolso'  => 'efectivo',
        'monto_devolucion' => 20,
        'monto_reembolso'  => 20,
        'estado'           => 'completada',
    ]);

    if ($estadoNc !== null) {
        $devolucion->anotarNotaCredito($estadoNc, $error);
    }

    return $devolucion->fresh();
}

it('anota "no aplica" cuando la venta no tenía comprobante en SUNAT', function () {
    // Un ticket no se declara, así que no hay nada que acreditar. Que se DIGA es
    // lo que distingue "no hacía falta" de "se perdió por el camino": sin anotarlo,
    // las dos se ven igual.
    $devolucion = devolucionConNc(null);
    (new EmitirNotaCreditoElectronica($devolucion->id, $this->env->empresa->id))
        ->handle(app(\App\Services\Facturacion\FacturacionEmpresa::class));

    expect($devolucion->fresh()->nota_credito_estado)->toBe(Devolucion::NC_NO_APLICA);
});

it('una nota de crédito fallida queda marcada, con su motivo y sin tocar la devolución', function () {
    $devolucion = devolucionConNc(Devolucion::NC_FALLIDA, 'SUNAT rechazó el documento');

    expect($devolucion->nota_credito_estado)->toBe('fallida')
        ->and($devolucion->nota_credito_error)->toBe('SUNAT rechazó el documento')
        ->and($devolucion->notaCreditoSinCerrar())->toBeTrue()
        // LA REGLA DE ORO: la devolución sigue completa y su importe intacto.
        ->and($devolucion->estado)->toBe('completada')
        ->and((float) $devolucion->monto_devolucion)->toBe(20.0);
});

it('al emitirse borra el error anterior: no puede seguir pareciendo rota', function () {
    $devolucion = devolucionConNc(Devolucion::NC_FALLIDA, 'se cayó la conexión');

    $devolucion->anotarNotaCredito(Devolucion::NC_EMITIDA, null, [
        'nota_credito_numero' => 'BC02-00000009',
    ]);

    $devolucion->refresh();
    expect($devolucion->nota_credito_estado)->toBe('emitida')
        ->and($devolucion->nota_credito_error)->toBeNull()
        ->and($devolucion->nota_credito_numero)->toBe('BC02-00000009')
        ->and($devolucion->notaCreditoSinCerrar())->toBeFalse();
});

it('esperar no es fallar: una NC en espera no pide que nadie haga nada', function () {
    // Una boleta vive en `pendiente_resumen` hasta las 23:55, así que casi toda
    // devolución del día pasa por aquí. Si esto se contara como fallo, el aviso
    // rojo saldría todos los días y se volvería ruido.
    $devolucion = devolucionConNc(Devolucion::NC_ESPERANDO);

    expect($devolucion->nota_credito_estado)->toBe('esperando')
        ->and($devolucion->nota_credito_error)->toBeNull();
});

it('el botón de reintentar vuelve a encolar la nota de crédito', function () {
    Queue::fake();
    $devolucion = devolucionConNc(Devolucion::NC_FALLIDA, 'timeout');

    $this->post(route('devoluciones.nota-credito.reintentar', $devolucion))
        ->assertSessionHasNoErrors();

    Queue::assertPushed(EmitirNotaCreditoElectronica::class);
    expect($devolucion->fresh()->nota_credito_estado)->toBe(Devolucion::NC_PENDIENTE);
});

it('no se reintenta una nota de crédito que ya está emitida', function () {
    Queue::fake();
    $devolucion = devolucionConNc(Devolucion::NC_EMITIDA);

    $this->post(route('devoluciones.nota-credito.reintentar', $devolucion));

    Queue::assertNothingPushed();
    expect($devolucion->fresh()->nota_credito_estado)->toBe(Devolucion::NC_EMITIDA);
});

it('la lista avisa de las notas de crédito que quedaron sin emitir', function () {
    devolucionConNc(Devolucion::NC_FALLIDA, 'error');
    devolucionConNc(Devolucion::NC_EMITIDA);      // esta no cuenta
    devolucionConNc(Devolucion::NC_ESPERANDO);    // esta tampoco: no es un fallo

    $this->get(route('devoluciones.index'))
        ->assertInertia(fn ($page) => $page->where('ncFallidas', 1));
});
