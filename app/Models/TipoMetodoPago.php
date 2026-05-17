<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Catálogo global de tipos de método de pago. Reemplaza el enum nativo PG.
 *
 * No tiene `empresa_id`: es un catálogo del sistema. Si un día una empresa
 * necesita un tipo exclusivo, se evalúa hacerlo tenant-scoped, pero por ahora
 * todos los tipos son comunes (efectivo, yape, tarjeta, etc.).
 */
class TipoMetodoPago extends Model
{
    protected $table = 'tipos_metodo_pago';

    protected $fillable = [
        'slug',
        'nombre',
        'icono',
        'admite_vuelto_default',
        'requiere_referencia',
        'orden',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'admite_vuelto_default' => 'boolean',
            'requiere_referencia'   => 'boolean',
            'activo'                => 'boolean',
        ];
    }

    public function metodosPago(): HasMany
    {
        return $this->hasMany(MetodoPago::class, 'tipo_id');
    }

    public function scopeActivo(Builder $q): Builder
    {
        return $q->where('activo', true);
    }

    /** Helper para queries antiguas que comparaban con string. */
    public static function idDeSlug(string $slug): ?int
    {
        return static::where('slug', $slug)->value('id');
    }
}
