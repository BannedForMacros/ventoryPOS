<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeudaPago extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'deuda_id', 'user_id', 'metodo_pago_id', 'cuenta_id',
        'fecha', 'tipo', 'monto', 'observacion',
        // "Afecta caja": turno cuya caja movió el efectivo de esta cuota.
        'turno_id',
        // Vínculo de una compensación entre una deuda por pagar y otra por cobrar.
        'compensacion_grupo_id', 'compensacion_deuda_id',
        // Cruces con otros módulos (deuda vinculada a un tercero):
        //  - cliente_anticipo_id: amortización cobrada del anticipo del cliente.
        //  - compensacion_venta_id / compensacion_entrada_id: compensada contra
        //    una venta CxC o una compra CxP del mismo tercero. Sin tesorería.
        'cliente_anticipo_id', 'compensacion_venta_id', 'compensacion_entrada_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date:Y-m-d',
            'monto' => 'decimal:2',
        ];
    }

    public function deuda(): BelongsTo      { return $this->belongsTo(Deuda::class); }
    public function user(): BelongsTo       { return $this->belongsTo(User::class); }
    public function metodoPago(): BelongsTo { return $this->belongsTo(MetodoPago::class, 'metodo_pago_id'); }
    public function cuenta(): BelongsTo     { return $this->belongsTo(Cuenta::class); }
    public function turno(): BelongsTo      { return $this->belongsTo(Turno::class); }
    public function compensacionDeuda(): BelongsTo { return $this->belongsTo(Deuda::class, 'compensacion_deuda_id'); }
    public function compensacionVenta(): BelongsTo   { return $this->belongsTo(Venta::class, 'compensacion_venta_id'); }
    public function compensacionEntrada(): BelongsTo { return $this->belongsTo(Entrada::class, 'compensacion_entrada_id'); }
    public function anticipo(): BelongsTo            { return $this->belongsTo(ClienteAnticipo::class, 'cliente_anticipo_id'); }

    /** Movimiento que cruza con otro módulo (anticipo/CxC/CxP): no se edita, solo se anula. */
    public function esCruce(): bool
    {
        return !empty($this->cliente_anticipo_id)
            || !empty($this->compensacion_venta_id)
            || !empty($this->compensacion_entrada_id);
    }
}
