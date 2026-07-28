<?php

namespace App\Services\Facturacion;

use App\Exceptions\MapeoComprobanteException;
use App\Models\Venta;
use App\Models\VentaItem;

/**
 * Serializa una venta de ventoryPOS al payload de `POST /api/v2/ventas`.
 *
 * ─── Lo importante de esta clase es lo que NO hace ───────────────────────────────
 *
 * No calcula bases imponibles, no separa IGV, no redondea céntimos, no concilia
 * totales, no traduce unidades al Catálogo 03 ni tipos de documento al Catálogo 06.
 * Solo vuelca lo que la venta ya dice, en el idioma del POS.
 *
 * Toda esa traducción fiscal la hace ahora FacturaMac, que es quien tiene el
 * certificado, la clave SOL, la tasa vigente y las series. Antes vivía duplicada aquí
 * (ver VentaAComprobante, hoy @deprecated): dos implementaciones de la misma
 * aritmética tributaria, en dos repos, que había que mantener sincronizadas a mano.
 * La primera vez que divergieran, el POS habría mandado importes que el emisor
 * recalcula distinto — y el resultado es un documento fiscal irreversible.
 *
 * Que esta clase sea corta y tonta no es un accidente: es el objetivo.
 *
 * ─── Lo único que sí decide ──────────────────────────────────────────────────────
 *
 * Se niega a serializar lo que no debe emitirse (un `ticket`, una venta sin ítems o
 * sin cliente). Es una comprobación de existencia, no de aritmética: sin ella
 * mandaríamos a FacturaMac una petición que sabemos de antemano que va a fallar.
 *
 * @see \App\Services\Facturacion\VentaAComprobante  El mapper v1, que hacía TODO esto
 *      más la matemática. Se conserva mientras dure la transición.
 */
class VentaAPayloadV2
{
    /** Tipos del POS que sí generan comprobante. `ticket` es nota de venta interna. */
    private const TIPOS_EMITIBLES = ['boleta', 'factura'];

    /**
     * Tipo de documento que el POS usa para el "cliente de mostrador".
     * FacturaMac lo traduce al '0' del Catálogo 06 y decide si lo admite según el
     * importe; aquí solo declaramos el hecho: "esta venta no identifica al comprador".
     */
    private const DOC_GENERICO = 'generico';

    /**
     * @return array<string, mixed>
     *
     * @throws MapeoComprobanteException si la venta no puede generar comprobante.
     */
    public function mapear(Venta $venta): array
    {
        $tipo = strtolower((string) $venta->tipo_comprobante);

        if (! in_array($tipo, self::TIPOS_EMITIBLES, true)) {
            throw MapeoComprobanteException::para(
                $venta->id,
                "La venta es de tipo '{$tipo}' y no genera comprobante electrónico. "
                . 'Solo se emiten boleta y factura; el ticket es una nota de venta interna.',
                ['tipo_comprobante' => $tipo],
            );
        }

        return [
            // G6 — La clave viaja también en el header. Es lo único que impide que un
            // timeout de red convierta un reintento en un segundo comprobante real.
            'idempotency_key' => $venta->idempotency_key ?: "venta-{$venta->id}",

            // El POS habla su idioma: boleta/factura. La traducción al Catálogo 01
            // ('03'/'01') es cosa de FacturaMac.
            'tipo'            => $tipo,
            'fecha_emision'   => $this->fechaEmision($venta),

            // Con qué venta del POS se corresponde: es lo que permite cruzar una
            // observación de SUNAT con la caja del día sin adivinar.
            'referencia_externa' => (string) ($venta->numero ?: $venta->id),

            'moneda' => strtoupper(trim((string) ($venta->moneda ?: 'PEN'))) ?: 'PEN',

            // null = que FacturaMac use la serie por defecto del tipo. El POS ya no
            // opina sobre series: son del emisor, y elegirlas desde aquí fue
            // exactamente la duplicación que este refactor elimina.
            'serie'  => null,

            'cliente' => $this->cliente($venta),
            'items'   => $this->items($venta),

            // El descuento global viaja SIN prorratear. FacturaMac decide cómo lo
            // reparte entre las líneas para que el comprobante cuadre; el POS solo
            // declara cuánto descontó.
            'descuento_global'  => $this->numero($venta->descuento_total),

            'es_credito'        => (bool) $venta->es_credito,
            'fecha_vencimiento' => $venta->fecha_vencimiento?->format('Y-m-d'),

            // Contrato de verificación: el importe que el cliente pagó de verdad. Si
            // FacturaMac recalcula y no cuadra, rechaza SIN consumir correlativo. Es
            // la última red antes de que el documento se vuelva irreversible.
            'total' => $this->numero($venta->total),

            'observaciones' => $this->observaciones($venta),
        ];
    }

