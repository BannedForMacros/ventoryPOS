<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    /** Estados en los que el comprobante ya salió del POS y SUNAT lo conoce (o lo conocerá hoy). */
    public const ESTADOS_EMITIDOS = ['aceptado', 'enviando', 'pendiente_resumen'];

    /** Estados de fallo que admiten un nuevo intento sobre el MISMO comprobante. */
    public const ESTADOS_REINTENTABLES = ['pendiente', 'error_envio', 'rechazado'];

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
     */
    public function esEmitido(): bool
    {
        return in_array($this->estado, self::ESTADOS_EMITIDOS, true);
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
        return in_array($this->estado, self::ESTADOS_REINTENTABLES, true);
    }

    public function scopeEmitidos(Builder $q): Builder
    {
        return $q->whereIn('estado', self::ESTADOS_EMITIDOS);
    }

    /** Boletas a la espera del Resumen Diario: son las que hay que ir consultando. */
    public function scopePendientesDeConfirmacion(Builder $q): Builder
    {
        return $q->whereIn('estado', ['enviando', 'pendiente_resumen']);
    }
}
