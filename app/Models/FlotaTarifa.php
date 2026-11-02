<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cuánto se paga al chofer y a cada ayudante por cada `por_cantidad` unidades
 * (base) repartidas de un producto. Sin producto = la tarifa general.
 * Ej. JH: S/ 12.50 por millar (por_cantidad 1000) al chofer y a cada ayudante.
 */
class FlotaTarifa extends Model
{
    protected $table = 'flota_tarifas';

    protected $fillable = ['empresa_id', 'producto_id', 'por_cantidad', 'monto_chofer', 'monto_ayudante'];

    protected function casts(): array
    {
        return ['por_cantidad' => 'decimal:2', 'monto_chofer' => 'decimal:2', 'monto_ayudante' => 'decimal:2'];
    }

    public function producto(): BelongsTo { return $this->belongsTo(Producto::class); }
}
