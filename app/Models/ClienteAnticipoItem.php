<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ítem de un anticipo material multi-producto ("pendiente por entregar" desde
 * el POS): una línea por producto de la venta que el cliente pagó pero aún no
 * se lleva. cantidad/cantidad_pendiente van en la UNIDAD VENDIDA; para mover
 * stock se multiplica por factor_conversion (unidades base).
 */
class ClienteAnticipoItem extends Model
{
    protected $fillable = [
        'cliente_anticipo_id', 'venta_item_id',
        'producto_id', 'producto_unidad_id', 'producto_nombre', 'unidad_nombre',
        'cantidad', 'factor_conversion', 'cantidad_pendiente', 'precio_unitario',
    ];

    protected function casts(): array
    {
        return [
            'cantidad'           => 'decimal:4',
            'factor_conversion'  => 'decimal:4',
            'cantidad_pendiente' => 'decimal:4',
            'precio_unitario'    => 'decimal:2',
        ];
    }

    public function anticipo(): BelongsTo  { return $this->belongsTo(ClienteAnticipo::class, 'cliente_anticipo_id'); }
    public function ventaItem(): BelongsTo { return $this->belongsTo(VentaItem::class, 'venta_item_id'); }
    public function producto(): BelongsTo  { return $this->belongsTo(Producto::class); }
    public function unidad(): BelongsTo    { return $this->belongsTo(ProductoUnidad::class, 'producto_unidad_id'); }

    /**
     * Pasivo de este ítem: pendiente × precio CONGELADO de la venta.
     *
     * El cliente pagó estos productos a un precio concreto y ese es el que se
     * le respeta: si el producto sube o baja después, la deuda con él no
     * cambia. NO se revaloriza al precio del día.
     */
    public function valorPasivo(): float
    {
        return round((float) $this->cantidad_pendiente * (float) $this->precio_unitario, 2);
    }
}
