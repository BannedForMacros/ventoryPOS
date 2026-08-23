<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Deuda extends Model
{
    public const DIRECCION_POR_PAGAR  = 'por_pagar';
    public const DIRECCION_POR_COBRAR = 'por_cobrar';

    protected $fillable = [
        'empresa_id', 'user_id', 'direccion', 'tipo', 'nombre',
        'monto_original', 'saldo', 'fecha_inicio', 'fecha_vencimiento',
        'estado', 'observacion',
        'moneda', 'tipo_cambio', 'monto_moneda',
        // "Afecta caja": turno cuya caja recibió/entregó el desembolso inicial.
        'turno_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha_inicio'      => 'date:Y-m-d',
            'fecha_vencimiento' => 'date:Y-m-d',
            'monto_original'    => 'decimal:2',
            'saldo'             => 'decimal:2',
            'tipo_cambio'       => 'decimal:6',
            'monto_moneda'      => 'decimal:2',
        ];
    }

    public function empresa(): BelongsTo { return $this->belongsTo(Empresa::class); }
    public function user(): BelongsTo    { return $this->belongsTo(User::class); }
    public function pagos(): HasMany     { return $this->hasMany(DeudaPago::class); }

    /**
     * Desembolso inicial registrado en tesorería (solo si la deuda afectó caja).
     */
    public function desembolso(): HasOne
    {
        return $this->hasOne(CuentaMovimiento::class, 'ref_id')
            ->where('ref_tipo', 'deuda');
    }

    /**
     * Recalcula el saldo desde cero usando el monto original y los movimientos
     * activos. Útil para corregir desfases cuando se eliminan/ajustan pagos.
     */
    public function recalcularSaldo(): void
    {
        if ($this->estado === 'anulada') {
            return;
        }

        $incrementos = (float) $this->pagos()->where('tipo', 'incremento')->sum('monto');
        $amortizaciones = (float) $this->pagos()->whereIn('tipo', ['amortizacion', 'compensacion'])->sum('monto');
        $nuevo = max(0, (float) $this->monto_original + $incrementos - $amortizaciones);

        $this->update([
            'saldo'  => round($nuevo, 2),
            'estado' => $nuevo <= 0.01 ? 'pagada' : 'activa',
        ]);
    }

    public function scopeActiva(Builder $q): Builder             { return $q->where('estado', 'activa'); }
    public function scopePorPagar(Builder $q): Builder           { return $q->where('direccion', self::DIRECCION_POR_PAGAR); }
    public function scopePorCobrar(Builder $q): Builder          { return $q->where('direccion', self::DIRECCION_POR_COBRAR); }
    public function scopeDeEmpresa(Builder $q, int $id): Builder { return $q->where('empresa_id', $id); }
}