    // ── Cliente ──────────────────────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     *
     * @throws MapeoComprobanteException
     */
    private function cliente(Venta $venta): array
    {
        $cliente = $venta->cliente;

        if (! $cliente) {
            throw MapeoComprobanteException::para($venta->id, 'La venta no tiene cliente asociado.');
        }

        // `es_cliente_general` es el "clientes varios" de mostrador. No mandamos su
        // documento ficticio ('99999999'): mandamos el HECHO de que no está
        // identificado, y FacturaMac aplica la regla que corresponda al importe.
        $tipoDoc = $cliente->es_cliente_general
            ? self::DOC_GENERICO
            : trim((string) $cliente->tipo_documento);

        $numDoc = $cliente->es_cliente_general
            ? null
            : (trim((string) $cliente->numero_documento) ?: null);

        return [
            'tipo_documento'   => $tipoDoc !== '' ? $tipoDoc : self::DOC_GENERICO,
            'numero_documento' => $numDoc,
            'nombre'           => trim($cliente->getNombreCompletoAttribute()) ?: null,
            'direccion'        => trim((string) $cliente->direccion) ?: null,
            'email'            => trim((string) $cliente->email) ?: null,
        ];
    }

    // ── Ítems ────────────────────────────────────────────────────────────────────

    /**
     * @return list<array<string, mixed>>
     *
     * @throws MapeoComprobanteException
     */
    private function items(Venta $venta): array
    {
        $items = $venta->items;

        if ($items === null || count($items) === 0) {
            throw MapeoComprobanteException::para($venta->id, 'La venta no tiene ítems que facturar.');
        }

        $lineas = [];

        foreach ($items as $item) {
            /** @var VentaItem $item */
            $lineas[] = [
                'codigo'      => $item->producto?->codigo ?: ('P-' . $item->producto_id),
                'descripcion' => trim((string) $item->producto_nombre),
                'unidad'      => $this->abreviaturaDe($item),
                'cantidad'    => $this->numero($item->cantidad, 4),

                // El precio TAL COMO ESTÁ en la venta. Si `incluye_igv` es true, es
                // bruto (con IGV dentro); FacturaMac se encarga de desglosarlo.
                'precio_unitario' => $this->numero($item->precio_unitario, 6),

                // En ventoryPOS `incluye_igv` NO es "afecto/no afecto":
                //   true  = producto GRAVADO cuyo precio ya trae el IGV dentro.
                //   false = producto EXONERADO, que nunca paga IGV.
                // Está documentado así en Venta::calcularTotales(). Se manda el flag
                // crudo y FacturaMac decide la afectación del Catálogo 07.
                'incluye_igv'     => (bool) $item->incluye_igv,
                'descuento_item'  => $this->numero($item->descuento_item),
            ];
        }

        return $lineas;
    }

    /**
     * Abreviatura de la unidad tal como la escribió la empresa ("BOL"), que es lo que
     * FacturaMac espera para traducir al Catálogo 03.
     *
     * OJO con el fallback: `venta_items.unidad_nombre` guarda el NOMBRE de la unidad
     * ("Bolsa"), no la abreviatura — así lo persiste VentaService. Por eso se prefiere
     * la relación cuando está cargada y solo se cae al nombre cuando no lo está; ese
     * caso degrada a que FacturaMac no reconozca la unidad y use su default, nunca a
     * que la emisión falle.
     */
    private function abreviaturaDe(VentaItem $item): string
    {
        $abrev = $item->productoUnidad?->unidadMedida?->abreviatura;

        return trim((string) ($abrev ?: $item->unidad_nombre));
    }

    // ── Cabecera ─────────────────────────────────────────────────────────────────

    private function fechaEmision(Venta $venta): string
    {
        $fecha = $venta->fecha_venta ?? $venta->created_at;

        return $fecha ? $fecha->format('Y-m-d') : now()->format('Y-m-d');
    }

    private function observaciones(Venta $venta): ?string
    {
        $texto = trim("POS {$venta->numero} · Turno {$venta->turno_id}");

        return $texto !== '' ? mb_substr($texto, 0, 250) : null;
    }

    /**
     * Casteo a float con la precisión con la que el dato está guardado.
     *
     * No es aritmética fiscal: las columnas `decimal` llegan de Eloquent como string
     * ("118.00") y hay que mandarlas como número en el JSON. El redondeo solo evita
     * que la representación binaria del float meta un `0.30000000000000004` en el
     * payload; los importes ya vienen cuadrados de la venta.
     */
    private function numero(mixed $valor, int $decimales = 2): float
    {
        return round((float) $valor, $decimales);
    }
}
