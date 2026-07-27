<?php

use App\Exceptions\MapeoComprobanteException;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Producto;
use App\Models\ProductoUnidad;
use App\Models\UnidadMedida;
use App\Models\Venta;
use App\Models\VentaItem;
use App\Services\Facturacion\MapaUnidadesSunat;
use App\Services\Facturacion\VentaAComprobante;
use Illuminate\Database\Eloquent\Collection;

/**
 * Tests del mapper de emisión electrónica. SIN BASE DE DATOS: los modelos se arman en
 * memoria con `setRelation()`, que es justo lo que la pureza del mapper hace posible.
 *
 * Los `total` esperados de cada venta NO son inventados: son los que produce
 * `Venta::calcularTotales()` con esos mismos ítems (redondeo único del IGV a nivel de
 * venta). Ese es el contrato que el mapper tiene que respetar — el cliente pagó ese
 * importe y el comprobante tiene que decir exactamente eso.
 *
 * Se bootea la app de Laravel (no la BD) porque el mapper lee sus umbrales de
 * `config/facturamac.php`.
 */
uses(Tests\TestCase::class);

// ── Constructores en memoria ────────────────────────────────────────────────────

function fmEmpresa(float $tasaIgv = 18.0): Empresa
{
    $empresa = new Empresa(['razon_social' => 'Ferretería de prueba', 'tasa_igv' => $tasaIgv]);
    $empresa->id = 1;

    return $empresa;
}

function fmCliente(array $attrs = []): Cliente
{
    $cliente = new Cliente(array_merge([
        'empresa_id'         => 1,
        'tipo_documento'     => 'DNI',
        'numero_documento'   => '45678912',
        'nombres'            => 'Juan',
        'apellidos'          => 'Pérez',
        'direccion'          => 'Av. Siempre Viva 742',
        'es_cliente_general' => false,
    ], $attrs));
    $cliente->id = 10;

    return $cliente;
}

/** Cliente General: el "clientes varios" de mostrador, sin documento. */
function fmClienteGeneral(): Cliente
{
    $cliente = new Cliente([
        'empresa_id'         => 1,
        'nombres'            => 'Clientes varios',
        'apellidos'          => '',
        'es_cliente_general' => true,
    ]);
    $cliente->id = 99;

    return $cliente;
}

function fmItem(float $precio, float $cantidad, bool $incluyeIgv, array $attrs = []): VentaItem
{
    $item = new VentaItem(array_merge([
        'producto_id'        => 5,
        'producto_unidad_id' => 7,
        'producto_nombre'    => 'Cemento Sol 42.5kg',
        'unidad_nombre'      => 'Bolsa',
        'cantidad'           => $cantidad,
        'precio_unitario'    => $precio,
        'descuento_item'     => 0,
        'incluye_igv'        => $incluyeIgv,
    ], $attrs));

    $unidadMedida = new UnidadMedida(['nombre' => 'Bolsa', 'abreviatura' => 'BOL']);
    $unidadMedida->id = 3;

    $productoUnidad = new ProductoUnidad(['unidad_medida_id' => 3]);
    $productoUnidad->id = 7;
    $productoUnidad->setRelation('unidadMedida', $unidadMedida);

    $producto = new Producto(['codigo' => 'P-001', 'nombre' => 'Cemento Sol 42.5kg']);
    $producto->id = 5;

    $item->setRelation('productoUnidad', $productoUnidad);
    $item->setRelation('producto', $producto);

    return $item;
}

/**
 * @param list<VentaItem> $items
 */
function fmVenta(array $items, float $total, array $attrs = [], ?Cliente $cliente = null, ?Empresa $empresa = null): Venta
{
    $venta = new Venta(array_merge([
        'empresa_id'       => 1,
        'turno_id'         => 45,
        'numero'           => 'V-0123',
        'tipo_comprobante' => 'boleta',
        'descuento_total'  => 0,
        'moneda'           => 'PEN',
        'es_credito'       => false,
        'fecha_venta'      => '2026-07-27 10:15:00',
        'idempotency_key'  => 'idem-abc',
        'total'            => $total,
    ], $attrs));
    $venta->id = 1000;

    $venta->setRelation('empresa', $empresa ?? fmEmpresa());
    $venta->setRelation('cliente', $cliente ?? fmCliente());
    $venta->setRelation('items', new Collection($items));

    return $venta;
}

