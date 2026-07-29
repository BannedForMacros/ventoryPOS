<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MacSoft\Facturacion\Contrato\Enum\EstadoComprobante;

/**
 * Comprobante electrónico SUNAT emitido (o por emitir) para una venta.
 *
 * Espejo local de lo que vive en FacturaMac. Guardamos copia de serie, correlativo,
 * QR y hash porque el ticket se imprime EN CAJA, al instante, y no puede depender de
 * que FacturaMac o SUNAT respondan en ese momento. El estado real llega después por
 * polling y se refleja en la UI.
 */
class VentaComprobante extends Model
{
    protected $table = 'venta_comprobantes';

    /**
     * ─── Estados propios del POS que el emisor NO conoce ─────────────────────
     *
     * `EstadoComprobante` cubre todo lo que puede pasarle a un comprobante DENTRO
     * de FacturaMac. Estos dos describen algo que ocurre ANTES de que el emisor
     * llegue a ver la venta, así que no tienen —ni deben tener— hueco en el enum
     * compartido y se tratan aquí, explícitamente:
     *
     *   error_mapeo  la venta no se pudo convertir en comprobante (falta el RUC,
     *                los importes no cuadran…). NUNCA se llamó al emisor.
     *   no_emitido   la empresa tiene la emisión apagada (§4.2 del contrato: no es
     *                un error y NO consume correlativo).
     *
     * Consecuencias, que son las mismas para los dos:
     *   · NO bloquean anular ni editar: SUNAT no sabe nada de esta venta, no hay
     *     documento fiscal que revertir con una Nota de Crédito.
     *   · SÍ son terminales: esperar no los va a mover, el polling debe parar.
     *   · NO son reintentables ni acreditables: no hay comprobante sobre el que
     *     actuar (un `error_mapeo` se arregla corrigiendo el DATO de la venta).
     */
    public const ESTADO_ERROR_MAPEO = 'error_mapeo';
    public const ESTADO_NO_EMITIDO  = 'no_emitido';

    /** @var list<string> Los dos de arriba, para recorrerlos de una vez. */
    public const ESTADOS_SOLO_POS = [self::ESTADO_ERROR_MAPEO, self::ESTADO_NO_EMITIDO];

    /**
     * ─── Espejos estáticos ───────────────────────────────────────────────────
     *
     * NO son la fuente de verdad: lo es `EstadoComprobante`, el enum del paquete
     * compartido `macsoft/facturacion-contrato`, del que derivan los métodos
     * `estadosEmitidos()`, `estadosAcreditables()`, `estadosReintentables()` y
     * `estadosTerminales()` — que es lo que usa TODO el comportamiento de esta
     * clase.
     *
     * Estas constantes sobreviven solo porque PHP no admite llamadas a métodos en
     * una expresión constante y hay consumidores que necesitan una constante de
     * verdad (`ConsultarEstadoComprobante::TERMINALES`,
     * `EmitirNotaCreditoElectronica`). Para que no vuelvan a desalinearse —ya han
     * causado tres bugs fiscales— `tests/Unit/Facturacion/EstadosComprobanteTest.php`
     * compara cada constante con su método derivado y falla si divergen.
     *
     * Si tocas una lista de aquí, toca el ENUM; esto se ajusta detrás.
     */
    public const ESTADOS_EMITIDOS = [
        'enviando',
        'enviado',
        'pendiente_resumen',
        'en_resumen',
        'pendiente_anulacion',
        'aceptado',
        // FALTABA, y con ella se podía anular en el POS una venta cuya factura Y
        // cuya nota de crédito ya estaban en SUNAT: doble reversión de stock y de
        // caja sobre dos documentos fiscales irreversibles.
        'anulado',
    ];

    /**
     * Estados sobre los que SUNAT admite una Nota de Crédito: el comprobante
     * tiene que haber llegado ya a SUNAT. Una boleta en `pendiente_resumen`
     * todavía no existe para SUNAT, así que la NC hay que ESPERARLA, no
     * fallarla (ver EmitirNotaCreditoElectronica).
     */
    public const ESTADOS_ACREDITABLES = ['aceptado', 'enviado'];

    /** Estados de fallo que admiten un nuevo intento sobre el MISMO comprobante. */
    public const ESTADOS_REINTENTABLES = ['pendiente', 'error_envio', 'rechazado'];

    /**
     * Estados que ya no van a cambiar solos: el polling debe parar y la interfaz
     * puede dejar de refrescar. Incluye los dos estados propios del POS.
     */
    public const ESTADOS_TERMINALES = [
        'aceptado',
        'rechazado',
        'anulado',
        'simulado',
        self::ESTADO_ERROR_MAPEO,
        self::ESTADO_NO_EMITIDO,
    ];

