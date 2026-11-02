<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cuántas unidades de un producto entran en un camión por VOLUMEN (tolva o
 * baranda). El planificador usa el menor entre este límite y el de peso: en el
 * ladrillo de techo el camión se llena antes de llegar al peso.
 */
class VehiculoCapacidad extends Model
{
    protected $table = 'vehiculo_capacidades';

    protected $fillable = ['vehiculo_id', 'producto_id', 'max_unidades'];

    protected function casts(): array
    {
        return ['max_unidades' => 'decimal:2'];
    }

    public function vehiculo(): BelongsTo { return $this->belongsTo(Vehiculo::class); }
    public function producto(): BelongsTo { return $this->belongsTo(Producto::class); }
}
