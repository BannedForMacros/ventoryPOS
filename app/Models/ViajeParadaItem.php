<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Qué y cuánto lleva una parada. El peso se congela al armar el viaje. */
class ViajeParadaItem extends Model
{
    protected $table = 'viaje_parada_items';

    protected $fillable = [
        'viaje_parada_id', 'cliente_anticipo_item_id', 'venta_item_id', 'producto_id',
        'producto_nombre', 'unidad_nombre', 'cantidad', 'factor_conversion', 'peso_kg',
    ];

    protected function casts(): array
    {
        return ['cantidad' => 'decimal:4', 'factor_conversion' => 'decimal:4', 'peso_kg' => 'decimal:3'];
    }

    public function parada(): BelongsTo       { return $this->belongsTo(ViajeParada::class, 'viaje_parada_id'); }
    public function anticipoItem(): BelongsTo { return $this->belongsTo(ClienteAnticipoItem::class, 'cliente_anticipo_item_id'); }
    public function producto(): BelongsTo     { return $this->belongsTo(Producto::class); }
}