function fmMapper(): VentaAComprobante
{
    // Precargado en memoria: ni un solo acceso a BD en todo el test.
    $unidades = new MapaUnidadesSunat();
    $unidades->precargar(1, [3 => 'BG'], ['BOL' => 'BG']);

    return new VentaAComprobante($unidades);
}

// ── IGV por ítem (§5.2) ─────────────────────────────────────────────────────────

it('venta simple gravada: separa base e IGV y el total cuadra con la venta', function () {
    // 1 × S/ 118 con IGV incluido → base 100, IGV 18, total 118.
    $venta = fmVenta([fmItem(118, 1, true)], 118.00);

    $payload = fmMapper()->mapear($venta);

    expect($payload['detalles'])->toHaveCount(1);
    expect($payload['detalles'][0]['tipo_afectacion_igv'])->toBe('10');
    expect($payload['detalles'][0]['subtotal'])->toBe(100.00);
    expect($payload['detalles'][0]['igv_item'])->toBe(18.00);
    expect($payload['detalles'][0]['total_item'])->toBe(118.00);
    expect($payload['totales_esperados'])->toBe(['igv' => 18.00, 'total' => 118.00]);
});

it('producto exonerado (incluye_igv=false): afectación 20 e IGV cero', function () {
    // 3 × S/ 50 exonerado → 150 sin un céntimo de IGV.
    $venta = fmVenta([fmItem(50, 3, false)], 150.00);

    $payload = fmMapper()->mapear($venta);

    expect($payload['detalles'][0]['tipo_afectacion_igv'])->toBe('20');
    expect($payload['detalles'][0]['igv_item'])->toBe(0.00);
    expect($payload['detalles'][0]['subtotal'])->toBe(150.00);
    expect($payload['totales_esperados'])->toBe(['igv' => 0.00, 'total' => 150.00]);
});

it('venta mixta gravado + exonerado: cada base va por su lado', function () {
    // Gravado 118 (base 100 + IGV 18) + exonerado 150 → total 268.
    $venta = fmVenta([fmItem(118, 1, true), fmItem(50, 3, false)], 268.00);

    $payload = fmMapper()->mapear($venta);

    expect($payload['detalles'][0]['tipo_afectacion_igv'])->toBe('10');
    expect($payload['detalles'][0]['subtotal'])->toBe(100.00);
    expect($payload['detalles'][0]['igv_item'])->toBe(18.00);

    expect($payload['detalles'][1]['tipo_afectacion_igv'])->toBe('20');
    expect($payload['detalles'][1]['subtotal'])->toBe(150.00);
    expect($payload['detalles'][1]['igv_item'])->toBe(0.00);

    expect($payload['totales_esperados'])->toBe(['igv' => 18.00, 'total' => 268.00]);
});

// ── Descuento global prorrateado (G2) ───────────────────────────────────────────

it('descuento global: se prorratea por ítem y NUNCA viaja como campo global', function () {
    // Gravado 118 + exonerado 50 = 168 bruto, menos 10 de descuento.
    // calcularTotales(): base gravada 94.047619, IGV 16.93, exonerada 47.023810 → 158.00.
    $venta = fmVenta(
        [fmItem(118, 1, true), fmItem(50, 1, false)],
        158.00,
        ['descuento_total' => 10],
    );

    $resultado = fmMapper()->mapearConDiagnostico($venta);
    $payload = $resultado['payload'];

    // El descuento no puede aparecer como campo de cabecera: FacturaMac lo ignoraría
    // al sumar totales y el comprobante saldría inflado en esos 10 soles (G2).
    expect($payload)->not->toHaveKey('descuento_global');

    expect($payload['detalles'][0]['subtotal'])->toBe(94.05);   // 110.976190 / 1.18
    expect($payload['detalles'][0]['igv_item'])->toBe(16.93);
    expect($payload['detalles'][1]['subtotal'])->toBe(47.02);    // exonerado, 1:1

    $suma = array_sum(array_column($payload['detalles'], 'total_item'));
    expect(round($suma, 2))->toBe(158.00);
    expect($payload['totales_esperados']['total'])->toBe(158.00);
    expect($resultado['diagnostico']['delta'])->toBe(0.0);
});

// ── Conciliación de céntimos (§5.3) ─────────────────────────────────────────────

