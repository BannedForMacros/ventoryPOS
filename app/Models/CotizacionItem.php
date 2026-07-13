<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Línea de una cotización: producto con precio congelado y descuento por
 * unidad. Guarda snapshot de nombres (el catálogo puede cambiar después).
 */
class CotizacionItem extends Model
{
    protected $table = 'cotizacion_items';

    protected $fillable = [
        'cotizacion_id', 'producto_id', 'producto_unidad_id',
        'producto_nombre', 'unidad_nombre',
        'cantidad', 'precio_unitario', 'descuento_item', 'subtotal',
    ];

    protected function casts(): array
    {
        return [
            'cantidad'        => 'decimal:4',
            'precio_unitario' => 'decimal:2',
            'descuento_item'  => 'decimal:2',
            'subtotal'        => 'decimal:2',
        ];
    }

    public function cotizacion(): BelongsTo     { return $this->belongsTo(Cotizacion::class); }
    public function producto(): BelongsTo       { return $this->belongsTo(Producto::class); }
    public function productoUnidad(): BelongsTo { return $this->belongsTo(ProductoUnidad::class); }
}
