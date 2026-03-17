<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class MetodoPagoCuenta extends Model
{
    protected $table = 'metodo_pago_cuentas';

    protected $fillable = [
        'metodo_pago_id',
        'nombre',
        'numero_cuenta',
        'banco',
        'cci',
        'titular',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function metodoPago(): BelongsTo
    {
        return $this->belongsTo(MetodoPago::class);
    }

    public function scopeActivo(Builder $query): Builder
    {
        return $query->where('activo', true);
    }
}
