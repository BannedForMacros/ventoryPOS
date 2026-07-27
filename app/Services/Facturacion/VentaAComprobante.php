<?php

namespace App\Services\Facturacion;

use App\Exceptions\MapeoComprobanteException;
use App\Models\Venta;
use App\Models\VentaItem;

/**
 * Proyecta una venta YA CERRADA de ventoryPOS al payload de emisión de FacturaMac.
 *
 * ─── Por qué esta clase es pura (sin HTTP, sin escrituras, sin `save()`) ───────────
 *
 * Aquí se decide si la caja cuadra o no. Es el único punto del sistema donde una
 * diferencia de un céntimo se convierte en un documento fiscal irreversible, así que
 * tiene que poder ejecutarse cientos de veces contra ventas históricas reales, en
 * seco, sin efectos: eso es lo que hace `pos:simular-emision`. Toda la E/S que el
 * mapeo necesitaría (el catálogo de unidades) está encapsulada en MapaUnidadesSunat
 * y se puede precargar en memoria.
 *
 * La clase LEE la venta y nada más. `VentaService::crear()`, `aplicarItemsPagos()` y
 * `Venta::calcularTotales()` no se tocan ni se invocan: acoplamiento de solo lectura.
 *
 * ─── El problema de fondo: dos formas legítimas de sumar el mismo IGV ─────────────
 *
 * ventoryPOS redondea el IGV UNA vez, a nivel de venta:
 *     igv = round(base_gravada_total * tasa, 2)
 * SUNAT (y FacturaMac) lo suman POR ÍTEM:
 *     igv = Σ round(subtotal_i * tasa, 2)
 * Los dos criterios son correctos y difieren en 1–3 céntimos con frecuencia. El
 * cliente pagó el número de ventoryPOS, así que ese es el número que manda: el
 * comprobante se concilia contra `ventas.total` (§5.3), no al revés.
 *
 * @see \Venta::calcularTotales()  — la lógica que este mapper ESPEJA sin modificar.
 */
class VentaAComprobante
{
    /** ventoryPOS → Catálogo 01 SUNAT. `ticket` no está: es nota de venta interna. */
    private const TIPOS = [
        'boleta'  => '03',
        'factura' => '01',
    ];

    /** ventoryPOS → Catálogo 06 SUNAT (tipo de documento de identidad). */
    private const TIPOS_DOC = [
        'DNI'       => '1',
        'RUC'       => '6',
        'CE'        => '4',
        'pasaporte' => '7',
    ];

    /** Documento del Cliente General: SUNAT lo admite en boletas por debajo de S/ 700. */
    private const DOC_SIN_IDENTIFICAR = '0';

    public function __construct(
        private readonly MapaUnidadesSunat $unidades = new MapaUnidadesSunat(),
    ) {
    }

    /**
     * Payload listo para `POST /api/v1/comprobantes` de FacturaMac.
     *
     * @return array<string, mixed>
     *
     * @throws MapeoComprobanteException si la venta no puede emitirse correctamente.
     */
    public function mapear(Venta $venta): array
    {
        return $this->mapearConDiagnostico($venta)['payload'];
    }

    /**
     * Igual que `mapear()` pero devolviendo además cómo salió la conciliación de
     * céntimos. Lo consume `pos:simular-emision` para auditar ventas reales en seco:
     * sin esto no habría forma de saber cuántas ventas necesitan ajuste antes de
     * enchufar SUNAT.
     *
     * @return array{payload: array<string, mixed>, diagnostico: array<string, mixed>}
     *
     * @throws MapeoComprobanteException
     */
    public function mapearConDiagnostico(Venta $venta): array
    {
        $tipo = $this->resolverTipo($venta);
        $tasa = $this->validarTasa($venta);
        $this->validarMoneda($venta);

        $lineas = $this->construirLineas($venta, $tasa);
        [$lineas, $diagnostico] = $this->conciliarCentavos($venta, $lineas, $tasa);

        $cliente = $this->mapearCliente($venta, $tipo);

        $sumaIgv = $this->suma($lineas, 'igv_item');

        $payload = [
            'idempotency_key'  => $venta->idempotency_key ?: "venta-{$venta->id}",
            'tipo_comprobante' => $tipo,
            'serie'            => $this->serieDe($tipo),
            'fecha_emision'    => $this->fechaEmision($venta),
            // v1 siempre en PEN: es lo que ventoryPOS contabiliza de verdad (G12).
            'moneda'           => 'PEN',
            ...$this->formaPago($venta),
            'observaciones'    => "POS {$venta->numero} · Turno {$venta->turno_id}",
            'cliente'          => $cliente,
            'detalles'         => $this->detalles($venta, $lineas),
            // Contrato de verificación: si FacturaMac recalcula y no cuadra (±0.01)
            // devuelve 422 y NO consume correlativo. Es la última red de seguridad
            // antes de que el documento se vuelva irreversible.
            'totales_esperados' => [
                'igv'   => $sumaIgv,
                'total' => round((float) $venta->total, 2),
            ],
        ];

        return [
            'payload'     => $payload,
            'diagnostico' => $diagnostico,
        ];
    }

