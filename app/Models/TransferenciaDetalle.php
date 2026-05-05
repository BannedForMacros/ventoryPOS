<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransferenciaDetalle extends Model
{
    protected $table = 'transferencias_detalle';

    protected $fillable = [
        'transferencia_id',
        'producto_id',
        'unidad_medida_id',
        'cantidad_enviada',
        'factor_conversion',
        'cantidad_base_enviada',
        'cantidad_recibida',
        'cantidad_base_recibida',
        'diferencia_base',
        'costo_unitario',
        'observacion',
    ];

    protected function casts(): array
    {
        return [
            'cantidad_enviada'       => 'decimal:4',
            'factor_conversion'      => 'decimal:4',
            'cantidad_base_enviada'  => 'decimal:4',
            'cantidad_recibida'      => 'decimal:4',
            'cantidad_base_recibida' => 'decimal:4',
            'diferencia_base'        => 'decimal:4',
            'costo_unitario'         => 'decimal:4',
        ];
    }

    public function transferencia(): BelongsTo  { return $this->belongsTo(Transferencia::class); }
    public function producto(): BelongsTo       { return $this->belongsTo(Producto::class); }
    public function unidadMedida(): BelongsTo   { return $this->belongsTo(UnidadMedida::class); }
}
