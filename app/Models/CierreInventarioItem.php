<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CierreInventarioItem extends Model
{
    protected $table = 'cierres_inventario_items';

    protected $fillable = [
        'cierre_id',
        'producto_id',
        'stock_sistema',
        'stock_declarado',
        'diferencia',
        'observacion',
    ];

    protected function casts(): array
    {
        return [
            'stock_sistema'   => 'decimal:4',
            'stock_declarado' => 'decimal:4',
            'diferencia'      => 'decimal:4',
        ];
    }

    public function cierre(): BelongsTo  { return $this->belongsTo(CierreInventario::class, 'cierre_id'); }
    public function producto(): BelongsTo { return $this->belongsTo(Producto::class); }
}