    // ── Guardas ──────────────────────────────────────────────────────────────────

    /**
     * @throws MapeoComprobanteException
     */
    private function resolverTipo(Venta $venta): string
    {
        $tipoPos = (string) $venta->tipo_comprobante;

        if (! isset(self::TIPOS[$tipoPos])) {
            throw MapeoComprobanteException::para(
                $venta->id,
                "La venta es de tipo '{$tipoPos}' y no genera comprobante electrónico. "
                . 'Solo se emiten boleta (03) y factura (01); el ticket es una nota de venta interna.',
                ['tipo_comprobante' => $tipoPos],
            );
        }

        return self::TIPOS[$tipoPos];
    }

    /**
     * Guarda G4 — tasa de IGV.
     *
     * FacturaMac tiene el 18 % cableado en tres sitios distintos (config/sunat.php,
     * DetalleComprobante y Producto::getPrecioConIgvAttribute). ventoryPOS, en cambio,
     * lee `empresas.tasa_igv`, que es configurable por empresa. Emitir con otra tasa
     * no daría error: daría un comprobante con importes distintos a los cobrados, y
     * nadie se enteraría hasta la declaración. Por eso abortamos.
     *
     * @throws MapeoComprobanteException
     */
    private function validarTasa(Venta $venta): float
    {
        $empresa = $venta->empresa;

        if (! $empresa) {
            throw MapeoComprobanteException::para(
                $venta->id,
                'La venta no tiene empresa cargada; no se puede determinar la tasa de IGV.',
            );
        }

        $soportada = (float) config('facturamac.tasa_igv_soportada', 18.0);
        $tasaEmpresa = (float) ($empresa->tasa_igv ?? 18);

        if (abs($tasaEmpresa - $soportada) > 0.0001) {
            throw MapeoComprobanteException::para(
                $venta->id,
                "La empresa tiene una tasa de IGV de {$tasaEmpresa}% y la emisión electrónica "
                . "solo está soportada al {$soportada}%. Emitir con otra tasa descuadraría el "
                . 'comprobante en silencio.',
                ['tasa_empresa' => $tasaEmpresa, 'tasa_soportada' => $soportada],
            );
        }

        return $tasaEmpresa / 100;
    }

    /**
     * Guarda G12 — multimoneda.
     *
     * ventoryPOS puede COBRAR en USD pero CONTABILIZA todo en soles al tipo de cambio
     * congelado; `ventas.total` siempre está en PEN. FacturaMac, en cambio, emite en
     * la moneda del comprobante. Emitir en USD exigiría reconstruir los importes al
     * TC y volver a conciliar: fuera de alcance en v1, y mejor no emitir que emitir mal.
     *
     * @throws MapeoComprobanteException
     */
    private function validarMoneda(Venta $venta): void
    {
        $moneda = strtoupper(trim((string) ($venta->moneda ?? 'PEN'))) ?: 'PEN';

        if ($moneda !== 'PEN') {
            throw MapeoComprobanteException::para(
                $venta->id,
                "La venta se cobró en {$moneda} y la emisión electrónica v1 solo admite PEN.",
                ['moneda' => $moneda],
            );
        }
    }

    // ── Líneas: prorrateo del descuento global e IGV por ítem ────────────────────

