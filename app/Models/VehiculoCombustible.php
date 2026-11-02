<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ficha técnica de una carga de combustible. El DINERO es el gasto al que
 * pertenece (caja, tesorería, balance); aquí solo van km, galones y si se
 * llenó el tanque. Mientras el gasto esté eliminado, la carga no cuenta.
 */
class VehiculoCombustible extends Model
{
    protected $table = 'vehiculo_combustibles';

    protected $fillable = ['empresa_id', 'vehiculo_id', 'gasto_id', 'conductor_id', 'km', 'galones', 'tanque_lleno', 'grifo'];

    protected function casts(): array
    {
        return ['km' => 'decimal:1', 'galones' => 'decimal:3', 'tanque_lleno' => 'boolean'];
    }

    public function vehiculo(): BelongsTo  { return $this->belongsTo(Vehiculo::class); }
    public function gasto(): BelongsTo     { return $this->belongsTo(Gasto::class); }
    public function conductor(): BelongsTo { return $this->belongsTo(Conductor::class); }

    /** Solo las cargas cuyo gasto sigue activo (no eliminado). */
    public function scopeActivo(Builder $q): Builder
    {
        return $q->whereHas('gasto');
    }
}
