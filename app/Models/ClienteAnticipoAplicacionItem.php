<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Detalle de una entrega parcial de un anticipo multi-producto: cuánto se
 * entregó de cada ítem en esa aplicación (con la fecha de la aplicación).
 */
class ClienteAnticipoAplicacionItem extends Model
{
    protected $table = 'cliente_anticipo_aplicacion_items';

    protected $fillable = [
        'cliente_anticipo_aplicacion_id', 'cliente_anticipo_item_id', 'cantidad',
    ];

    protected function casts(): array
    {
        return ['cantidad' => 'decimal:4'];
    }

    public function aplicacion(): BelongsTo { return $this->belongsTo(ClienteAnticipoAplicacion::class, 'cliente_anticipo_aplicacion_id'); }
    public function item(): BelongsTo       { return $this->belongsTo(ClienteAnticipoItem::class, 'cliente_anticipo_item_id'); }
}