it('delta dentro de tolerancia: lo absorbe la línea gravada mayor y el total cuadra EXACTO', function () {
    // 3 líneas gravadas de S/ 10. Redondeo por ítem: 3 × (8.47 + 1.52) = 29.97.
    // Redondeo por venta (lo que pagó el cliente): 25.423729 + 4.58 = 30.00.
    // Delta 0.03 → dentro de los 0.05 de tolerancia.
    $venta = fmVenta([fmItem(10, 1, true), fmItem(10, 1, true), fmItem(10, 1, true)], 30.00);

    $resultado = fmMapper()->mapearConDiagnostico($venta);

    expect($resultado['diagnostico']['delta'])->toBe(0.03);
    expect($resultado['diagnostico']['ajustado'])->toBeTrue();

    $detalles = $resultado['payload']['detalles'];
    $total = round(array_sum(array_column($detalles, 'subtotal'))
                 + array_sum(array_column($detalles, 'igv_item')), 2);

    expect($total)->toBe(30.00);
    expect($resultado['payload']['totales_esperados']['total'])->toBe(30.00);

    // El ajuste cayó en una sola línea; las otras dos quedan intactas.
    expect($detalles[0]['subtotal'])->toBe(8.50);
    expect($detalles[1]['subtotal'])->toBe(8.47);
    expect($detalles[2]['subtotal'])->toBe(8.47);

    // SUNAT tolera hasta ±0.01 entre el IGV del ítem y base × tasa.
    foreach ($detalles as $d) {
        expect(abs($d['igv_item'] - round($d['subtotal'] * 0.18, 2)))->toBeLessThanOrEqual(0.0101);
    }
});

it('delta mayor que la tolerancia: NO se emite, se lanza excepción con las cifras', function () {
    // El ítem suma 118 pero la venta dice 118.50: eso no es redondeo, es un bug.
    $venta = fmVenta([fmItem(118, 1, true)], 118.50);

    expect(fn () => fmMapper()->mapear($venta))
        ->toThrow(MapeoComprobanteException::class);

    try {
        fmMapper()->mapear($venta);
    } catch (MapeoComprobanteException $e) {
        expect($e->getMessage())->toContain('118.50');
        expect($e->datos['delta'])->toBe(0.50);
        expect($e->datos['tolerancia'])->toBe(0.05);
    }
});

// ── Cliente (§5.4) ──────────────────────────────────────────────────────────────

it('factura sin RUC: se rechaza antes de emitir', function () {
    $venta = fmVenta(
        [fmItem(118, 1, true)],
        118.00,
        ['tipo_comprobante' => 'factura'],
        fmCliente(['tipo_documento' => 'DNI', 'numero_documento' => '45678912']),
    );

    expect(fn () => fmMapper()->mapear($venta))
        ->toThrow(MapeoComprobanteException::class, 'FACTURA a un cliente sin RUC');
});

it('factura con RUC y dirección: mapea el adquirente como tipo doc 6', function () {
    $venta = fmVenta(
        [fmItem(118, 1, true)],
        118.00,
        ['tipo_comprobante' => 'factura'],
        fmCliente([
            'tipo_documento'   => 'RUC',
            'numero_documento' => '20123456789',
            'razon_social'     => 'ACME SAC',
            'direccion'        => 'Av. Siempre Viva 742',
        ]),
    );

    $payload = fmMapper()->mapear($venta);

    expect($payload['tipo_comprobante'])->toBe('01');
    expect($payload['cliente'])->toBe([
        'tipo_doc'     => '6',
        'num_doc'      => '20123456789',
        'razon_social' => 'ACME SAC',
        'direccion'    => 'Av. Siempre Viva 742',
    ]);
});

it('boleta de S/ 700 o más sin cliente identificado: se rechaza', function () {
    // 1 × S/ 826 con IGV incluido → base 700, IGV 126, total 826.
    $venta = fmVenta(
        [fmItem(826, 1, true)],
        826.00,
        [],
        fmClienteGeneral(),
    );

    expect(fn () => fmMapper()->mapear($venta))
        ->toThrow(MapeoComprobanteException::class, 'exige identificar al cliente');
});

