<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalidaTipo extends Model
{
    protected $table = 'salida_tipos';

    protected $fillable = [
        'empresa_id',
        'nombre',
        'slug',
        'es_sistema',
        'activo',
        'orden',
    ];

    protected function casts(): array
    {
        return [
            'es_sistema' => 'boolean',
            'activo'     => 'boolean',
        ];
    }

    public function empresa(): BelongsTo { return $this->belongsTo(Empresa::class); }
    public function salidas(): HasMany   { return $this->hasMany(Salida::class); }

    public function scopeActivo(Builder $q): Builder        { return $q->where('activo', true); }
    public function scopeDeEmpresa(Builder $q, int $id): Builder { return $q->where('empresa_id', $id); }
}
