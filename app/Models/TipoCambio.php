<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Tipo de cambio contable SBS del día, congelado (fuente: Decolecta).
 * Una fila por (fecha, moneda). Evita reconvertir montos históricos.
 */
class TipoCambio extends Model
{
    protected $table = 'tipos_cambio';

    protected $fillable = [
        'fecha', 'moneda', 'tasa', 'fuente', 'raw',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date:Y-m-d',
            'tasa'  => 'decimal:6',
            'raw'   => 'array',
        ];
    }
}
