<?php

namespace App\Jobs;

use App\Exceptions\MapeoComprobanteException;
use App\Models\Devolucion;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaComprobante;
use App\Models\VentaItem;
use App\Services\AuditoriaService;
use App\Services\Facturacion\FacturaMacClient;
use App\Services\Facturacion\FacturaMacException;
use MacSoft\Facturacion\Contrato\Enum\Impuesto;
use MacSoft\Facturacion\Contrato\Enum\MotivoNotaCredito;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * V15 — Nota de Crédito por una devolución sobre una venta YA informada a SUNAT.
 *
 * POR QUÉ: cuando el comprobante está aceptado (o camino a estarlo), el importe
 * ya está declarado. Devolver mercadería sin emitir NC deja la venta declarada
 * por un monto que el cliente no pagó. Localmente no se puede "deshacer": el
 * único instrumento legal es la Nota de Crédito.
 *
 * REGLA DE ORO (la razón de que esto sea un job y no parte de la transacción):
 * si la NC falla, la devolución NO se revierte. El stock volvió al almacén y el
 * dinero salió de la caja — eso ya ocurrió y es correcto. Un fallo de SUNAT no
 * puede dejar al cliente sin su plata en el mostrador. Se registra el error, se
 * audita y el admin lo resuelve después.
 */
