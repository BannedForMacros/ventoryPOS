<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DescuentoConcepto extends Model
{
    protected $table = 'descuento_conceptos';

    protected $fillable = ['empresa_id', 'nombre', 'requiere_aprobacion', 'activo'];

    protected function casts(): array
    {
        return [
            'requiere_aprobacion' => 'boolean',
            'activo'              => 'boolean',
        ];
    }

    public function empresa(): BelongsTo  { return $this->belongsTo(Empresa::class); }
    public function logs(): HasMany       { return $this->hasMany(DescuentoLog::class); }

    public function scopeActivo(Builder $q): Builder              { return $q->where('activo', true); }
    public function scopeDeEmpresa(Builder $q, int $id): Builder  { return $q->where('empresa_id', $id); }
    public function scopeRequiereAprobacion(Builder $q): Builder  { return $q->where('requiere_aprobacion', true); }
    public function scopeLibres(Builder $q): Builder              { return $q->where('requiere_aprobacion', false); }
}
