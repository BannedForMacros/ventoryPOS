<?php

use App\Services\VentaService;
use Tests\Support\TestEnv;

/**
 * El efectivo esperado del turno debe contar lo que QUEDA en el cajón:
 * si el cliente paga S/ 20 una compra de S/ 18, entran S/ 18 (se le dan S/ 2 de vuelto).
 */
beforeEach(function () {
    $this->env = TestEnv::crear();
    $this->turno = $this->env->abrirTurno(apertura: 100.0);
    $this->actingAs($this->env->admin);
});

it('no cuenta el vuelto como efectivo que quedó en el cajón', function () {
    $producto = $this->env->crearProducto(['precio_venta' => 18, 'precio_costo' => 10, 'stock_inicial' => 10]);

    app(VentaService::class)->crear([
        'tipo_comprobante' => 'ticket',
        'items' => [[
            'producto_id'        => $producto->id,
            'producto_unidad_id' => $producto->unidadBase->id,
            'cantidad'           => 1,
            'precio_unitario'    => 18,
        ]],
        'pagos' => [['metodo_pago_id' => $this->env->metodo('efectivo')->id, 'monto' => 20]],
    ], $this->env->admin, $this->turno);

    // 100 de apertura + 18 que quedaron (20 recibidos − 2 de vuelto).
    expect($this->turno->fresh()->calcularMontoEsperado())->toBe(118.0);
});
