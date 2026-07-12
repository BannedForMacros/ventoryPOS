<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClienteAnticipo extends Model
{
    protected $fillable = [
        'empresa_id', 'cliente_id', 'user_id', 'metodo_pago_id', 'cuenta_id',
        'fecha', 'monto', 'saldo', 'tipo_valorizacion',
        'producto_id', 'cantidad', 'cantidad_pendiente',
        'estado', 'observacion',
        'moneda', 'tipo_cambio', 'monto_moneda',
        'venta_id', 'fecha_entrega_estimada',
    ];

    protected function casts(): array
    {
        return [
            'fecha'                  => 'date:Y-m-d',
            'monto'                  => 'decimal:2',
            'saldo'                  => 'decimal:2',
            'cantidad'               => 'decimal:4',
            'cantidad_pendiente'     => 'decimal:4',
            'tipo_cambio'            => 'decimal:6',
            'monto_moneda'           => 'decimal:2',
            'fecha_entrega_estimada' => 'date:Y-m-d',
        ];
    }

    public function empresa(): BelongsTo    { return $this->belongsTo(Empresa::class); }
    public function cliente(): BelongsTo    { return $this->belongsTo(Cliente::class); }
    public function user(): BelongsTo       { return $this->belongsTo(User::class); }
    public function metodoPago(): BelongsTo { return $this->belongsTo(MetodoPago::class, 'metodo_pago_id'); }
    public function cuenta(): BelongsTo     { return $this->belongsTo(Cuenta::class); }
    public function producto(): BelongsTo   { return $this->belongsTo(Producto::class); }
    public function venta(): BelongsTo      { return $this->belongsTo(Venta::class); }
    public function aplicaciones(): HasMany { return $this->hasMany(ClienteAnticipoAplicacion::class); }
    public function items(): HasMany        { return $this->hasMany(ClienteAnticipoItem::class); }

    public function scopeActivo(Builder $q): Builder             { return $q->where('estado', 'activo'); }
    public function scopeDeEmpresa(Builder $q, int $id): Builder { return $q->where('empresa_id', $id); }

    /**
     * Pasivo que representa el anticipo HOY.
     *
     * - 'monto': lo que queda por aplicar en soles.
     * - 'material': cantidad aún no entregada × precio de venta ACTUAL del
     *   producto (precio del día). Si el ladrillo subió, la deuda en
     *   mercadería vale más — exactamente como lo calcula el cliente en su
     *   Excel. Fallback al saldo en soles si el producto ya no existe.
     * - 'material' multi-producto (pendiente por entregar del POS): suma de
     *   los ítems pendientes, cada uno a precio del día de su presentación.
     */
    public function valorPasivoHoy(): float
    {
        if ($this->tipo_valorizacion === 'material') {
            // Multi-producto (venta POS con pendiente por entregar)
            if ($this->relationLoaded('items') ? $this->items->isNotEmpty() : $this->items()->exists()) {
                $this->loadMissing('items.unidad');

                return round($this->items->sum(fn (ClienteAnticipoItem $i) => $i->valorPasivoHoy()), 2);
            }

            if ($this->producto && $this->cantidad_pendiente !== null) {
                return round((float) $this->cantidad_pendiente * (float) $this->producto->precio_venta, 2);
            }
        }

        return (float) $this->saldo;
    }
}
