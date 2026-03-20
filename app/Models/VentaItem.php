<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VentaItem extends Model
{
    protected $table = 'venta_items';

    protected $fillable = [
        'venta_id', 'producto_id', 'producto_unidad_id',
        'producto_nombre', 'unidad_nombre',
        'cantidad', 'factor_conversion', 'cantidad_base',
        'precio_unitario', 'precio_original',
        'descuento_item', 'descuento_concepto_id',
        'subtotal', 'incluye_igv',
    ];

    protected function casts(): array
    {
        return [
            'cantidad'          => 'decimal:4',
            'factor_conversion' => 'decimal:4',
            'cantidad_base'     => 'decimal:4',
            'precio_unitario'   => 'decimal:2',
            'precio_original'   => 'decimal:2',
            'descuento_item'    => 'decimal:2',
            'subtotal'          => 'decimal:2',
            'incluye_igv'       => 'boolean',
        ];
    }

    public function venta(): BelongsTo            { return $this->belongsTo(Venta::class); }
    public function producto(): BelongsTo         { return $this->belongsTo(Producto::class); }
    public function productoUnidad(): BelongsTo   { return $this->belongsTo(ProductoUnidad::class); }
    public function descuentoConcepto(): BelongsTo { return $this->belongsTo(DescuentoConcepto::class); }

    public function subtotalCalculado(): float
    {
        return round(((float) $this->precio_unitario - (float) $this->descuento_item) * (float) $this->cantidad, 2);
    }
}
