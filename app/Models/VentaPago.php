<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VentaPago extends Model
{
    protected $table = 'venta_pagos';

    protected $fillable = [
        'venta_id', 'metodo_pago_id', 'cuenta_metodo_pago_id',
        'monto', 'referencia', 'vuelto',
    ];

    protected function casts(): array
    {
        return [
            'monto'  => 'decimal:2',
            'vuelto' => 'decimal:2',
        ];
    }

    public function venta(): BelongsTo       { return $this->belongsTo(Venta::class); }
    public function metodoPago(): BelongsTo  { return $this->belongsTo(MetodoPago::class); }

    public function cuentaMetodoPago(): BelongsTo
    {
        return $this->belongsTo(Cuenta::class, 'cuenta_metodo_pago_id');
    }
}
