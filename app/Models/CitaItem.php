<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Servicio reservado dentro de una cita. Patron snapshot:
 * el precio_estimado captura el valor al momento de agendar, NO se actualiza
 * automaticamente si despues cambia el catalogo. Eso permite mostrar en
 * reportes "se reservo por X, se cobro Y".
 */
class CitaItem extends Model
{
    protected $table = 'cita_items';

    protected $fillable = [
        'cita_id', 'producto_id', 'producto_unidad_id',
        'cantidad', 'duracion_min', 'precio_estimado',
        'observaciones', 'orden',
    ];

    protected function casts(): array
    {
        return [
            'cantidad'        => 'decimal:4',
            'duracion_min'    => 'integer',
            'precio_estimado' => 'decimal:2',
            'orden'           => 'integer',
        ];
    }

    public function cita(): BelongsTo           { return $this->belongsTo(Cita::class); }
    public function producto(): BelongsTo       { return $this->belongsTo(Producto::class); }
    public function productoUnidad(): BelongsTo { return $this->belongsTo(ProductoUnidad::class); }

    /** Subtotal estimado de esta linea (no del cobro real). */
    public function subtotalEstimado(): float
    {
        return (float) $this->cantidad * (float) $this->precio_estimado;
    }
}
