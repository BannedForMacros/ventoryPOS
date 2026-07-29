<?php

use App\Models\Cliente;
use App\Models\Producto;
use App\Models\ProductoUnidad;
use App\Models\UnidadMedida;
use App\Models\Venta;
use App\Models\VentaItem;
use App\Services\Facturacion\VentaAContrato;
use Illuminate\Database\Eloquent\Collection;
use MacSoft\Facturacion\Contrato\Enum\CondicionPago;
use MacSoft\Facturacion\Contrato\Enum\Impuesto;
use MacSoft\Facturacion\Contrato\Enum\TipoComprobante;
use MacSoft\Facturacion\Contrato\Enum\TipoDocumento;
use MacSoft\Facturacion\Contrato\Excepcion\ContratoInvalidoException;

/**
 * Tests del adaptador al contrato común. SIN BASE DE DATOS: los modelos se arman en
 * memoria con `setRelation()`.
 *
 * Lo que se comprueba aquí NO son importes calculados —el adaptador no calcula nada—
 * sino que cada dato de la venta acabe en el campo correcto del contrato. Las dos
 * traducciones que sí son decisiones del POS (el doble sentido de `incluye_igv` y la
 * unicidad de la referencia externa) tienen test propio.
 */
uses(Tests\TestCase::class);

// ── Constructores en memoria ────────────────────────────────────────────────────

function vcCliente(array $attrs = []): Cliente
{
    $cliente = new Cliente(array_merge([
        'empresa_id'         => 1,
        'tipo_documento'     => 'DNI',
        'numero_documento'   => '45678912',
        'nombres'            => 'Juan',
        'apellidos'          => 'Pérez',
        'direccion'          => 'Av. Siempre Viva 742',
        'email'              => 'juan@example.com',
        'es_cliente_general' => false,
    ], $attrs));
    $cliente->id = 10;

    return $cliente;
}

/** El "clientes varios" de mostrador: sin documento, por definición. */
function vcClienteGeneral(): Cliente
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

/**
 * @param array{unidad?: ?UnidadMedida} $attrs  `unidad` a null = relación sin cargar,
 *                                              que es el caso del respaldo a `unidad_nombre`.
 */
function vcItem(float $precio, float $cantidad, bool $incluyeIgv, array $attrs = [], bool $conUnidad = true): VentaItem
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

    $productoUnidad = new ProductoUnidad(['unidad_medida_id' => 3]);
    $productoUnidad->id = 7;

    if ($conUnidad) {
        $unidadMedida = new UnidadMedida(['nombre' => 'Bolsa', 'abreviatura' => 'BOL']);
        $unidadMedida->id = 3;
        $productoUnidad->setRelation('unidadMedida', $unidadMedida);
    } else {
        $productoUnidad->setRelation('unidadMedida', null);
    }

    $producto = new Producto(['codigo' => 'P-001', 'nombre' => 'Cemento Sol 42.5kg']);
    $producto->id = 5;

    $item->setRelation('productoUnidad', $productoUnidad);
    $item->setRelation('producto', $producto);

    return $item;
}

/** @param list<VentaItem> $items */
function vcVenta(array $items, float $total, array $attrs = [], ?Cliente $cliente = null): Venta
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

    $venta->setRelation('cliente', $cliente ?? vcCliente());
    $venta->setRelation('items', new Collection($items));

    return $venta;
}

function vcMapper(): VentaAContrato
{
    return new VentaAContrato();
}

// ── Volcado básico ──────────────────────────────────────────────────────────────

it('vuelca una venta gravada simple al contrato sin tocar los importes', function () {
    // 10 bolsas a S/ 25.00 con IGV dentro. El adaptador NO desglosa nada: manda el
    // precio de la etiqueta y deja que el emisor haga la aritmética fiscal.
    $venta = vcVenta([vcItem(25.00, 10, true)], 250.00);

    $dto = vcMapper()->mapear($venta);

    expect($dto->idempotencyKey)->toBe('idem-abc')
        ->and($dto->referenciaExterna)->toBe('T45-V-0123')
        ->and($dto->tipo)->toBe(TipoComprobante::BOLETA)
        ->and($dto->total)->toBe(250.00)
        ->and($dto->moneda)->toBe('PEN')
        ->and($dto->serie)->toBeNull()
        ->and($dto->descuentoGlobal)->toBe(0.0)
        ->and($dto->fechaEmision?->format('Y-m-d'))->toBe('2026-07-27')
        ->and($dto->observaciones)->toBe('POS V-0123 · Turno 45')
        ->and($dto->items)->toHaveCount(1);

    $item = $dto->items[0];

    expect($item->descripcion)->toBe('Cemento Sol 42.5kg')
        ->and($item->codigo)->toBe('P-001')
        ->and($item->cantidad)->toBe(10.0)
        ->and($item->precioUnitario)->toBe(25.00)
        ->and($item->impuesto)->toBe(Impuesto::GRAVADO)
        // Innegociable en ventoryPOS: sus precios son brutos, siempre.
        ->and($item->precioIncluyeImpuesto)->toBeTrue()
        ->and($item->descuento)->toBe(0.0);

    // Y el cliente, con su documento nombrado (no el '1' del catálogo 06).
    expect($dto->cliente->documento->tipo)->toBe(TipoDocumento::DNI)
        ->and($dto->cliente->documento->numero)->toBe('45678912')
        ->and($dto->cliente->nombre)->toBe('Juan Pérez')
        ->and($dto->cliente->direccion)->toBe('Av. Siempre Viva 742');
});

