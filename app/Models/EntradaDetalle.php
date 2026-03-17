<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntradaDetalle extends Model
{
    protected $table = 'entradas_detalle';

    protected $fillable = [
        'entrada_id',
        'producto_id',
        'unidad_medida_id',
        'cantidad',
        'factor_conversion',
        'cantidad_base',
        'precio_costo',
        'subtotal',
    ];

    protected function casts(): array
    {
        return [
            'cantidad'          => 'decimal:4',
            'factor_conversion' => 'decimal:4',
            'cantidad_base'     => 'decimal:4',
            'precio_costo'      => 'decimal:4',
            'subtotal'          => 'decimal:2',
        ];
    }

    public function entrada(): BelongsTo
    {
        return $this->belongsTo(Entrada::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function unidadMedida(): BelongsTo
    {
        return $this->belongsTo(UnidadMedida::class);
    }
}