class EmitirNotaCreditoElectronica implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    public int $timeout = 60;

    public array $backoff = [30, 120, 600];

    /**
     * Tope de esperas por "el comprobante todavía no llegó a SUNAT". Con la
     * cadencia de abajo cubre algo más de 2 días, de sobra para que el Resumen
     * Diario de las 23:55 se envíe y SUNAT devuelva el CDR incluso si un día
     * falla. Pasado ese punto se avisa al admin en vez de reintentar en vano.
     */
    private const MAX_ESPERAS = 48;

    public function __construct(
        public int $devolucionId,
        public ?int $userId = null,
        /** Cuántas veces se pospuso ya por comprobante aún no informado a SUNAT. */
        public int $esperas = 0,
    ) {}

    public function handle(FacturaMacClient $client): void
    {
        if (!config('facturamac.enabled')) {
            return;
        }

        // Se cargan las relaciones que necesita la aritmética de la NC: los ítems de
        // la venta (para el prorrateo del descuento global y el criterio de IGV) y la
        // unidad de cada línea (Catálogo 03). Sin esto cada detalle dispararía su
        // propia consulta y, peor, `incluye_igv` no estaría disponible.
        $devolucion = Devolucion::with([
            'venta.items',
            'venta.empresa',
            'detalles.ventaItem.producto',
            'detalles.ventaItem.productoUnidad.unidadMedida',
        ])->find($this->devolucionId);

        if (!$devolucion || !$devolucion->venta) {
            return;
        }

        // Una devolución rechazada/anulada no genera NC.
        if (!in_array($devolucion->estado, ['pendiente', 'aprobada', 'completada'], true)) {
            return;
        }

        $venta = $devolucion->venta;
        $ce    = $venta->comprobanteElectronico()->first();

        // Sin comprobante informado a SUNAT no hay nada que acreditar: la venta
        // era un `ticket` (nota interna) o su emisión falló. En ambos casos la
        // devolución se queda como está, que es exactamente lo correcto.
        if (!$ce || !$ce->esEmitido() || !$ce->facturamac_id) {
            return;
        }

        // SUNAT solo admite acreditar lo que ya conoce. Una BOLETA vive en
        // `pendiente_resumen` hasta que el Resumen Diario sale a las 23:55, así
        // que la inmensa mayoría de devoluciones del día caen aquí. Fallar sería
        // garantizar que ninguna boleta devuelta genere jamás su nota de crédito;
        // lo correcto es ESPERAR a que el comprobante llegue a SUNAT.
        //
        // Se despacha una instancia NUEVA en vez de usar release(): release()
        // incrementa attempts() y agotaría los $tries, que deben quedar
        // reservados para los fallos de red. La espera no es un fallo.
        if (! in_array($ce->estado, VentaComprobante::ESTADOS_ACREDITABLES, true)) {
            if ($this->esperas >= self::MAX_ESPERAS) {
                $this->avisarFallo($devolucion, sprintf(
                    'El comprobante %s lleva demasiado tiempo en estado "%s" y no se pudo emitir '
                    . 'la nota de crédito. Revisa el envío a SUNAT y emítela manualmente.',
                    $ce->numero,
                    $ce->estado,
                ));

                return;
            }

            $esperaMinutos = $ce->estado === 'pendiente_resumen' ? 60 : 5;

            Log::info('Nota de crédito en espera: el comprobante aún no llegó a SUNAT.', [
                'devolucion_id' => $devolucion->id,
                'comprobante'   => $ce->numero,
                'estado'        => $ce->estado,
                'espera'        => ($this->esperas + 1) . '/' . self::MAX_ESPERAS,
                'reintento_en'  => $esperaMinutos . ' min',
            ]);

            self::dispatch($this->devolucionId, $this->userId, $this->esperas + 1)
                ->delay(now()->addMinutes($esperaMinutos));

            return;
        }

        $esTotal = $this->esDevolucionTotal($devolucion, $venta);

        $peticion = [
            // Idempotencia: una devolución = UNA nota de crédito, por más
            // veces que se reintente este job.
            'idempotency_key' => 'devolucion-' . $devolucion->id,
            // El contrato usa NOMBRES, no códigos de catálogo. Que `devolucion`
            // se traduzca a 06 (total) o 07 (por ítem) del catálogo 09 lo decide
            // el emisor a partir de si mandamos líneas o no: el POS no tiene por
            // qué conocer los códigos de SUNAT.
            'motivo'          => MotivoNotaCredito::DEVOLUCION->value,
            'observaciones'   => sprintf(
                'Devolución %s — venta %s',
                $devolucion->numero ?: ('#' . $devolucion->id),
                $venta->numero,
            ),
        ];

        // DEVOLUCIÓN PARCIAL ('07'): la NC debe acreditar SOLO lo devuelto, así que
        // viajan sus líneas y su importe. Sin ellas FacturaMac copiaría los totales
        // del comprobante original y una devolución de 1 saco de 10 emitiría una NC
        // por la factura entera — un error fiscal que solo se corrige con otra NC.
        //
        // DEVOLUCIÓN TOTAL ('06'): NO se mandan líneas a propósito. La NC tiene que
        // acreditar el comprobante ÍNTEGRO, y la forma de garantizarlo al céntimo es
        // que FacturaMac copie los importes que ya declaró: nuestro recálculo podría
        // desviarse un céntimo del original, porque aquel pasó por la conciliación de
        // VentaAComprobante y este no.
        if (! $esTotal) {
            try {
                $items = $this->items($devolucion, $venta);
            } catch (MapeoComprobanteException $e) {
                // Mejor no emitir que emitir una NC con importes equivocados.
                $this->avisarFallo($devolucion, $e->getMessage());

                return;
            }

            $peticion['items'] = $items;
            // Contrato de verificación, el equivalente de `total` en la emisión: si
            // el emisor recalcula las líneas y no coincide con esto, devuelve 422 y
            // no emite nada. Las líneas viajan con el precio BRUTO, así que el total
            // es la suma directa.
            $peticion['total'] = round(
                array_sum(array_map(
                    static fn (array $i): float => $i['precio_unitario'] * $i['cantidad'],
                    $items,
                )),
                2,
            );
        }

        try {
            $respuesta = $client->notaCredito((int) $ce->facturamac_id, $peticion);
        } catch (FacturaMacException $e) {
            if ($e->reintentable) {
                throw $e;
            }

            // 422: FacturaMac/SUNAT rechazan la NC por contenido. Reintentar da
            // lo mismo; hay que avisar a una persona.
            $this->avisarFallo($devolucion, $e->getMessage());

            return;
        }

        Log::info('Nota de crédito electrónica emitida', [
            'devolucion_id'  => $devolucion->id,
            'venta_id'       => $venta->id,
            'comprobante'    => $ce->numero,
            'alcance'        => $esTotal ? 'total' : 'parcial',
            'nota_credito'   => $respuesta['numero'] ?? null,
        ]);

        AuditoriaService::log('venta_comprobante.nota_credito_emitida', $devolucion, [
            'venta_id'          => $venta->id,
            'comprobante'       => $ce->numero,
            'alcance'           => $esTotal ? 'total' : 'parcial',
            'nota_credito'      => $respuesta['numero'] ?? null,
            'nota_credito_id'   => $respuesta['id'] ?? null,
            'monto'             => round((float) $devolucion->monto_devolucion, 2),
            // Lo realmente acreditado ante SUNAT. En una devolución parcial NO tiene
            // por qué coincidir con `monto_devolucion`: la NC descuenta la parte
            // proporcional del descuento global de la venta. En una total va a null
            // porque la NC copia los importes del comprobante original.
            'monto_acreditado'  => $peticion['total'] ?? null,
        ], $this->usuario());
    }

    /**
     * ¿Esta devolución acredita el comprobante ENTERO?
     *
     * Determina si la nota viaja SIN líneas (el emisor copia los importes que ya
     * declaró, garantizando que cuadre al céntimo) o CON ellas (acredita solo lo
     * devuelto). El código del catálogo 09 lo elige el emisor a partir de eso.
     *
     * Solo es total cuando ESTA devolución, por sí sola, devuelve todos los ítems de la
     * venta en su totalidad — porque es la única situación en la que la NC acredita el
     * comprobante ENTERO. No basta con que la suma de todas las devoluciones cubra la
     * venta: si el cliente devolvió 5 sacos ayer y 5 hoy, la NC de hoy acredita media
     * factura, y declararla como "devolución total" haría que el motivo dijera una
     * cosa y los importes otra.
     */
    private function esDevolucionTotal(Devolucion $devolucion, Venta $venta): bool
    {
        $items = $venta->items;

        if ($items === null || count($items) === 0) {
            return false;
        }

        // Cantidad devuelta en ESTA devolución, agrupada por línea de venta.
        $devuelto = [];
        foreach ($devolucion->detalles as $d) {
            $devuelto[$d->venta_item_id] = ($devuelto[$d->venta_item_id] ?? 0.0) + (float) $d->cantidad;
        }

        foreach ($items as $item) {
            // 0.0001 es la precisión con la que se guardan las cantidades; por debajo
            // de eso es ruido de coma flotante, no una unidad pendiente.
            if (($devuelto[$item->id] ?? 0.0) < (float) $item->cantidad - 0.0001) {
                return false;
            }
        }

        return true;
    }

    /**
     * Líneas devueltas en el formato EXACTO que valida FacturaMac
     * (`StoreComprobanteApiRequest::reglasDetalles()`), para que acredite solo lo que
     * volvió al almacén.
     *
     * La aritmética replica la de `VentaAComprobante` línea por línea, y eso no es
     * opcional: si la NC calculara el IGV con otro criterio que el comprobante
     * original, los importes acreditados no serían un subconjunto de los declarados y
     * quedaría un residuo imposible de cuadrar ante SUNAT. En concreto:
     *
     *  · `incluye_igv = true`  → GRAVADO ('10'): el precio del POS ya trae el IGV, así
     *    que la base se obtiene dividiendo entre (1 + tasa).
     *  · `incluye_igv = false` → EXONERADO ('20'): base = importe, igv_item = 0.
     *  · El descuento global de la venta se prorratea por el bruto de la línea, igual
     *    que en el comprobante original, y NUNCA viaja como campo aparte (G2).
     *
     * @return list<array<string, mixed>>
     *
     * @throws MapeoComprobanteException
     */
    /**
     * Líneas de la nota de crédito, en el vocabulario del CONTRATO.
     *
     * Antes esta función hacía la traducción fiscal: dividía entre (1+tasa), calculaba
     * el IGV por línea y elegía el código del catálogo 07. Todo eso vive ahora en el
     * emisor, que es quien conoce la tasa de la empresa y los catálogos vigentes.
     * Aquí solo se declara el hecho comercial: qué se devolvió, cuánto y a qué precio.
     *
     * `devoluciones_detalle.subtotal` ya es (precio − descuento_item) × cantidad
     * devuelta: el mismo bruto que usa Venta::calcularTotales(). De ahí se deriva el
     * precio unitario bruto, que es lo que el contrato espera con
     * `precio_incluye_impuesto = true`.
     */
    private function items(Devolucion $devolucion, Venta $venta): array
    {
        // El descuento global de la venta rebajó el precio efectivo de cada línea. Se
        // incorpora al precio unitario en vez de viajar aparte: la nota de crédito no
        // tiene campo de descuento global, y mandarlo como descuento de línea lo
        // restaría dos veces.
        $factor = $this->factorDescuentoGlobal($venta);
        $items  = [];

        foreach ($devolucion->detalles as $d) {
            $cantidad = round((float) $d->cantidad, 4);

            if ($cantidad <= 0) {
                continue; // una línea sin unidades devueltas no acredita nada
            }

            $item = $d->ventaItem;

            if (! $item) {
                // Sin la línea de venta no sabemos si el producto era gravado o
                // exonerado, y adivinarlo cambiaría el impuesto acreditado.
                throw MapeoComprobanteException::para(
                    $venta->id,
                    "La línea de devolución {$d->id} no tiene el ítem de venta asociado; "
                    . 'no se puede determinar cómo tributaba.'
                );
            }

            $bruto       = round((float) $d->subtotal, 2) * $factor;
            $abreviatura = trim((string) ($item->productoUnidad?->unidadMedida?->abreviatura ?: $item->unidad_nombre));

            $items[] = [
                // MISMO código que envió el comprobante original: el emisor lo usa
                // para comprobar que no se acreditan más unidades de las facturadas.
                'codigo'      => $item->producto?->codigo ?: ('P-' . $item->producto_id),
                'descripcion' => $this->descripcion($item, $abreviatura),
                'cantidad'    => $cantidad,
                // Precio unitario BRUTO. 10 decimales para que cantidad × precio
                // reproduzca el importe sin arrastrar error de redondeo.
                'precio_unitario' => round($bruto / $cantidad, 10),
                // Abreviatura del POS tal cual: el mapeo al catálogo 03 es del emisor.
                'unidad'      => $abreviatura ?: 'UND',
                // `incluye_igv` de ventoryPOS mezcla dos preguntas; el contrato las
                // separa. Aquí se traduce, que es donde corresponde.
                'impuesto'    => $item->incluye_igv ? Impuesto::GRAVADO->value : Impuesto::EXONERADO->value,
                'precio_incluye_impuesto' => true,
                'descuento'   => 0,
            ];
        }

        if ($items === []) {
            throw MapeoComprobanteException::para(
                $venta->id,
                "La devolución {$devolucion->id} no tiene líneas con cantidad devuelta."
            );
        }

        return $items;
    }

    /**
     * Descripción idéntica a la del comprobante original (VentaAComprobante): conserva
     * la unidad del POS entre paréntesis porque el Catálogo 03 es genérico y sin ella
     * el cliente no reconocería en la NC lo que devolvió.
     */
    private function descripcion(VentaItem $item, string $abreviatura): string
    {
        $nombre = trim((string) $item->producto_nombre);

        if ($abreviatura !== '') {
            $nombre .= " ({$abreviatura})";
        }

        return mb_substr($nombre, 0, 250); // límite de SUNAT
    }

    /**
     * Guarda G4. FacturaMac tiene el 18 % cableado; ventoryPOS lo lee de
     * `empresas.tasa_igv`. Con otra tasa la NC saldría con un IGV que no corresponde
     * al del comprobante que pretende acreditar, y nadie se enteraría hasta la
     * declaración.
     *
     * @throws MapeoComprobanteException
     */
    private function tasaIgv(Venta $venta): float
    {
        $soportada = (float) config('facturamac.tasa_igv_soportada', 18.0);
        $empresa   = (float) ($venta->empresa?->tasa_igv ?? $soportada);

        if (abs($empresa - $soportada) > 0.0001) {
            throw MapeoComprobanteException::para(
                $venta->id,
                "La empresa tiene una tasa de IGV de {$empresa}% y la emisión electrónica solo "
                . "está soportada al {$soportada}%."
            );
        }

        return $empresa / 100;
    }

    /**
     * Proporción del bruto que sobrevive al descuento global de la venta.
     *
     * El comprobante original repartió `ventas.descuento_total` entre sus líneas en
     * proporción al bruto de cada una (VentaAComprobante::construirLineas). La NC tiene
     * que aplicar ese mismo reparto o acreditaría más de lo que se facturó: sobre una
     * venta con 10 % de descuento global, devolver un ítem de S/ 100 acredita S/ 90.
     *
     * @throws MapeoComprobanteException
     */
    private function factorDescuentoGlobal(Venta $venta): float
    {
        $descuento = round((float) $venta->descuento_total, 2);

        if ($descuento <= 0) {
            return 1.0;
        }

        $bruto = 0.0;
        foreach ($venta->items as $item) {
            $bruto += ((float) $item->precio_unitario - (float) $item->descuento_item) * (float) $item->cantidad;
        }

        if ($bruto <= 0 || $descuento > $bruto + 0.01) {
            throw MapeoComprobanteException::para(
                $venta->id,
                "El descuento global de la venta {$venta->numero} (S/ {$descuento}) no es "
                . 'coherente con el bruto de sus ítems; la nota de crédito saldría negativa.'
            );
        }

        return max(0.0, 1 - ($descuento / $bruto));
    }

    private function usuario(): ?User
    {
        return $this->userId ? User::find($this->userId) : null;
    }

    private function avisarFallo(Devolucion $devolucion, string $mensaje): void
    {
        Log::error('No se pudo emitir la nota de crédito de una devolución', [
            'devolucion_id' => $devolucion->id,
            'venta_id'      => $devolucion->venta_id,
            'error'         => $mensaje,
        ]);

        // La auditoría es el canal por el que el admin se entera: la devolución
        // quedó hecha pero SUNAT sigue viendo el importe original declarado.
        AuditoriaService::log('venta_comprobante.nota_credito_fallida', $devolucion, [
            'venta_id' => $devolucion->venta_id,
            'error'    => mb_substr($mensaje, 0, 500),
            'aviso'    => 'La devolución NO se revirtió. Emitir la nota de crédito manualmente en FacturaMac.',
        ], $this->usuario());
    }

    public function failed(Throwable $e): void
    {
        $devolucion = Devolucion::find($this->devolucionId);
        if ($devolucion) {
            $this->avisarFallo($devolucion, $e->getMessage());
        } else {
            Log::error('Falló la nota de crédito y la devolución ya no existe', [
                'devolucion_id' => $this->devolucionId,
                'error'         => $e->getMessage(),
            ]);
        }
    }
}
