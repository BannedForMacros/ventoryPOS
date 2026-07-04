<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BalanceDiarioItem extends Model
{
    protected $fillable = [
        'balance_diario_id', 'seccion', 'categoria', 'descripcion',
        'ref_tipo', 'ref_id', 'monto', 'es_manual', 'conciliado', 'orden',
    ];

    protected function casts(): array
    {
        return [
            'monto'      => 'decimal:2',
            'es_manual'  => 'boolean',
            'conciliado' => 'boolean',
        ];
    }

    public function balance(): BelongsTo { return $this->belongsTo(BalanceDiario::class, 'balance_diario_id'); }
}