    /**
     * Construye las líneas del comprobante replicando el criterio de
     * `Venta::calcularTotales()`.
     *
     * Sobre el DESCUENTO GLOBAL (gap G2, crítico): `ventas.descuento_total` NO puede
     * viajar como campo global. `ComprobanteController::calcularTotales()` de
     * FacturaMac hace `total = gravadas + exoneradas + inafectas + igv` SIN restar el
     * descuento global, así que el comprobante saldría inflado justo por ese importe.
     * La solución es prorratearlo dentro de cada línea, por el BRUTO — exactamente el
     * mismo criterio con el que `calcularTotales()` lo reparte entre base gravada y
     * exonerada, de modo que la carga tributaria se desplaza igual en ambos cálculos.
     *
     * No redondeamos el prorrateo por línea a propósito: si lo hiciéramos, la suma de
     * las partes dejaría de valer el descuento total y meteríamos un descuadre nuevo.
     * La deriva de redondeo se resuelve una sola vez, al final, en `conciliarCentavos()`.
     *
     * @return list<array<string, mixed>>
     *
     * @throws MapeoComprobanteException
     */
    private function construirLineas(Venta $venta, float $tasa): array
    {
        $items = $venta->items;

        if ($items === null || count($items) === 0) {
            throw MapeoComprobanteException::para($venta->id, 'La venta no tiene ítems que facturar.');
        }

        // 1) Bruto de cada línea, tal como lo calcula calcularTotales().
        $brutos     = [];
        $brutoTotal = 0.0;

        foreach ($items as $item) {
            $bruto = ((float) $item->precio_unitario - (float) $item->descuento_item) * (float) $item->cantidad;
            $brutos[] = $bruto;
            $brutoTotal += $bruto;
        }

        $descuentoGlobal = (float) $venta->descuento_total;

        if ($descuentoGlobal > $brutoTotal + 0.01) {
            throw MapeoComprobanteException::para(
                $venta->id,
                "El descuento global (S/ {$descuentoGlobal}) supera el bruto de los ítems "
                . "(S/ {$brutoTotal}). El comprobante saldría negativo.",
                ['descuento_total' => $descuentoGlobal, 'bruto_items' => $brutoTotal],
            );
        }

        // 2) Cada línea, ya neta de su parte del descuento global.
        $lineas = [];

        foreach ($items as $indice => $item) {
            /** @var VentaItem $item */
            $cantidad = (float) $item->cantidad;

            if ($cantidad <= 0) {
                throw MapeoComprobanteException::para(
                    $venta->id,
                    "El ítem «{$item->producto_nombre}» tiene cantidad {$cantidad}; SUNAT exige cantidad positiva.",
                    ['item_indice' => $indice, 'cantidad' => $cantidad],
                );
            }

            $bruto = $brutos[$indice];
            $prorrateo = ($brutoTotal > 0 && $descuentoGlobal > 0)
                ? $descuentoGlobal * ($bruto / $brutoTotal)
                : 0.0;
            $neto = $bruto - $prorrateo;

            // `incluye_igv` en ventoryPOS NO significa "afecto/no afecto" a secas:
            // true  = producto GRAVADO cuyo precio ya trae el IGV dentro (precio bruto).
            // false = producto EXONERADO (medicamentos vet, ciertos alimentos), que
            //         nunca paga IGV. Está documentado en Venta::calcularTotales().
            if ($item->incluye_igv) {
                $subtotal   = round($neto / (1 + $tasa), 2);
                $igv        = round($subtotal * $tasa, 2);
                $afectacion = '10'; // Gravado - Operación onerosa
            } else {
                $subtotal   = round($neto, 2);
                $igv        = 0.00;
                $afectacion = '20'; // Exonerado - Operación onerosa
            }

            $lineas[] = [
                'item'        => $item,
                'gravado'     => (bool) $item->incluye_igv,
                'cantidad'    => round($cantidad, 4),
                'subtotal'    => $subtotal,
                'igv_item'    => $igv,
                'afectacion'  => $afectacion,
            ];
        }

        return $lineas;
    }

    // ── Conciliación de céntimos (§5.3) ──────────────────────────────────────────

