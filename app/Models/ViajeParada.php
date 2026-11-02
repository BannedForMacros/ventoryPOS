<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Una venta que lleva el viaje.
 *
 *  - modo 'pendiente': mercadería por entregar (anticipo material de la venta).
 *    Al entregarla sale del stock con la misma entrega de Despachos.
 *  - modo 'directa':   la venta ya sacó su stock al venderse (p. ej. "puesto en
 *    obra" con la placa elegida en el POS, o un flete). Nace entregada.
 */
class ViajeParada extends Model
{
    protected $table = 'viaje_paradas';

    protected $fillable = [
        'viaje_id', 'venta_id', 'cliente_anticipo_id', 'orden', 'modo', 'estado',
        'cliente_anticipo_aplicacion_id', 'entregado_at', 'motivo',
    ];

    protected function casts(): array
    {
        return ['entregado_at' => 'datetime', 'orden' => 'integer'];
    }

    public function viaje(): BelongsTo    { return $this->belongsTo(Viaje::class); }
    public function venta(): BelongsTo    { return $this->belongsTo(Venta::class); }
    public function anticipo(): BelongsTo { return $this->belongsTo(ClienteAnticipo::class, 'cliente_anticipo_id'); }
    public function entrega(): BelongsTo  { return $this->belongsTo(ClienteAnticipoAplicacion::class, 'cliente_anticipo_aplicacion_id'); }
    public function items(): HasMany      { return $this->hasMany(ViajeParadaItem::class)->orderBy('id'); }
    public function guia(): HasOne        { return $this->hasOne(Guia::class)->latestOfMany(); }

    public function pesoKg(): float
    {
        return round((float) $this->items->sum(fn ($i) => (float) $i->peso_kg), 3);
    }
}
