<?php

use App\Models\Venta;
use App\Models\VentaComprobante;
use App\Services\VentaService;
use Illuminate\Support\Facades\Http;
use Tests\Support\TestEnv;

/**
 * La guarda que impide anular o editar en el POS una venta que SUNAT ya conoce.
 *
 * EL CASO QUE ESTABA ROTO: se acepta la nota de crédito de una venta, el emisor
 * pone el comprobante original en `anulado`, el polling copia ese estado al POS…
 * y como `anulado` no figuraba en la lista de "ya informado a SUNAT",
 * `esEmitido()` pasaba a devolver false. A partir de ese momento el POS dejaba
 * anular o editar la venta otra vez: doble reversión de stock y de caja sobre una
 * operación que ya tenía DOS documentos fiscales irreversibles (la factura y su
 * nota de crédito), y unos importes editados que ya no se parecían a ninguno.
 *
 * NOTA PARA QUIEN EDITE ESTE ARCHIVO: aquí NO va `uses(Tests\TestCase::class)`;
 * `tests/Pest.php` ya lo asocia a toda la carpeta `Feature`.
 */

beforeEach(function () {
    // StoreVentaRequest lee el umbral del emisor (§7). Se falsea la respuesta para
    // que el test no dependa de que haya un FacturaMac escuchando en localhost.
    Http::fake(['*/api/v1/configuracion' => Http::response([
        'modo' => 'beta', 'emision_activa' => true, 'envia_a_sunat' => true,
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

/** Venta simple de 2 x S/ 10, ya cerrada. */
function ventaConCpeEnEstado(string $estado): Venta
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

    VentaComprobante::create([
        'venta_id'    => $venta->id,
        'tipo'        => '03',
        'serie'       => 'B002',
        'correlativo' => 45,
        'numero'      => 'B002-00000045',
        'estado'      => $estado,
    ]);

    return $venta->fresh();
}

it('NO deja anular una venta cuyo comprobante quedó anulado ante SUNAT', function () {
    $venta = ventaConCpeEnEstado('anulado');

    $this->post(route('ventas.anular', $venta), [
        'motivo' => 'El cliente devolvió toda la mercadería',
    ])->assertStatus(422);

    // Y el stock NO se movió por segunda vez: la venta descontó 2 de 100 y ahí sigue.
    expect($venta->fresh()->estado)->toBe('completada');
});

it('NO deja editar una venta cuyo comprobante quedó anulado ante SUNAT', function () {
    $venta = ventaConCpeEnEstado('anulado');

    $this->put(route('ventas.update', $venta), [
        'tipo_comprobante' => 'boleta',
        'items' => [[
            'producto_id'        => $this->producto->id,
            'producto_unidad_id' => $this->producto->unidadBase->id,
            'cantidad'           => 9,          // importes que ya no cuadrarían con el CPE
            'precio_unitario'    => 10,
            'incluye_igv'        => true,
        ]],
        'pagos' => [['metodo_pago_id' => $this->efectivo->id, 'monto' => 90]],
    ])->assertStatus(422);

    expect((float) $venta->fresh()->total)->toBe(20.0);
});

it('sí deja anular cuando el comprobante fue rechazado: SUNAT no llegó a conocerlo', function () {
    // Contraprueba: la guarda tiene que bloquear lo informado y SOLO lo informado.
    // Si bloqueara de más, cada rechazo dejaría una venta imposible de corregir.
    $venta = ventaConCpeEnEstado('rechazado');

    $this->post(route('ventas.anular', $venta), [
        'motivo' => 'SUNAT rechazó el comprobante y se rehará la venta',
    ])->assertSessionHasNoErrors();

    expect($venta->fresh()->estado)->toBe('anulada');
});

it('esEmitido() cubre toda la tabla §9 del contrato', function () {
    // Prueba del modelo, sin HTTP: es la función que gobierna las dos guardas.
    $estado = fn (string $e) => (new VentaComprobante(['estado' => $e]))->esEmitido();

    expect($estado('anulado'))->toBeTrue();              // el defecto corregido
    expect($estado('aceptado'))->toBeTrue();
    expect($estado('enviando'))->toBeTrue();
    expect($estado('enviado'))->toBeTrue();
    expect($estado('pendiente_resumen'))->toBeTrue();
    expect($estado('en_resumen'))->toBeTrue();
    expect($estado('pendiente_anulacion'))->toBeTrue();

    expect($estado('pendiente'))->toBeFalse();
    expect($estado('rechazado'))->toBeFalse();
    expect($estado('error_envio'))->toBeFalse();
    expect($estado('simulado'))->toBeFalse();
    expect($estado('error_mapeo'))->toBeFalse();
    expect($estado('no_emitido'))->toBeFalse();
});