    /**
     * Cuadra la suma por ítem del comprobante contra `ventas.total`, que es el importe
     * que el cliente pagó de verdad.
     *
     * La diferencia nace del doble criterio de redondeo (G3) y es de 1–3 céntimos.
     * Se absorbe en la línea GRAVADA de mayor importe porque es la que menos distorsiona
     * en términos relativos y porque el ajuste tiene que caer sobre base gravada para
     * que el IGV declarado siga siendo coherente.
     *
     * El ajuste va en dos pasos porque uno solo no basta: al mover el subtotal, el IGV
     * se recalcula redondeando otra vez y puede volver a desviarse un céntimo. El
     * segundo paso corrige ese resto directamente sobre el IGV del ítem, algo que SUNAT
     * tolera hasta ±0.01 entre `igv_item` y `subtotal * tasa`.
     *
     * Por encima de la tolerancia NO se emite. Un delta grande no es redondeo, es un
     * bug de mapeo: prefiero un comprobante no emitido y visible que uno emitido con el
     * importe equivocado, porque lo primero se arregla y lo segundo exige Nota de
     * Crédito ante SUNAT.
     *
     * @param  list<array<string, mixed>> $lineas
     * @return array{0: list<array<string, mixed>>, 1: array<string, mixed>}
     *
     * @throws MapeoComprobanteException
     */
    private function conciliarCentavos(Venta $venta, array $lineas, float $tasa): array
    {
        $tolerancia = (float) config('facturamac.tolerancia_centavos', 0.05);
        $totalVenta = round((float) $venta->total, 2);

        $calculado = $this->totalCalculado($lineas);
        $delta     = round($totalVenta - $calculado, 2);

        $diagnostico = [
            'venta_id'        => $venta->id,
            'numero'          => $venta->numero,
            'total_venta'     => $totalVenta,
            'total_calculado' => $calculado,
            'delta'           => $delta,
            'ajustado'        => false,
            'linea_ajustada'  => null,
            'suma_subtotales' => $this->suma($lineas, 'subtotal'),
            'suma_igv'        => $this->suma($lineas, 'igv_item'),
        ];

        if (abs($delta) < 0.005) {
            return [$lineas, $diagnostico];
        }

        if (abs($delta) > $tolerancia) {
            throw MapeoComprobanteException::para(
                $venta->id,
                sprintf(
                    'Descuadre de mapeo en la venta %s: el comprobante suma S/ %.2f y la venta '
                    . 'registra S/ %.2f (delta S/ %.2f, tolerancia S/ %.2f). No se emite: un delta '
                    . 'de este tamaño indica un error de mapeo, no un redondeo.',
                    $venta->numero ?? $venta->id,
                    $calculado,
                    $totalVenta,
                    $delta,
                    $tolerancia,
                ),
                [
                    'total_venta'     => $totalVenta,
                    'total_calculado' => $calculado,
                    'delta'           => $delta,
                    'tolerancia'      => $tolerancia,
                ],
            );
        }

        $indice = $this->indiceLineaAbsorbente($lineas);
        $linea  = $lineas[$indice];

        // Paso 1 — mover el valor de venta. En una línea gravada el delta viene en
        // soles brutos (IGV incluido), así que hay que pasarlo a neto antes de sumarlo
        // a la base; en una exonerada bruto y neto coinciden.
        if ($linea['gravado']) {
            $lineas[$indice]['subtotal'] = round($linea['subtotal'] + round($delta / (1 + $tasa), 2), 2);
            $lineas[$indice]['igv_item'] = round($lineas[$indice]['subtotal'] * $tasa, 2);
        } else {
            $lineas[$indice]['subtotal'] = round($linea['subtotal'] + $delta, 2);
        }

        if ($lineas[$indice]['subtotal'] < 0) {
            throw MapeoComprobanteException::para(
                $venta->id,
                'El ajuste de céntimos dejaría una línea con importe negativo.',
                ['linea' => $indice, 'delta' => $delta],
            );
        }

        // Paso 2 — el resto que dejó el segundo redondeo, contra el IGV del ítem.
        $residual = round($totalVenta - $this->totalCalculado($lineas), 2);

        if (abs($residual) >= 0.005) {
            if (abs($residual) > 0.01) {
                throw MapeoComprobanteException::para(
                    $venta->id,
                    sprintf(
                        'La conciliación de céntimos no converge en la venta %s: queda un residuo '
                        . 'de S/ %.2f tras absorber el delta de S/ %.2f.',
                        $venta->numero ?? $venta->id,
                        $residual,
                        $delta,
                    ),
                    ['delta' => $delta, 'residual' => $residual],
                );
            }

            if ($linea['gravado']) {
                $lineas[$indice]['igv_item'] = round($lineas[$indice]['igv_item'] + $residual, 2);

                // SUNAT valida que el IGV del ítem no se aleje más de un céntimo de
                // base × tasa. Si nos pasáramos, el CDR rechazaría el comprobante.
                $teorico = round($lineas[$indice]['subtotal'] * $tasa, 2);
                if (abs($lineas[$indice]['igv_item'] - $teorico) > 0.0101) {
                    throw MapeoComprobanteException::para(
                        $venta->id,
                        'El ajuste de céntimos rompería la tolerancia de ±0.01 que SUNAT admite '
                        . 'entre el IGV del ítem y base × tasa.',
                        ['igv_ajustado' => $lineas[$indice]['igv_item'], 'igv_teorico' => $teorico],
                    );
                }
            } else {
                $lineas[$indice]['subtotal'] = round($lineas[$indice]['subtotal'] + $residual, 2);
            }
        }

        // Revalidación final: si después de todo no cuadra al céntimo, no se emite.
        $finalCalculado = $this->totalCalculado($lineas);
        if (abs(round($totalVenta - $finalCalculado, 2)) >= 0.005) {
            throw MapeoComprobanteException::para(
                $venta->id,
                sprintf(
                    'Tras la conciliación el comprobante sigue sin cuadrar: S/ %.2f frente a S/ %.2f.',
                    $finalCalculado,
                    $totalVenta,
                ),
                ['total_calculado' => $finalCalculado, 'total_venta' => $totalVenta],
            );
        }

        $diagnostico['ajustado']        = true;
        $diagnostico['linea_ajustada']  = $indice;
        $diagnostico['total_calculado'] = $finalCalculado;
        $diagnostico['suma_subtotales'] = $this->suma($lineas, 'subtotal');
        $diagnostico['suma_igv']        = $this->suma($lineas, 'igv_item');

        return [$lineas, $diagnostico];
    }

