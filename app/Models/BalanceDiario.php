<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BalanceDiario extends Model
{
    protected $table = 'balances_diarios';

    protected $fillable = [
        'empresa_id', 'user_id', 'fecha', 'estado',
        'total_favor', 'total_contra', 'balance_neto',
        'balance_anterior', 'diferencia', 'gastos_dia', 'utilidad_real',
        'observacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha'            => 'date',
            'total_favor'      => 'decimal:2',
            'total_contra'     => 'decimal:2',
            'balance_neto'     => 'decimal:2',
            'balance_anterior' => 'decimal:2',
            'diferencia'       => 'decimal:2',
            'gastos_dia'       => 'decimal:2',
            'utilidad_real'    => 'decimal:2',
        ];
    }

    public function empresa(): BelongsTo { return $this->belongsTo(Empresa::class); }
    public function user(): BelongsTo    { return $this->belongsTo(User::class); }
    public function items(): HasMany     { return $this->hasMany(BalanceDiarioItem::class)->orderBy('seccion')->orderBy('orden'); }

    public function scopeConfirmado(Builder $q): Builder         { return $q->where('estado', 'confirmado'); }
    public function scopeDeEmpresa(Builder $q, int $id): Builder { return $q->where('empresa_id', $id); }

    public function esBorrador(): bool { return $this->estado === 'borrador'; }

    /**
     * Recalcula totales desde los items y deriva utilidad real:
     *   UTILIDAD REAL = (BALANCE HOY − BALANCE AYER) + GASTOS DEL DÍA
     * (un gasto operativo baja el patrimonio pero no es pérdida del negocio,
     * por eso se suma de vuelta — misma fórmula del Excel del cliente).
     */
    public function recalcularTotales(): void
    {
        $favor  = (float) $this->items()->where('seccion', 'favor')->sum('monto');
        $contra = (float) $this->items()->where('seccion', 'contra')->sum('monto');
        $neto   = round($favor - $contra, 2);

        $diferencia = $this->balance_anterior !== null
            ? round($neto - (float) $this->balance_anterior, 2)
            : null;

        $this->update([
            'total_favor'   => round($favor, 2),
            'total_contra'  => round($contra, 2),
            'balance_neto'  => $neto,
            'diferencia'    => $diferencia,
            'utilidad_real' => $diferencia !== null
                ? round($diferencia + (float) $this->gastos_dia, 2)
                : null,
        ]);
    }
}
