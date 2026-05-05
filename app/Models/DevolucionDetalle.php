<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DevolucionDetalle extends Model
{
    protected $table = 'devoluciones_detalle';

    protected $fillable = [
        'devolucion_id',
        'venta_item_id',
        'producto_id',
        'producto_unidad_id',
        'cantidad',
        'cantidad_base',
        'precio_unitario',
        'subtotal',
        'estado_producto',
        'restock',
        'motivo_id',
        'observacion',
    ];

    protected function casts(): array
    {
        return [
            'cantidad'        => 'decimal:4',
            'cantidad_base'   => 'decimal:4',
            'precio_unitario' => 'decimal:2',
            'subtotal'        => 'decimal:2',
            'restock'         => 'boolean',
        ];
    }

    public function devolucion(): BelongsTo    { return $this->belongsTo(Devolucion::class); }
    public function ventaItem(): BelongsTo     { return $this->belongsTo(VentaItem::class); }
    public function producto(): BelongsTo      { return $this->belongsTo(Producto::class); }
    public function productoUnidad(): BelongsTo { return $this->belongsTo(ProductoUnidad::class); }
    public function motivo(): BelongsTo        { return $this->belongsTo(DevolucionMotivo::class, 'motivo_id'); }
}
