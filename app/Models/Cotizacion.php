<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Cotización (proforma): precios congelados con una vigencia. La `referencia`
 * es una etiqueta flexible por rubro ("Obra Av. Grau", "Paciente Firulais").
 *
 * Ciclo de vida:
 *   vigente → aceptada | rechazada | vencida | anulada
 *   vigente/aceptada/vencida → convertida (se cobró en el POS; venta_id
 *   apunta a la venta creada).
 *
 * Las vigentes con fecha_vencimiento < hoy se marcan 'vencida' al listar el
 * módulo (update masivo, sin cron).
 */
class Cotizacion extends Model
{
    protected $table = 'cotizaciones';

    public const ESTADO_VIGENTE    = 'vigente';
    public const ESTADO_ACEPTADA   = 'aceptada';
    public const ESTADO_RECHAZADA  = 'rechazada';
    public const ESTADO_VENCIDA    = 'vencida';
    public const ESTADO_CONVERTIDA = 'convertida';
    public const ESTADO_ANULADA    = 'anulada';

    public const ESTADOS = [
        self::ESTADO_VIGENTE, self::ESTADO_ACEPTADA, self::ESTADO_RECHAZADA,
        self::ESTADO_VENCIDA, self::ESTADO_CONVERTIDA, self::ESTADO_ANULADA,
    ];

    /** Estados desde los que la cotización aún puede convertirse en venta. */
    public const ESTADOS_CONVERTIBLES = [
        self::ESTADO_VIGENTE, self::ESTADO_ACEPTADA, self::ESTADO_VENCIDA,
    ];

    /** Estados en los que la cotización aún puede editarse completa. */
    public const ESTADOS_EDITABLES = [
        self::ESTADO_VIGENTE, self::ESTADO_VENCIDA,
    ];

    protected $fillable = [
        'empresa_id', 'local_id', 'user_id', 'cliente_id',
        'numero', 'referencia', 'estado',
        'fecha', 'fecha_vencimiento', 'fecha_entrega_estimada',
        'subtotal', 'descuento_total', 'igv', 'total',
        'venta_id', 'observacion', 'notas_seguimiento', 'ultimo_contacto',
    ];

    protected function casts(): array
    {
        return [
            'fecha'                  => 'date:Y-m-d',
            'fecha_vencimiento'      => 'date:Y-m-d',
            'fecha_entrega_estimada' => 'date:Y-m-d',
            'ultimo_contacto'        => 'date:Y-m-d',
            'subtotal'               => 'decimal:2',
            'descuento_total'        => 'decimal:2',
            'igv'                    => 'decimal:2',
            'total'                  => 'decimal:2',
        ];
    }

    // ── Relaciones ───────────────────────────────────────────────────────

    public function empresa(): BelongsTo { return $this->belongsTo(Empresa::class); }
    public function local(): BelongsTo   { return $this->belongsTo(Local::class); }
    public function user(): BelongsTo    { return $this->belongsTo(User::class); }
    public function cliente(): BelongsTo { return $this->belongsTo(Cliente::class); }
    public function venta(): BelongsTo   { return $this->belongsTo(Venta::class); }
    public function items(): HasMany     { return $this->hasMany(CotizacionItem::class)->orderBy('id'); }

    // ── Scopes ───────────────────────────────────────────────────────────

    public function scopeDeEmpresa(Builder $q, int $id): Builder { return $q->where('empresa_id', $id); }
    public function scopeVigente(Builder $q): Builder            { return $q->where('estado', self::ESTADO_VIGENTE); }

    // ── Helpers ──────────────────────────────────────────────────────────

    public function esEditable(): bool
    {
        return in_array($this->estado, self::ESTADOS_EDITABLES, true);
    }

    public function esConvertible(): bool
    {
        return !$this->venta_id && in_array($this->estado, self::ESTADOS_CONVERTIBLES, true);
    }

    /**
     * Correlativo COT-0001 por empresa. Igual que Venta::generarNumero, NO
     * bloquea filas: la unicidad final la garantiza el índice único
     * (empresa_id, numero); el caller reintenta si dos requests colisionan.
     */
    public static function generarNumero(int $empresaId): string
    {
        $max = (int) DB::table('cotizaciones')
            ->where('empresa_id', $empresaId)
            ->selectRaw("COALESCE(MAX(CAST(SUBSTRING(numero FROM 5) AS INTEGER)), 0) as n")
            ->value('n');

        return 'COT-' . str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Recalcula y persiste totales al estilo del POS: los precios cotizados
     * YA incluyen IGV cuando el producto es gravado (incluye_igv=true); los
     * exonerados no pagan IGV. El desglose es informativo:
     *
     *   - subtotal        = suma bruta (precio × cantidad)
     *   - descuento_total = suma de descuentos por línea (descuento × cantidad)
     *   - igv             = base gravada neta × tasa de la empresa
     *   - total           = subtotal − descuento_total
     */
    public function recalcularTotales(): void
    {
        $this->loadMissing('empresa', 'items.producto');
        $tasa = (float) ($this->empresa?->tasa_igv ?? 18) / 100;

        $subtotal    = 0.0;
        $descuentos  = 0.0;
        $baseGravada = 0.0;

        foreach ($this->items as $i) {
            $bruto   = (float) $i->precio_unitario * (float) $i->cantidad;
            $importe = ((float) $i->precio_unitario - (float) $i->descuento_item) * (float) $i->cantidad;

            $subtotal   += $bruto;
            $descuentos += $bruto - $importe;

            // Snapshot del flag al momento de cotizar; default gravado.
            if ($i->producto?->incluye_igv ?? true) {
                $baseGravada += $tasa > 0 ? $importe / (1 + $tasa) : $importe;
            }
        }

        $this->update([
            'subtotal'        => round($subtotal, 2),
            'descuento_total' => round($descuentos, 2),
            'igv'             => round($baseGravada * $tasa, 2),
            'total'           => round($subtotal - $descuentos, 2),
        ]);
    }

    /** Etiqueta legible del estado para UI y mensajes. */
    public function getEstadoLabelAttribute(): string
    {
        return match ($this->estado) {
            self::ESTADO_VIGENTE    => 'Vigente',
            self::ESTADO_ACEPTADA   => 'Aceptada',
            self::ESTADO_RECHAZADA  => 'Rechazada',
            self::ESTADO_VENCIDA    => 'Vencida',
            self::ESTADO_CONVERTIDA => 'Convertida',
            self::ESTADO_ANULADA    => 'Anulada',
            default                 => $this->estado,
        };
    }
}
