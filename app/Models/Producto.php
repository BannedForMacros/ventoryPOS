<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Producto extends Model
{
    protected $fillable = [
        'empresa_id',
        'categoria_id',
        'codigo',
        'nombre',
        'descripcion',
        'tipo',
        'tipo_precio',
        'precio_venta',
        'precio_costo',
        'imagen',
        'activo',
        'incluye_igv',
    ];

    protected function casts(): array
    {
        return [
            'precio_venta' => 'decimal:2',
            'precio_costo' => 'decimal:2',
            'activo'       => 'boolean',
            'incluye_igv'  => 'boolean',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    public function unidades(): HasMany
    {
        return $this->hasMany(ProductoUnidad::class);
    }

    public function unidadBase(): HasOne
    {
        return $this->hasOne(ProductoUnidad::class)->where('es_base', true);
    }

    public function esServicio(): bool
    {
        return $this->tipo === 'servicio';
    }

    public function esProductoFisico(): bool
    {
        return $this->tipo === 'producto';
    }

    public function scopeActivo(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    public function scopeDeEmpresa(Builder $query, int $empresaId): Builder
    {
        return $query->where('empresa_id', $empresaId);
    }

    public function scopeProductos(Builder $query): Builder
    {
        return $query->where('tipo', 'producto');
    }

    public function scopeServicios(Builder $query): Builder
    {
        return $query->where('tipo', 'servicio');
    }
}
