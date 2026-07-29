<?php

use App\Services\VentaService;
use Tests\Support\TestEnv;

beforeEach(function () {
    $this->env    = TestEnv::crear();
    $this->turno  = $this->env->abrirTurno();
    $this->ventas = app(VentaService::class);
    $this->actingAs($this->env->admin);
});

/**
 * Helper local: ejecuta una venta con los items dados y devuelve el modelo Venta.
 * Asume que el pago cubre el total exacto en efectivo.
 */
function ventaConItems(TestEnv $env, $turno, array $items, ?float $descuentoTotal = null): \App\Models\Venta
{
    $subtotalPagar = 0;
    foreach ($items as $i) {
        $subtotalPagar += ((float) $i['precio_unitario'] - (float) ($i['descuento_item'] ?? 0)) * (float) $i['cantidad'];
    }
    // El monto que se debe pagar es el total real, lo recomputaremos después
    // de crear la venta. Aquí pagamos suficiente y dejamos efectivo asumir el vuelto.
    $pago = $subtotalPagar - ($descuentoTotal ?? 0);

    $payload = [
        'tipo_comprobante' => 'ticket',
        'items'            => $items,
        'pagos' => [[
            'metodo_pago_id' => $env->metodo('efectivo')->id,
            'monto'          => max($pago, 0.01),
        ]],
    ];
    if ($descuentoTotal !== null) {
        $payload['descuento_total'] = $descuentoTotal;
    }
    return app(VentaService::class)->crear($payload, $env->admin, $turno);
}

it('venta 100% gravada (incluye_igv=true): base = precio/1.18, igv = base*0.18, total = precio', function () {
    $producto = $this->env->crearProducto(['precio_venta' => 118, 'incluye_igv' => true]);
    // Venta de 1 unidad a 118 (precio bruto que ya incluye IGV)

    $venta = ventaConItems($this->env, $this->turno, [[
        'producto_id'        => $producto->id,
        'producto_unidad_id' => $producto->unidadBase->id,
        'cantidad'           => 1,
        'precio_unitario'    => 118,
    ]]);

    // Base gravada = 118 / 1.18 = 100; IGV = 18; total = 118
    expect((float) $venta->igv)->toBe(18.0);
    expect((float) $venta->total)->toBe(118.0);
});

it('venta 100% exonerada (incluye_igv=false): igv=0, total=precio*cantidad', function () {
    $producto = $this->env->crearProducto(['precio_venta' => 50, 'incluye_igv' => false]);

    $venta = ventaConItems($this->env, $this->turno, [[
        'producto_id'        => $producto->id,
        'producto_unidad_id' => $producto->unidadBase->id,
        'cantidad'           => 3,
        'precio_unitario'    => 50,
    ]]);

    expect((float) $venta->igv)->toBe(0.0);
    expect((float) $venta->total)->toBe(150.0);
});

it('venta MIXTA gravada + exonerada: separa correctamente las bases', function () {
    $gravado   = $this->env->crearProducto(['precio_venta' => 118, 'incluye_igv' => true]);
    $exonerado = $this->env->crearProducto(['precio_venta' => 50,  'incluye_igv' => false]);

    $venta = ventaConItems($this->env, $this->turno, [
        [
            'producto_id'        => $gravado->id,
            'producto_unidad_id' => $gravado->unidadBase->id,
            'cantidad'           => 1,
            'precio_unitario'    => 118,
        ],
        [
            'producto_id'        => $exonerado->id,
            'producto_unidad_id' => $exonerado->unidadBase->id,
            'cantidad'           => 2,
            'precio_unitario'    => 50,
        ],
    ]);

    // Gravada: 118/1.18 = 100, IGV = 18
    // Exonerada: 50*2 = 100
    // Total: 100 + 18 + 100 = 218
    expect((float) $venta->igv)->toBe(18.0);
    expect((float) $venta->total)->toBe(218.0);
});

it('descuento global se prorratea entre base gravada y exonerada', function () {
    $gravado   = $this->env->crearProducto(['precio_venta' => 118, 'incluye_igv' => true]);
    $exonerado = $this->env->crearProducto(['precio_venta' => 100, 'incluye_igv' => false]);

    // `descuento_total` está en SOLES BRUTOS (IGV incluido): es lo que el cajero
    // quiere que baje el total que paga el cliente. Venta::calcularTotales() lo
    // prorratea por el BRUTO de cada base y convierte a neto la parte gravada
    // ANTES de restarla, para que el total baje EXACTAMENTE el descuento tecleado.
    //   bruto gravado = 100 × 1.18 = 118 ; exonerado = 100 ; bruto total = 218
    //   desc gravado bruto = 20 × 118/218 = 10.825688 → neto ÷1.18 = 9.174312
    //   desc exonerado     = 20 × 100/218 =  9.174312
    //   base gravada final = 100 − 9.174312 = 90.825688 → IGV = 16.35
    //   base exonerada final = 90.825688
    //   total = 90.825688 + 16.35 + 90.825688 = 198.00  (= 218 − 20, exacto)
    // Mismo criterio que StoreVentaRequest::calcularTotalEsperado(),
    // VentaAComprobante::construirLineas() y el espejo TS de Pos/Index.tsx.

    $venta = ventaConItems($this->env, $this->turno, [
        [
            'producto_id'        => $gravado->id,
            'producto_unidad_id' => $gravado->unidadBase->id,
            'cantidad'           => 1,
            'precio_unitario'    => 118,
        ],
        [
            'producto_id'        => $exonerado->id,
            'producto_unidad_id' => $exonerado->unidadBase->id,
            'cantidad'           => 1,
            'precio_unitario'    => 100,
        ],
    ], descuentoTotal: 20);

    expect((float) $venta->igv)->toBe(16.35);
    expect((float) $venta->total)->toBe(198.00);
});

it('tasa_igv configurable: 10% calcula correctamente', function () {
    $this->env->empresa->update(['tasa_igv' => 10.00]);

    $producto = $this->env->crearProducto(['precio_venta' => 110, 'incluye_igv' => true]);

    $venta = ventaConItems($this->env, $this->turno, [[
        'producto_id'        => $producto->id,
        'producto_unidad_id' => $producto->unidadBase->id,
        'cantidad'           => 1,
        'precio_unitario'    => 110,
    ]]);

    // Base = 110 / 1.10 = 100, IGV = 10
    expect((float) $venta->igv)->toBe(10.0);
    expect((float) $venta->total)->toBe(110.0);
});

it('tasa_igv=0 (exenta): IGV siempre 0', function () {
    $this->env->empresa->update(['tasa_igv' => 0.00]);

    $producto = $this->env->crearProducto(['precio_venta' => 100, 'incluye_igv' => true]);

    $venta = ventaConItems($this->env, $this->turno, [[
        'producto_id'        => $producto->id,
        'producto_unidad_id' => $producto->unidadBase->id,
        'cantidad'           => 1,
        'precio_unitario'    => 100,
    ]]);

    expect((float) $venta->igv)->toBe(0.0);
    expect((float) $venta->total)->toBe(100.0);
});