it('boleta por debajo del umbral con Cliente General: tipo doc 0 y num doc "-"', function () {
    $venta = fmVenta([fmItem(118, 1, true)], 118.00, [], fmClienteGeneral());

    $payload = fmMapper()->mapear($venta);

    expect($payload['tipo_comprobante'])->toBe('03');
    expect($payload['cliente']['tipo_doc'])->toBe('0');
    expect($payload['cliente']['num_doc'])->toBe('-');
    expect($payload['cliente']['razon_social'])->toBe('Clientes varios');
});

// ── Guardas duras (G4, G12) y tipo de comprobante (§5.1) ────────────────────────

it('empresa con tasa de IGV distinta de 18: aborta (G4)', function () {
    $venta = fmVenta([fmItem(110, 1, true)], 110.00, [], null, fmEmpresa(10.0));

    expect(fn () => fmMapper()->mapear($venta))
        ->toThrow(MapeoComprobanteException::class, 'solo está soportada al 18');
});

it('venta en USD: aborta, la v1 solo emite en PEN (G12)', function () {
    $venta = fmVenta([fmItem(118, 1, true)], 118.00, ['moneda' => 'USD']);

    expect(fn () => fmMapper()->mapear($venta))
        ->toThrow(MapeoComprobanteException::class, 'solo admite PEN');
});

it('venta tipo ticket: no genera comprobante electrónico', function () {
    $venta = fmVenta([fmItem(118, 1, true)], 118.00, ['tipo_comprobante' => 'ticket']);

    expect(fn () => fmMapper()->mapear($venta))
        ->toThrow(MapeoComprobanteException::class, 'no genera comprobante electrónico');
});

// ── Cabecera, unidades y forma de pago (§5.5, §5.6) ─────────────────────────────

it('unidad SUNAT del mapa y abreviatura original conservada en la descripción', function () {
    $venta = fmVenta([fmItem(118, 1, true)], 118.00);

    $payload = fmMapper()->mapear($venta);

    expect($payload['detalles'][0]['unidad_medida'])->toBe('BG');
    expect($payload['detalles'][0]['descripcion'])->toBe('Cemento Sol 42.5kg (BOL)');
    expect($payload['detalles'][0]['codigo_producto'])->toBe('P-001');
});

it('unidad no mapeada: cae a NIU sin romper la emisión', function () {
    $item = fmItem(118, 1, true);
    $item->productoUnidad->unidadMedida->abreviatura = 'XYZ';
    $item->productoUnidad->unidad_medida_id = 999;

    $payload = fmMapper()->mapear(fmVenta([$item], 118.00));

    expect($payload['detalles'][0]['unidad_medida'])->toBe('NIU');
    expect($payload['detalles'][0]['descripcion'])->toBe('Cemento Sol 42.5kg (XYZ)');
});

it('venta al contado: forma de pago Contado sin vencimiento', function () {
    $payload = fmMapper()->mapear(fmVenta([fmItem(118, 1, true)], 118.00));

    expect($payload['forma_pago'])->toBe('Contado');
    expect($payload['condicion_pago'])->toBeNull();
    expect($payload['fecha_vencimiento'])->toBeNull();
});

it('venta a crédito: forma de pago Credito con vencimiento y días', function () {
    $venta = fmVenta([fmItem(118, 1, true)], 118.00, [
        'es_credito'        => true,
        'fecha_vencimiento' => '2026-08-26',
    ]);

    $payload = fmMapper()->mapear($venta);

    expect($payload['forma_pago'])->toBe('Credito');
    expect($payload['fecha_vencimiento'])->toBe('2026-08-26');
    expect($payload['condicion_pago'])->toBe('Crédito a 30 días');
});

it('cabecera: serie por tipo, fecha de emisión, observaciones e idempotencia', function () {
    $payload = fmMapper()->mapear(fmVenta([fmItem(118, 1, true)], 118.00));

    expect($payload['serie'])->toBe(config('facturamac.series.03'));
    expect($payload['fecha_emision'])->toBe('2026-07-27');
    expect($payload['moneda'])->toBe('PEN');
    expect($payload['observaciones'])->toBe('POS V-0123 · Turno 45');
    expect($payload['idempotency_key'])->toBe('idem-abc');
});

it('venta sin idempotency_key: se deriva del id para que el reintento siga siendo seguro', function () {
    $payload = fmMapper()->mapear(fmVenta([fmItem(118, 1, true)], 118.00, ['idempotency_key' => null]));

    expect($payload['idempotency_key'])->toBe('venta-1000');
});
