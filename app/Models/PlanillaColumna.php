<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Columna de dinero de la planilla de caja de una empresa (ej. "Efectivo",
 * "Depósitos", "Yape / Cuenta BCP"). Cada medio de pago se asigna a una;
 * varios medios pueden sumar en la misma.
 */
class PlanillaColumna extends Model
{
    protected $table = 'planilla_columnas';

    protected $fillable = ['empresa_id', 'nombre', 'orden'];

    public function empresa(): BelongsTo      { return $this->belongsTo(Empresa::class); }
    public function metodosPago(): HasMany    { return $this->hasMany(MetodoPago::class, 'planilla_columna_id'); }

    public function scopeDeEmpresa(Builder $q, int $id): Builder { return $q->where('empresa_id', $id); }
}
