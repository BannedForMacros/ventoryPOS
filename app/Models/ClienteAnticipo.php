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
        'turno_id', 'turno_devolucion_id',
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
    public function turno(): BelongsTo      { return $this->belongsTo(Turno::class); }
    public function turnoDevolucion(): BelongsTo { return $this->belongsTo(Turno::class, 'turno_devolucion_id'); }
    public function aplicaciones(): HasMany { return $this->hasMany(ClienteAnticipoAplicacion::class); }
    public function items(): HasMany        { return $this->hasMany(ClienteAnticipoItem::class); }
    public function cancelaciones(): HasMany { return $this->hasMany(ClienteAnticipoCancelacion::class, 'cliente_anticipo_id'); }

    public function scopeActivo(Builder $q): Builder             { return $q->where('estado', 'activo'); }
    public function scopeDeEmpresa(Builder $q, int $id): Builder { return $q->where('empresa_id', $id); }

    /**
     * Pasivo que representa el anticipo HOY.
     *
     * - 'monto': lo que queda por aplicar en soles.
     * - 'material' clásico (un solo producto): cantidad aún no entregada al
     *   precio CONGELADO que el cliente pagó (monto/cantidad). No se
     *   revaloriza al precio del día: el cliente pagó un precio concreto y ese
     *   se le respeta, suba o baje el producto después.
     * - 'material' multi-producto (pendiente por entregar del POS): el cliente
     *   YA PAGÓ esos productos a un precio concreto; se le debe exactamente lo
     *   pagado y no entregado (el saldo, congelado al precio de la venta). NO
     *   se revaloriza a precio del día: la deuda es de esa venta, de ese día.
     */
    public function valorPasivo(): float
    {
        if ($this->tipo_valorizacion === 'material') {
            // Multi-producto (venta POS con pendiente por entregar): pasivo =
            // saldo pagado no entregado, a precio congelado de la venta.
            if ($this->relationLoaded('items') ? $this->items->isNotEmpty() : $this->items()->exists()) {
                return (float) $this->saldo;
            }

            // Precio congelado por unidad = lo que el cliente pagó de verdad.
            // Ojo: si el saldo quedó desincronizado de la cantidad pendiente
            // (aplicaciones de versiones viejas), manda la cantidad pendiente:
            // se le debe la mercadería, no lo que diga el saldo.
            if ($this->producto && $this->cantidad_pendiente !== null && (float) $this->cantidad > 0) {
                $precioCongelado = (float) $this->monto / (float) $this->cantidad;

                return round((float) $this->cantidad_pendiente * $precioCongelado, 2);
            }
        }

        return (float) $this->saldo;
    }
}
