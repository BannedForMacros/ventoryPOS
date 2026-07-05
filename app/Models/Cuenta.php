<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Builder;

class Cuenta extends Model
{
    protected $fillable = [
        'empresa_id',
        'nombre',
        'numero_cuenta',
        'banco',
        'cci',
        'titular',
        'es_efectivo',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo'      => 'boolean',
            'es_efectivo' => 'boolean',
        ];
    }

    public function movimientos(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CuentaMovimiento::class);
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function metodosPago(): BelongsToMany
    {
        return $this->belongsToMany(MetodoPago::class, 'cuenta_metodo_pago');
    }

    public function scopeActivo(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    public function scopeDeEmpresa(Builder $query, int $empresaId): Builder
    {
        return $query->where('empresa_id', $empresaId);
    }
}
