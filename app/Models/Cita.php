<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cita / reserva. Cabecera de UNA visita al local que puede involucrar
 * multiples servicios (ver CitaItem).
 *
 * Vinculo con venta:
 *   - Mientras no se cobra: venta_id = null.
 *   - Cuando se "Completa y cobra": CitaService crea una Venta nueva con sus
 *     items (precios actuales + posibles ajustes) y guarda venta_id aqui.
 *   - Los CitaItems quedan intactos como historico de lo reservado.
 */
class Cita extends Model
{
    protected $table = 'citas';

    public const ESTADO_PROGRAMADA  = 'programada';
    public const ESTADO_CONFIRMADA  = 'confirmada';
    public const ESTADO_EN_ATENCION = 'en_atencion';
    public const ESTADO_COMPLETADA  = 'completada';
    public const ESTADO_NO_ASISTIO  = 'no_asistio';
    public const ESTADO_CANCELADA   = 'cancelada';

    public const ESTADOS = [
        self::ESTADO_PROGRAMADA, self::ESTADO_CONFIRMADA, self::ESTADO_EN_ATENCION,
        self::ESTADO_COMPLETADA, self::ESTADO_NO_ASISTIO, self::ESTADO_CANCELADA,
    ];

    protected $fillable = [
        'empresa_id', 'local_id', 'cliente_id', 'profesional_id', 'created_by',
        'numero', 'fecha_hora', 'duracion_min', 'estado', 'observaciones',
        'sujeto_nombre', 'sujeto_descripcion',
        'venta_id',
        'confirmada_at', 'iniciada_at', 'completada_at', 'cancelada_at',
        'motivo_cancelacion', 'recordatorio_enviado_at',
    ];

    protected function casts(): array
    {
        return [
            'fecha_hora'              => 'datetime',
            'duracion_min'            => 'integer',
            'confirmada_at'           => 'datetime',
            'iniciada_at'             => 'datetime',
            'completada_at'           => 'datetime',
            'cancelada_at'            => 'datetime',
            'recordatorio_enviado_at' => 'datetime',
        ];
    }

    // ── Relaciones ───────────────────────────────────────────────────────

    public function empresa(): BelongsTo     { return $this->belongsTo(Empresa::class); }
    public function local(): BelongsTo       { return $this->belongsTo(Local::class); }
    public function cliente(): BelongsTo     { return $this->belongsTo(Cliente::class); }
    public function profesional(): BelongsTo { return $this->belongsTo(User::class, 'profesional_id'); }
    public function creador(): BelongsTo     { return $this->belongsTo(User::class, 'created_by'); }
    public function venta(): BelongsTo       { return $this->belongsTo(Venta::class); }
    public function items(): HasMany         { return $this->hasMany(CitaItem::class)->orderBy('orden')->orderBy('id'); }

    // ── Scopes ──────────────────────────────────────────────────────────

    public function scopeDeEmpresa(Builder $q, int $id): Builder { return $q->where('empresa_id', $id); }
    public function scopeDelLocal(Builder $q, int $id): Builder  { return $q->where('local_id', $id); }
    public function scopeDelProfesional(Builder $q, int $userId): Builder { return $q->where('profesional_id', $userId); }
    public function scopeDelCliente(Builder $q, int $id): Builder { return $q->where('cliente_id', $id); }

    public function scopeProgramadas(Builder $q): Builder { return $q->where('estado', self::ESTADO_PROGRAMADA); }
    public function scopeConfirmadas(Builder $q): Builder { return $q->where('estado', self::ESTADO_CONFIRMADA); }
    public function scopeEnAtencion(Builder $q): Builder  { return $q->where('estado', self::ESTADO_EN_ATENCION); }
    public function scopeCompletadas(Builder $q): Builder { return $q->where('estado', self::ESTADO_COMPLETADA); }
    public function scopeCanceladas(Builder $q): Builder  { return $q->where('estado', self::ESTADO_CANCELADA); }
    public function scopeNoAsistio(Builder $q): Builder   { return $q->where('estado', self::ESTADO_NO_ASISTIO); }

    /** Citas activas: las que aun pueden tener acciones (no canceladas, no no-show, no completadas). */
    public function scopeActivas(Builder $q): Builder
    {
        return $q->whereIn('estado', [
            self::ESTADO_PROGRAMADA, self::ESTADO_CONFIRMADA, self::ESTADO_EN_ATENCION,
        ]);
    }

    public function scopeDelDia(Builder $q, ?Carbon $dia = null): Builder
    {
        $dia = $dia ?? Carbon::today();
        return $q->whereBetween('fecha_hora', [$dia->copy()->startOfDay(), $dia->copy()->endOfDay()]);
    }

    public function scopeEntreFechas(Builder $q, Carbon $desde, Carbon $hasta): Builder
    {
        return $q->whereBetween('fecha_hora', [$desde, $hasta]);
    }

    // ── Helpers de estado ───────────────────────────────────────────────

    public function esProgramada(): bool { return $this->estado === self::ESTADO_PROGRAMADA; }
    public function esConfirmada(): bool { return $this->estado === self::ESTADO_CONFIRMADA; }
    public function esEnAtencion(): bool { return $this->estado === self::ESTADO_EN_ATENCION; }
    public function esCompletada(): bool { return $this->estado === self::ESTADO_COMPLETADA; }
    public function esCancelada(): bool  { return $this->estado === self::ESTADO_CANCELADA; }
    public function esNoAsistio(): bool  { return $this->estado === self::ESTADO_NO_ASISTIO; }

    /** Indica si la cita aun puede modificarse o transicionar de estado. */
    public function estaActiva(): bool
    {
        return in_array($this->estado, [
            self::ESTADO_PROGRAMADA, self::ESTADO_CONFIRMADA, self::ESTADO_EN_ATENCION,
        ], true);
    }

    public function fechaFin(): Carbon
    {
        return $this->fecha_hora->copy()->addMinutes((int) $this->duracion_min);
    }

    // ── Calculos derivados ─────────────────────────────────────────────

    public function calcularDuracionTotal(): int
    {
        return (int) $this->items()->sum('duracion_min');
    }

    public function calcularPrecioEstimado(): float
    {
        return (float) $this->items->sum(fn ($it) => (float) $it->precio_estimado * (float) $it->cantidad);
    }

    /**
     * Numero amigable: C-{empresa}-{secuencial}.
     * Generado al crear la cita en CitaService, no aqui.
     * Esta utilidad esta para uso del Service.
     */
    public static function generarNumero(int $empresaId): string
    {
        $count = static::where('empresa_id', $empresaId)->count() + 1;
        return 'C-' . str_pad((string) $empresaId, 3, '0', STR_PAD_LEFT)
             . '-' . str_pad((string) $count, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Etiqueta legible del estado para UI.
     */
    public function getEstadoLabelAttribute(): string
    {
        return match ($this->estado) {
            self::ESTADO_PROGRAMADA  => 'Programada',
            self::ESTADO_CONFIRMADA  => 'Confirmada',
            self::ESTADO_EN_ATENCION => 'En atención',
            self::ESTADO_COMPLETADA  => 'Completada',
            self::ESTADO_NO_ASISTIO  => 'No asistió',
            self::ESTADO_CANCELADA   => 'Cancelada',
            default                  => $this->estado,
        };
    }
}
