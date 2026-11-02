<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ficha técnica de un mantenimiento (preventivo o correctivo). El dinero es
 * el gasto al que pertenece; aquí van el km, el taller y los días parado.
 */
class VehiculoMantenimiento extends Model
{
    protected $table = 'vehiculo_mantenimientos';

    public const TIPOS = ['preventivo', 'correctivo'];

    protected $fillable = ['empresa_id', 'vehiculo_id', 'gasto_id', 'tipo', 'km', 'taller', 'detalle', 'dias_parado'];

    protected function casts(): array
    {
        return ['km' => 'decimal:1', 'dias_parado' => 'integer'];
    }

    public function vehiculo(): BelongsTo { return $this->belongsTo(Vehiculo::class); }
    public function gasto(): BelongsTo    { return $this->belongsTo(Gasto::class); }

    public function scopeActivo(Builder $q): Builder
    {
        return $q->whereHas('gasto');
    }
}