    /**
     * ¿Qué estados bloquean anular/editar la venta en el POS?
     *
     * Derivado de `EstadoComprobante::bloqueaAnulacion()`, que es donde vive la
     * respuesta y donde se razona la regla de oro: ante la duda, un estado
     * bloquea. Escribir esta lista a mano en el POS ya salió mal tres veces —la
     * última, sin `anulado`—, así que aquí no se escribe: se pregunta.
     *
     * @return list<string>
     */
    public static function estadosEmitidos(): array
    {
        // Los estados solo-POS quedan fuera a propósito: en ninguno de los dos
        // llegó a existir un comprobante en el emisor.
        return self::valoresSi(fn (EstadoComprobante $e) => $e->bloqueaAnulacion());
    }

    /** @return list<string> Derivado de EstadoComprobante::admiteNotaCredito(). */
    public static function estadosAcreditables(): array
    {
        return self::valoresSi(fn (EstadoComprobante $e) => $e->admiteNotaCredito());
    }

    /** @return list<string> Derivado de EstadoComprobante::esReintentable(). */
    public static function estadosReintentables(): array
    {
        return self::valoresSi(fn (EstadoComprobante $e) => $e->esReintentable());
    }

    /**
     * @return list<string> Derivado de EstadoComprobante::esTerminal(), MÁS los dos
     *                      estados propios del POS: ninguno se mueve solo.
     */
    public static function estadosTerminales(): array
    {
        return [
            ...self::valoresSi(fn (EstadoComprobante $e) => $e->esTerminal()),
            ...self::ESTADOS_SOLO_POS,
        ];
    }

    /**
     * Valores (`string`) de los casos del enum compartido que cumplen el filtro.
     *
     * @param  callable(EstadoComprobante): bool $filtro
     * @return list<string>
     */
    private static function valoresSi(callable $filtro): array
    {
        return array_values(array_map(
            static fn (EstadoComprobante $caso): string => $caso->value,
            array_filter(EstadoComprobante::cases(), $filtro),
        ));
    }

    /** ¿Ya no va a cambiar por sí solo? */
    public function esFinal(): bool
    {
        return in_array($this->estado, self::estadosTerminales(), true);
    }

    protected $fillable = [
        'venta_id', 'tipo', 'serie', 'correlativo', 'numero', 'estado',
        'facturamac_id', 'hash_cpe', 'qr',
        'sunat_codigo', 'sunat_descripcion',
        'error', 'intentos', 'enviado_at', 'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'correlativo'   => 'integer',
            'facturamac_id' => 'integer',
            'intentos'      => 'integer',
            'enviado_at'    => 'datetime',
        ];
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    /**
     * ¿SUNAT ya sabe (o va a saber hoy) de este comprobante?
     *
     * Es la pregunta que gobierna las guardas de anulación y edición de venta (G7):
     * una vez informado, revertir la operación solo es legal mediante Nota de Crédito.
     * Incluye `pendiente_resumen` a propósito: la boleta ya consumió correlativo y
     * entrará en el Resumen Diario de las 23:55 aunque el CDR todavía no exista.
     *
     * También incluye `anulado`: que el comprobante esté dado de baja NO devuelve la
     * venta al POS, al contrario — significa que SUNAT conoce DOS documentos (el
     * original y su nota de crédito). Anularla o editarla aquí revertiría stock y
     * caja por segunda vez.
     */
    public function esEmitido(): bool
    {
        return in_array($this->estado, self::estadosEmitidos(), true);
    }

    /**
     * ¿Se puede volver a intentar el envío sobre esta misma fila?
     *
     * Reintentamos SIEMPRE sobre el mismo comprobante (mismo correlativo) para no
     * dejar huecos en la numeración (G11). `error_mapeo` queda fuera: ahí el problema
     * está en el DATO de la venta (falta el RUC, tasa de IGV rara, descuadre), y
     * reintentar sin corregirlo solo repite el fallo.
     */
    public function puedeReintentar(): bool
    {
        return in_array($this->estado, self::estadosReintentables(), true);
    }

    /** ¿SUNAT ya lo conoce lo bastante como para admitirle una Nota de Crédito? */
    public function esAcreditable(): bool
    {
        return in_array($this->estado, self::estadosAcreditables(), true);
    }

    public function scopeEmitidos(Builder $q): Builder
    {
        return $q->whereIn('estado', self::estadosEmitidos());
    }

    /** Boletas a la espera del Resumen Diario: son las que hay que ir consultando. */
    public function scopePendientesDeConfirmacion(Builder $q): Builder
    {
        return $q->whereIn('estado', ['enviando', 'pendiente_resumen']);
    }
}
