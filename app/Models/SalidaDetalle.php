<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalidaDetalle extends Model
{
    protected $table = 'salidas_detalle';

    protected $fillable = [
        'salida_id',
        'producto_id',
        'unidad_medida_id',
        'cantidad',
        'factor_conversion',
        'cantidad_base',
        'costo_unitario',
        'subtotal',
        'observacion',
    ];

    protected function casts(): array
    {
        return [
            'cantidad'          => 'decimal:4',
            'factor_conversion' => 'decimal:4',
            'cantidad_base'     => 'decimal:4',
            'costo_unitario'    => 'decimal:4',
            'subtotal'          => 'decimal:2',
        ];
    }

    public function salida(): BelongsTo       { return $this->belongsTo(Salida::class); }
    public function producto(): BelongsTo     { return $this->belongsTo(Producto::class); }
    public function unidadMedida(): BelongsTo { return $this->belongsTo(UnidadMedida::class); }
}
