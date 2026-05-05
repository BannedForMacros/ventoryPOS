<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DevolucionPago extends Model
{
    protected $table = 'devolucion_pagos';

    protected $fillable = [
        'devolucion_id',
        'metodo_pago_id',
        'cuenta_metodo_pago_id',
        'monto',
        'referencia',
    ];

    protected function casts(): array
    {
        return [
            'monto' => 'decimal:2',
        ];
    }

    public function devolucion(): BelongsTo  { return $this->belongsTo(Devolucion::class); }
    public function metodoPago(): BelongsTo  { return $this->belongsTo(MetodoPago::class); }
}
