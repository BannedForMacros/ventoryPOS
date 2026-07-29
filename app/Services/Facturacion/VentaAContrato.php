<?php

namespace App\Services\Facturacion;

use App\Models\Venta;
use App\Models\VentaItem;
use DateTimeImmutable;
use MacSoft\Facturacion\Contrato\Dto\Cliente as ClienteContrato;
use MacSoft\Facturacion\Contrato\Dto\Documento;
use MacSoft\Facturacion\Contrato\Dto\Item;
use MacSoft\Facturacion\Contrato\Dto\Pago;
use MacSoft\Facturacion\Contrato\Dto\Venta as VentaContrato;
use MacSoft\Facturacion\Contrato\Enum\CondicionPago;
use MacSoft\Facturacion\Contrato\Enum\Impuesto;
use MacSoft\Facturacion\Contrato\Enum\TipoComprobante;
use MacSoft\Facturacion\Contrato\Enum\TipoDocumento;
use MacSoft\Facturacion\Contrato\Excepcion\ContratoInvalidoException;

/**
 * Vuelca una venta de ventoryPOS al contrato común de emisión.
 *
 * ─── Lo importante es lo que NO hace ─────────────────────────────────────────────
 *
 * Ni una operación de IGV, ni un redondeo de céntimos, ni un catálogo de SUNAT, ni un
 * umbral. Todo eso lo hace FacturaMac, que es quien tiene el certificado, la clave SOL,
 * la tasa vigente y las series. Mientras esa aritmética vivió duplicada aquí (ver
 * VentaAComprobante), eran dos implementaciones de la misma ley tributaria en dos
 * repositorios: la primera vez que divergieran, el POS habría declarado importes que el
 * emisor recalcula distinto, sobre un documento fiscal irreversible.
 *
 * Que esta clase sea corta y tonta es el objetivo, no un descuido.
 *
 * ─── Lo único que sí decide ──────────────────────────────────────────────────────
 *
 * De DÓNDE sale cada dato de la venta. Eso sigue siendo conocimiento de ventoryPOS y
 * no puede vivir en el emisor. El resto de la validación la hacen los DTOs al
 * construirse: se dejan fallar a propósito, porque descubrir un dato malo aquí es
 * mucho más barato que descubrirlo en el rechazo de SUNAT.
 */
class VentaAContrato
{
    /** Tipos del POS que sí generan comprobante. `ticket` es nota de venta interna. */
    private const TIPOS = [
        'boleta'  => TipoComprobante::BOLETA,
        'factura' => TipoComprobante::FACTURA,
    ];

    /**
     * @throws ContratoInvalidoException si la venta no puede emitirse.
     */
    public function mapear(Venta $venta): VentaContrato
    {
        return new VentaContrato(
            idempotencyKey: $venta->idempotency_key ?: "venta-{$venta->id}",

            // `Venta::generarNumero()` numera DENTRO del turno (la restricción es
            // `ventas_turno_id_numero_unique`), así que «V-0004» existe una vez por
            // turno. Mandar el número a secas haría que la segunda venta con ese
            // número se tomara por duplicada y NO se emitiera nunca. La forma
            // compuesta hereda esa misma unicidad y además un humano la rastrea.
            referenciaExterna: "T{$venta->turno_id}-{$venta->numero}",

            cliente: $this->cliente($venta),
            items: array_map($this->item(...), $venta->items?->all() ?? []),
            total: (float) $venta->total,
            tipo: $this->tipo($venta),
            fechaEmision: $this->fechaEmision($venta),

            // Sin `moneda`: el POS puede COBRAR en dólares, pero `ventas.total`
            // siempre está contabilizado en soles al tipo de cambio congelado, y ese
            // es el importe que se declara. Pasar la moneda del cobro emitiría un
            // total en PEN etiquetado como USD.

            // Sin `serie`: las series son del emisor. Elegirlas desde aquí fue
            // exactamente la duplicación que este refactor elimina.

            // El descuento global viaja SIN prorratear: FacturaMac decide cómo lo
            // reparte entre líneas para que el comprobante cuadre.
            descuentoGlobal: (float) $venta->descuento_total,

            pago: $this->pago($venta),
            observaciones: "POS {$venta->numero} · Turno {$venta->turno_id}",
        );
    }

    /** @throws ContratoInvalidoException */
    private function tipo(Venta $venta): TipoComprobante
    {
        $tipo = strtolower(trim((string) $venta->tipo_comprobante));

        return self::TIPOS[$tipo] ?? throw ContratoInvalidoException::campo(
            'tipo',
            "La venta es de tipo '{$tipo}' y no genera comprobante electrónico. "
            . 'Solo se emiten boleta y factura; el ticket es una nota de venta interna.',
        );
    }

