<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use MacSoft\Facturacion\Contrato\Enum\EstadoGuia;

/**
 * Guía de remisión emitida (o por emitir) a través de FacturaMac.
 *
 * ─── ESPEJO, NO ORIGINAL ───────────────────────────────────────────────────────
 *
 * El documento fiscal vive en FacturaMac. Aquí se guarda lo justo para listarlo,
 * buscarlo y —sobre todo— saber si el camión puede salir, porque eso se pregunta en
 * el almacén con el vehículo cargado y no puede depender de que FacturaMac responda
 * en ese instante.
 *
 * ─── `puede_trasladar` NO SE DEDUCE AQUÍ ───────────────────────────────────────
 *
 * Llega resuelto desde el emisor y se guarda tal cual. Tener una lista de estados
 * propia en este lado es exactamente lo que costó dos bugs fiscales con los
 * comprobantes: las dos listas se desincronizaron y nadie se enteró hasta que hubo
 * consecuencias. El enum compartido del contrato es la única fuente.
 *
 * ─── NINGUNA GUÍA MUEVE STOCK ──────────────────────────────────────────────────
 *
 * El stock lo mueven la venta, el despacho y la transferencia. Una guía es el
 * documento de ese movimiento, no el movimiento. Si esta clase tocara inventario
 * habría doble descuento al primer despacho.
 */
class Guia extends Model
{
    protected $table = 'guias';

    /** Estado propio de aquí: no se pudo ni construir el envío. No existe en el emisor. */
    public const ESTADO_ERROR_MAPEO = 'error_mapeo';

    protected $fillable = [
        'empresa_id', 'venta_id', 'transferencia_id', 'cliente_id',
        'facturamac_id', 'serie', 'correlativo', 'numero', 'estado',
        'puede_trasladar', 'aviso',
        'sunat_codigo', 'sunat_descripcion', 'error', 'intentos', 'enviado_at',
        'payload', 'motivo', 'modalidad', 'fecha_traslado',
        'destinatario', 'llegada_direccion',
        'idempotency_key', 'referencia_externa', 'user_id',
    ];

    protected function casts(): array
    {
        return [
            'puede_trasladar' => 'boolean',
            'payload'         => 'array',
            'fecha_traslado'  => 'date',
            'enviado_at'      => 'datetime',
            'intentos'        => 'integer',
        ];
    }

    public function scopeDeEmpresa(Builder $q, int $empresaId): Builder
    {
        return $q->where('empresa_id', $empresaId);
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    public function transferencia(): BelongsTo
    {
        return $this->belongsTo(Transferencia::class);
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** El estado como enum del contrato, o null si es uno propio de aquí. */
    public function estadoContrato(): ?EstadoGuia
    {
        return EstadoGuia::tryFrom((string) $this->estado);
    }

    /**
     * ¿Hay que volver a preguntarle a FacturaMac por ella?
     *
     * Se responde con el enum compartido, nunca con una lista escrita aquí.
     */
    public function esperaRespuesta(): bool
    {
        return $this->estadoContrato()?->esperaRespuesta() ?? false;
    }

    /** ¿Se acabó el recorrido? */
    public function terminal(): bool
    {
        return $this->estadoContrato()?->terminal()
            // `error_mapeo` no existe en el emisor y es definitivo: la guía ni se
            // llegó a construir, así que esperar no la va a mover.
            ?? $this->estado === self::ESTADO_ERROR_MAPEO;
    }

    /** ¿Tiene sentido reintentar el envío tal cual? */
    public function reintentable(): bool
    {
        return $this->estadoContrato()?->reintentable() ?? false;
    }

    public function getEstadoLabelAttribute(): string
    {
        return $this->estadoContrato()?->etiqueta()
            ?? ($this->estado === self::ESTADO_ERROR_MAPEO
                ? 'No se pudo preparar'
                : (string) $this->estado);
    }

    public function getEstadoColorAttribute(): string
    {
        return match (true) {
            $this->puede_trasladar         => 'green',
            $this->esperaRespuesta()       => 'blue',
            $this->estado === 'pendiente'  => 'gray',
            default                        => 'red',
        };
    }
}