    /**
     * Línea donde absorber el descuadre: la GRAVADA de mayor importe.
     *
     * Si la venta es 100 % exonerada no hay ninguna gravada; entonces se usa la
     * exonerada mayor, donde el ajuste es trivial porque no hay IGV que recalcular.
     *
     * @param list<array<string, mixed>> $lineas
     */
    private function indiceLineaAbsorbente(array $lineas): int
    {
        $mejor = null;
        $mejorImporte = -1.0;

        foreach ($lineas as $i => $linea) {
            if (! $linea['gravado']) {
                continue;
            }
            $importe = $linea['subtotal'] + $linea['igv_item'];
            if ($importe > $mejorImporte) {
                $mejorImporte = $importe;
                $mejor = $i;
            }
        }

        if ($mejor !== null) {
            return $mejor;
        }

        foreach ($lineas as $i => $linea) {
            if ($linea['subtotal'] > $mejorImporte) {
                $mejorImporte = $linea['subtotal'];
                $mejor = $i;
            }
        }

        return $mejor ?? 0;
    }

    /** @param list<array<string, mixed>> $lineas */
    private function totalCalculado(array $lineas): float
    {
        return round($this->suma($lineas, 'subtotal') + $this->suma($lineas, 'igv_item'), 2);
    }

    /** @param list<array<string, mixed>> $lineas */
    private function suma(array $lineas, string $clave): float
    {
        return round(array_sum(array_column($lineas, $clave)), 2);
    }

    // ── Detalles ─────────────────────────────────────────────────────────────────