    /** @throws ContratoInvalidoException */
    private function cliente(Venta $venta): ClienteContrato
    {
        $cliente = $venta->cliente ?? throw ContratoInvalidoException::campo(
            'cliente',
            'La venta no tiene cliente asociado.',
        );

        // El "clientes varios" de mostrador no manda documento ficticio: manda el
        // HECHO de que el comprador no está identificado. Si eso es admisible o no
        // depende del umbral legal, y el umbral lo conoce el emisor.
        $documento = $cliente->es_cliente_general
            ? Documento::sinDocumento()
            : new Documento(
                $this->tipoDocumento($cliente->tipo_documento),
                trim((string) $cliente->numero_documento) ?: null,
            );

        return new ClienteContrato(
            documento: $documento,
            nombre: trim((string) $cliente->nombre_completo),
            direccion: trim((string) $cliente->direccion) ?: null,
            email: trim((string) $cliente->email) ?: null,
        );
    }

    /**
     * El POS guarda 'DNI', 'RUC', 'CE' y 'pasaporte'; el contrato nombra los mismos
     * cuatro en mayúsculas. No hay tabla de traducción porque no hace falta: los
     * códigos del Catálogo 06 son cosa del emisor.
     *
     * @throws ContratoInvalidoException
     */
    private function tipoDocumento(?string $tipo): TipoDocumento
    {
        return TipoDocumento::tryFrom(strtoupper(trim((string) $tipo)))
            ?? throw ContratoInvalidoException::campo(
                'cliente.documento.tipo',
                "El cliente tiene el tipo de documento '{$tipo}', que no existe en el contrato.",
            );
    }

    private function item(VentaItem $item): Item
    {
        return new Item(
            descripcion: trim((string) $item->producto_nombre),
            cantidad: (float) $item->cantidad,
            precioUnitario: (float) $item->precio_unitario,
            codigo: $item->producto?->codigo ?: null,
            unidad: $this->unidad($item),

            // AQUÍ se traduce la peculiaridad de ventoryPOS: su `incluye_igv` mezcla
            // dos preguntas que el contrato separa. Como flag de tributación, `false`
            // significa EXONERADO (medicamentos, ciertos alimentos), no "no lleva el
            // IGV dentro". Está documentado así en `Venta::calcularTotales()`.
            impuesto: $item->incluye_igv ? Impuesto::GRAVADO : Impuesto::EXONERADO,

            // Y como flag de precio, en ventoryPOS SIEMPRE es cierto: el precio de la
            // etiqueta es el que paga el cliente, gravado o exonerado.
            precioIncluyeImpuesto: true,

            // `descuento_item` es POR UNIDAD en el POS y el contrato lo espera POR
            // LÍNEA (así lo reconstruye `VentaAComprobante::construirLineas()`, que
            // calcula el bruto como (precio − descuento) × cantidad).
            descuento: round((float) $item->descuento_item * (float) $item->cantidad, 2),
        );
    }

    /**
     * Abreviatura tal como la escribió la empresa ("BOL"): es texto libre para el
     * emisor, que la mapea al Catálogo 03 con su propia tabla.
     *
     * OJO con el respaldo: `venta_items.unidad_nombre` guarda el NOMBRE ("Bolsa"), no
     * la abreviatura — así lo persiste VentaService —, por eso se prefiere la relación
     * cuando está cargada. El peor caso es que el emisor no reconozca la unidad y use
     * su default con un aviso; nunca que la emisión falle.
     */
    private function unidad(VentaItem $item): string
    {
        $abrev = trim((string) ($item->productoUnidad?->unidadMedida?->abreviatura ?: $item->unidad_nombre));

        return $abrev !== '' ? $abrev : 'UND';
    }

    private function fechaEmision(Venta $venta): ?DateTimeImmutable
    {
        $fecha = $venta->fecha_venta ?? $venta->created_at;

        return $fecha ? new DateTimeImmutable($fecha->format('Y-m-d')) : null;
    }

    private function pago(Venta $venta): Pago
    {
        if (! $venta->es_credito) {
            return Pago::contado();
        }

        $vence = $venta->fecha_vencimiento;

        // Sin vencimiento construimos igualmente el crédito para que sea el DTO quien
        // lance señalando `pago.vence`: un mensaje con el campo culpable vale más que
        // un TypeError de `Pago::credito()`.
        return $vence
            ? Pago::credito(new DateTimeImmutable($vence->format('Y-m-d')))
            : new Pago(CondicionPago::CREDITO);
    }
}
