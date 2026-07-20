<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Retiro de efectivo de un turno (sangría / "Entrega a administración").
 *
 * NO es un gasto: es un traslado de custodia (del cajón de la cajera a
 * administración / Caja Grande). No genera movimiento de tesorería porque el
 * neto de la cuenta Efectivo de la empresa no cambia — solo cambia de manos.
 * Sí resta del efectivo esperado del turno (momento='turno') o registra la
 * entrega del efectivo final al cierre (momento='cierre').
 */
class TurnoRetiro extends Model
{
    public const CONCEPTO_ENTREGA_ADMIN = 'Entrega a administración';

    protected $fillable = [
        'empresa_id', 'turno_id', 'user_id', 'aprobado_por',
        'concepto', 'monto', 'momento', 'estado', 'observacion',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
        ];
    }

    public function empresa(): BelongsTo     { return $this->belongsTo(Empresa::class); }
    public function turno(): BelongsTo       { return $this->belongsTo(Turno::class); }
    public function user(): BelongsTo        { return $this->belongsTo(User::class); }
    public function aprobadoPor(): BelongsTo { return $this->belongsTo(User::class, 'aprobado_por'); }

    public function scopeDeEmpresa($q, int $id) { return $q->where('empresa_id', $id); }
}