    /**
     * Convierte las líneas conciliadas al formato de `detalles` de FacturaMac.
     *
     * Los precios unitarios se DERIVAN del importe final de la línea (subtotal /
     * cantidad) en vez de copiar `venta_items.precio_unitario`. Es deliberado: cuando
     * hay descuento de línea o prorrateo del descuento global, el precio de lista ya
     * no multiplica al subtotal y el detalle quedaría internamente incoherente ante
     * SUNAT. Por eso `descuento` viaja en 0: el descuento ya está incorporado al
     * precio efectivo, y mandarlo además como campo lo descontaría dos veces.
     *
     * @param  list<array<string, mixed>> $lineas
     * @return list<array<string, mixed>>
     */
    private function detalles(Venta $venta, array $lineas): array
    {
        $empresaId = (int) $venta->empresa_id;
        $detalles  = [];

        foreach ($lineas as $linea) {
            /** @var VentaItem $item */
            $item      = $linea['item'];
            $cantidad  = (float) $linea['cantidad'];
            $subtotal  = (float) $linea['subtotal'];
            $igv       = (float) $linea['igv_item'];
            $totalItem = round($subtotal + $igv, 2);

            $abreviatura = $this->abreviaturaDe($item);

            $detalles[] = [
                'codigo_producto'       => $item->producto?->codigo ?: ('P-' . $item->producto_id),
                'codigo_producto_sunat' => null,
                'descripcion'           => $this->descripcion($item, $abreviatura),
                'unidad_medida'         => $this->unidades->codigoSunat(
                    $empresaId,
                    $item->productoUnidad?->unidad_medida_id,
                    $abreviatura,
                ),
                'cantidad'                 => $cantidad,
                // 10 decimales: es la precisión que Greenter usa para el valor unitario
                // y evita que SUNAT recalcule un importe distinto del que declaramos.
                'precio_unitario'          => round($subtotal / $cantidad, 10),
                'precio_unitario_con_igv'  => round($totalItem / $cantidad, 2),
                'descuento'                => 0,
                'subtotal'                 => $subtotal,
                'igv_item'                 => $igv,
                'total_item'               => $totalItem,
                'tipo_afectacion_igv'      => $linea['afectacion'],
            ];
        }

        return $detalles;
    }

    /**
     * Abreviatura tal como la escribió la empresa. `venta_items.unidad_nombre` guarda
     * el NOMBRE de la unidad (así lo persiste VentaService), no la abreviatura, así que
     * preferimos la relación cuando está cargada y caemos al nombre si no lo está.
     */
    private function abreviaturaDe(VentaItem $item): string
    {
        $abrev = $item->productoUnidad?->unidadMedida?->abreviatura;

        return trim((string) ($abrev ?: $item->unidad_nombre));
    }

    /**
     * La descripción conserva la unidad original del POS entre paréntesis
     * ("Cemento Sol 42.5kg (BOL)") porque el código del Catálogo 03 suele ser genérico
     * (casi todo acaba en NIU) y sin esto el comprobante impreso perdería la
     * información que el cliente necesita para reconocer lo que compró.
     */
    private function descripcion(VentaItem $item, string $abreviatura): string
    {
        $nombre = trim((string) $item->producto_nombre);

        if ($abreviatura !== '') {
            $nombre .= " ({$abreviatura})";
        }

        // SUNAT limita la descripción del ítem a 250 caracteres.
        return mb_substr($nombre, 0, 250);
    }

    // ── Cliente (§5.4) ───────────────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     *
     * @throws MapeoComprobanteException
     */
    private function mapearCliente(Venta $venta, string $tipo): array
    {
        $cliente = $venta->cliente;

        if (! $cliente) {
            throw MapeoComprobanteException::para($venta->id, 'La venta no tiene cliente asociado.');
        }

        if ($cliente->es_cliente_general) {
            // Catálogo 06 tipo '0' ("sin documento"): la vía que SUNAT deja para la
            // boleta anónima de mostrador. num_doc '-' es la convención aceptada.
            $tipoDoc = self::DOC_SIN_IDENTIFICAR;
            $numDoc  = '-';
        } else {
            $tipoPos = (string) $cliente->tipo_documento;

            if (! isset(self::TIPOS_DOC[$tipoPos])) {
                throw MapeoComprobanteException::para(
                    $venta->id,
                    "El cliente tiene el tipo de documento '{$tipoPos}', que no tiene equivalente "
                    . 'en el Catálogo 06 de SUNAT (solo DNI, RUC, CE y pasaporte).',
                    ['tipo_documento' => $tipoPos, 'cliente_id' => $cliente->id],
                );
            }

            $tipoDoc = self::TIPOS_DOC[$tipoPos];
            $numDoc  = trim((string) $cliente->numero_documento);
        }

        $razonSocial = trim($cliente->getNombreCompletoAttribute());
        $direccion   = trim((string) $cliente->direccion);

        $this->validarCliente($venta, $tipo, $tipoDoc, $numDoc, $razonSocial, $direccion);

        return [
            'tipo_doc'     => $tipoDoc,
            'num_doc'      => $numDoc !== '' ? $numDoc : '-',
            'razon_social' => mb_substr($razonSocial, 0, 250),
            'direccion'    => $direccion !== '' ? mb_substr($direccion, 0, 250) : null,
        ];
    }