it('sin idempotency_key deriva la clave del id, para que el reintento siga siendo seguro', function () {
    $dto = vcMapper()->mapear(vcVenta([vcItem(10.00, 1, true)], 10.00, ['idempotency_key' => null]));

    expect($dto->idempotencyKey)->toBe('venta-1000');
});

// ── La peculiaridad de `incluye_igv` ────────────────────────────────────────────

it('traduce incluye_igv=false a EXONERADO, no a "precio sin impuesto"', function () {
    // Este es EL test de la traducción: en ventoryPOS `incluye_igv` mezcla dos
    // conceptos que el contrato separa. Como tributación, false = exonerado; el
    // precio sigue siendo el que paga el cliente.
    $dto = vcMapper()->mapear(vcVenta([vcItem(12.50, 4, false)], 50.00));

    expect($dto->items[0]->impuesto)->toBe(Impuesto::EXONERADO)
        ->and($dto->items[0]->precioIncluyeImpuesto)->toBeTrue();
});

it('convierte el descuento de línea: el POS lo guarda por unidad y el contrato lo espera por línea', function () {
    // 3 unidades a S/ 20.00 con S/ 2.00 de descuento CADA UNA → S/ 6.00 en la línea.
    // Es el mismo criterio con el que Venta::calcularTotales() calcula el bruto.
    $dto = vcMapper()->mapear(vcVenta([vcItem(20.00, 3, true, ['descuento_item' => 2.00])], 54.00));

    expect($dto->items[0]->descuento)->toBe(6.00)
        ->and($dto->items[0]->precioUnitario)->toBe(20.00);
});

// ── Cliente y tipo de comprobante ───────────────────────────────────────────────

it('manda SIN_DOCUMENTO para el cliente general, no un documento ficticio', function () {
    $dto = vcMapper()->mapear(vcVenta([vcItem(15.00, 2, true)], 30.00, [], vcClienteGeneral()));

    expect($dto->cliente->documento->tipo)->toBe(TipoDocumento::SIN_DOCUMENTO)
        ->and($dto->cliente->documento->numero)->toBeNull()
        ->and($dto->cliente->nombre)->toBe('Clientes varios');
});

it('emite FACTURA cuando la venta lo es y el cliente tiene RUC y dirección', function () {
    $cliente = vcCliente([
        'tipo_documento'   => 'RUC',
        'numero_documento' => '20601030013',
        'nombres'          => null,
        'apellidos'        => null,
        'razon_social'     => 'ACME SAC',
    ]);

    $dto = vcMapper()->mapear(vcVenta(
        [vcItem(100.00, 1, true)],
        100.00,
        ['tipo_comprobante' => 'factura'],
        $cliente,
    ));

    expect($dto->tipo)->toBe(TipoComprobante::FACTURA)
        ->and($dto->cliente->documento->tipo)->toBe(TipoDocumento::RUC)
        ->and($dto->cliente->nombre)->toBe('ACME SAC');
});

it('deja que el contrato rechace una factura sin RUC antes de quemar un correlativo', function () {
    // No lo valida el adaptador: lo valida el DTO al construirse. Fallar aquí es
    // gratis; fallar en el CDR de SUNAT ya cuesta una nota de crédito.
    $venta = vcVenta([vcItem(100.00, 1, true)], 100.00, ['tipo_comprobante' => 'factura']);

    expect(fn () => vcMapper()->mapear($venta))->toThrow(ContratoInvalidoException::class);
});

it('se niega a mapear un ticket: es una nota de venta interna, no un comprobante', function () {
    $venta = vcVenta([vcItem(10.00, 1, true)], 10.00, ['tipo_comprobante' => 'ticket']);

    expect(fn () => vcMapper()->mapear($venta))
        ->toThrow(ContratoInvalidoException::class, "La venta es de tipo 'ticket'");
});

