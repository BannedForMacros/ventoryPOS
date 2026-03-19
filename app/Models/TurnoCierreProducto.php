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
    ];

    public function turno(): BelongsTo    { return $this->belongsTo(Turno::class); }
    public function producto(): BelongsTo { return $this->belongsTo(Producto::class); }
}
