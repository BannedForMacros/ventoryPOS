<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductoUnidad extends Model
{
    protected $table = 'producto_unidades';

    protected $fillable = [
        'producto_id',
        'unidad_medida_id',
        'es_base',
        'factor_conversion',
        'tipo_precio',
        'precio_venta',
        'precio_costo',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'es_base'           => 'boolean',
            'factor_conversion' => 'decimal:4',
            'precio_venta'      => 'decimal:2',
            'precio_costo'      => 'decimal:2',
            'activo'            => 'boolean',
        ];
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function unidadMedida(): BelongsTo
    {
        return $this->belongsTo(UnidadMedida::class);
    }

    public function scopeBase(Builder $query): Builder
    {
        return $query->where('es_base', true);
    }

    public function scopeActivo(Builder $query): Builder
    {
        return $query->where('activo', true);
    }
}
