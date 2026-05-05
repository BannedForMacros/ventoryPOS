<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TurnoCierreProducto extends Model
{
    protected $table = 'turno_cierre_productos';

    protected $fillable = [
        'turno_id', 'producto_id', 'producto_nombre',
        'cantidad_vendida', 'precio_unitario', 'total',
        'stock_final',
    ];

    protected function casts(): array
    {
        return [
            'cantidad_vendida' => 'decimal:3',
            'precio_unitario'  => 'decimal:2',
            'total'            => 'decimal:2',
            'stock_final'      => 'decimal:4',
        ];
    }

    public function turno(): BelongsTo    { return $this->belongsTo(Turno::class); }
    public function producto(): BelongsTo { return $this->belongsTo(Producto::class); }
}
