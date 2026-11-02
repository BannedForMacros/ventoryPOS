<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un viaje cubierto por una liquidación: así no se paga dos veces. */
class FlotaLiquidacionViaje extends Model
{
    protected $table = 'flota_liquidacion_viajes';

    public $timestamps = false;

    protected $fillable = ['flota_liquidacion_id', 'viaje_id', 'vehiculo_id', 'rol', 'unidades', 'monto'];

    protected function casts(): array
    {
        return ['unidades' => 'decimal:2', 'monto' => 'decimal:2'];
    }

    public function liquidacion(): BelongsTo { return $this->belongsTo(FlotaLiquidacion::class, 'flota_liquidacion_id'); }
    public function viaje(): BelongsTo       { return $this->belongsTo(Viaje::class); }
}