// ── Referencia externa: la unicidad es por TURNO, no global ─────────────────────

it('compone la referencia externa como T{turno}-{numero}', function () {
    $dto = vcMapper()->mapear(vcVenta([vcItem(10.00, 1, true)], 10.00, [
        'turno_id' => 45,
        'numero'   => 'V-0004',
    ]));

    expect($dto->referenciaExterna)->toBe('T45-V-0004');
});

it('da referencias DISTINTAS a dos ventas con el mismo número en turnos distintos', function () {
    // `Venta::generarNumero()` numera dentro del turno: «V-0004» existe una vez por
    // turno. Si la referencia fuera solo el número, la segunda venta se tomaría por
    // duplicada y NO se emitiría nunca. Este test es la guarda contra eso.
    $mapper = vcMapper();

    $unaDelTurno45 = $mapper->mapear(vcVenta([vcItem(10.00, 1, true)], 10.00, [
        'turno_id' => 45, 'numero' => 'V-0004', 'idempotency_key' => 'a',
    ]));

    $otraDelTurno46 = $mapper->mapear(vcVenta([vcItem(10.00, 1, true)], 10.00, [
        'turno_id' => 46, 'numero' => 'V-0004', 'idempotency_key' => 'b',
    ]));

    expect($unaDelTurno45->referenciaExterna)->toBe('T45-V-0004')
        ->and($otraDelTurno46->referenciaExterna)->toBe('T46-V-0004')
        ->and($unaDelTurno45->referenciaExterna)->not->toBe($otraDelTurno46->referenciaExterna);
});

// ── Pago ────────────────────────────────────────────────────────────────────────

it('marca contado cuando la venta no es a crédito', function () {
    $dto = vcMapper()->mapear(vcVenta([vcItem(10.00, 1, true)], 10.00));

    expect($dto->pago?->condicion)->toBe(CondicionPago::CONTADO)
        ->and($dto->pago?->vence)->toBeNull();
});

it('manda el vencimiento pactado en una venta a crédito', function () {
    $dto = vcMapper()->mapear(vcVenta([vcItem(500.00, 1, true)], 500.00, [
        'es_credito'        => true,
        'fecha_vencimiento' => '2026-08-27',
    ]));

    expect($dto->pago?->condicion)->toBe(CondicionPago::CREDITO)
        ->and($dto->pago?->vence?->format('Y-m-d'))->toBe('2026-08-27');
});

it('rechaza el crédito sin fecha de vencimiento señalando el campo', function () {
    $venta = vcVenta([vcItem(500.00, 1, true)], 500.00, [
        'es_credito' => true, 'fecha_vencimiento' => null,
    ]);

    expect(fn () => vcMapper()->mapear($venta))->toThrow(ContratoInvalidoException::class);
});

// ── Unidad ──────────────────────────────────────────────────────────────────────

it('manda la abreviatura de la unidad, no su nombre', function () {
    $dto = vcMapper()->mapear(vcVenta([vcItem(10.00, 1, true)], 10.00));

    expect($dto->items[0]->unidad)->toBe('BOL');
});

it('cae a unidad_nombre cuando la relación de unidad no está cargada', function () {
    // Degradar a que el emisor no reconozca la unidad (y avise) es aceptable; que la
    // emisión falle por una unidad, no.
    $item = vcItem(10.00, 1, true, [], conUnidad: false);

    expect(vcMapper()->mapear(vcVenta([$item], 10.00))->items[0]->unidad)->toBe('Bolsa');
});

it('usa UND cuando la venta no dice nada de la unidad', function () {
    $item = vcItem(10.00, 1, true, ['unidad_nombre' => null], conUnidad: false);

    expect(vcMapper()->mapear(vcVenta([$item], 10.00))->items[0]->unidad)->toBe('UND');
});

// ── Serialización ───────────────────────────────────────────────────────────────

it('serializa al payload del contrato con nombres, no con códigos de SUNAT', function () {
    $payload = vcMapper()->mapear(vcVenta([vcItem(25.00, 2, true)], 50.00))->aArray();

    expect($payload['referencia_externa'])->toBe('T45-V-0123')
        ->and($payload['tipo'])->toBe('boleta')
        ->and($payload['cliente']['documento']['tipo'])->toBe('DNI')
        ->and($payload['items'][0]['impuesto'])->toBe('gravado')
        ->and($payload['items'][0]['precio_incluye_impuesto'])->toBeTrue()
        ->and($payload['pago']['condicion'])->toBe('contado')
        // Sin `serie`: las series son del emisor.
        ->and($payload)->not->toHaveKey('serie');
});
