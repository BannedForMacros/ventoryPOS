<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Modulo extends Model
{
    protected $fillable = [
        'padre_id',
        'nombre',
        'slug',
        'icono',
        'ruta',
        'orden',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'orden'  => 'integer',
        ];
    }

    public function padre(): BelongsTo
    {
        return $this->belongsTo(Modulo::class, 'padre_id');
    }

    public function hijos(): HasMany
    {
        return $this->hasMany(Modulo::class, 'padre_id')->orderBy('orden');
    }

    public function permisos(): HasMany
    {
        return $this->hasMany(Permiso::class);
    }

    public function scopeRaiz(Builder $query): Builder
    {
        return $query->whereNull('padre_id');
    }

    public function scopeActivo(Builder $query): Builder
    {
        return $query->where('activo', true);
    }
}