    /**
     * Reglas duras del adquirente. Fallar aquí es fallar TARDE: lo correcto es que el
     * POS no deje cerrar la venta (V13). Esta validación es la red de seguridad para
     * las ventas que ya entraron por otra vía o que se emiten en diferido.
     *
     * @throws MapeoComprobanteException
     */
    private function validarCliente(
        Venta $venta,
        string $tipo,
        string $tipoDoc,
        string $numDoc,
        string $razonSocial,
        string $direccion,
    ): void {
        if ($razonSocial === '') {
            throw MapeoComprobanteException::para(
                $venta->id,
                'El cliente no tiene nombre ni razón social; SUNAT lo exige en todo comprobante.',
            );
        }

        if ($tipo === '01') {
            // Factura: SUNAT rechaza cualquier factura cuyo adquirente no sea un RUC.
            if ($tipoDoc !== '6') {
                throw MapeoComprobanteException::para(
                    $venta->id,
                    'No se puede emitir una FACTURA a un cliente sin RUC. '
                    . 'Registra el RUC del cliente o emite una boleta.',
                    ['tipo_doc' => $tipoDoc, 'num_doc' => $numDoc],
                );
            }

            if (! preg_match('/^\d{11}$/', $numDoc)) {
                throw MapeoComprobanteException::para(
                    $venta->id,
                    "El RUC «{$numDoc}» no tiene 11 dígitos; SUNAT rechazaría la factura.",
                    ['num_doc' => $numDoc],
                );
            }

            if ($direccion === '') {
                throw MapeoComprobanteException::para(
                    $venta->id,
                    'La factura exige la dirección fiscal del cliente y este no la tiene registrada.',
                    ['num_doc' => $numDoc],
                );
            }

            return;
        }

        // Boleta: por encima del umbral (S/ 700) SUNAT exige identificar al adquirente.
        $umbral = (float) config('facturamac.umbral_boleta_identificada', 700.0);
        $total  = round((float) $venta->total, 2);

        if ($total >= $umbral && ($tipoDoc === self::DOC_SIN_IDENTIFICAR || $numDoc === '' || $numDoc === '-')) {
            throw MapeoComprobanteException::para(
                $venta->id,
                sprintf(
                    'La boleta suma S/ %.2f y desde S/ %.2f SUNAT exige identificar al cliente. '
                    . 'Registra el DNI o RUC del comprador antes de emitir.',
                    $total,
                    $umbral,
                ),
                ['total' => $total, 'umbral' => $umbral, 'tipo_doc' => $tipoDoc],
            );
        }
    }

    // ── Cabecera ─────────────────────────────────────────────────────────────────

    private function serieDe(string $tipo): ?string
    {
        $series = config('facturamac.series', []);

        return is_array($series) ? ($series[$tipo] ?? null) : null;
    }

    private function fechaEmision(Venta $venta): string
    {
        $fecha = $venta->fecha_venta ?? $venta->created_at;

        return $fecha ? $fecha->format('Y-m-d') : now()->format('Y-m-d');
    }

    /**
     * §5.6 — Forma de pago.
     *
     * En una venta a crédito el saldo pendiente va como cuota única con la fecha de
     * vencimiento pactada; FacturaMac ya sabe construir el FormaPagoCredito + Cuota a
     * partir de esto (`SunatService::buildFormaPago()`).
     *
     * @return array<string, mixed>
     */
    private function formaPago(Venta $venta): array
    {
        if (! $venta->es_credito) {
            return [
                'forma_pago'        => 'Contado',
                'condicion_pago'    => null,
                'fecha_vencimiento' => null,
            ];
        }

        $vencimiento = $venta->fecha_vencimiento;
        $emision     = $venta->fecha_venta ?? $venta->created_at;

        $dias = ($vencimiento && $emision)
            ? max(0, $emision->copy()->startOfDay()->diffInDays($vencimiento->copy()->startOfDay()))
            : null;

        return [
            'forma_pago'        => 'Credito',
            'condicion_pago'    => $dias !== null ? "Crédito a {$dias} días" : 'Crédito',
            'fecha_vencimiento' => $vencimiento?->format('Y-m-d'),
        ];
    }
}
