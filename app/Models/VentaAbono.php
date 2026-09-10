<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VentaAbono extends Model
{
    protected $fillable = [
        'venta_id', 'user_id', 'turno_id', 'metodo_pago_id', 'cuenta_id',
        'fecha', 'monto', 'referencia', 'observacion',
        'moneda', 'tipo_cambio', 'monto_moneda',
        // Compensación CxC↔CxP: abono sin dinero, cancelado contra una compra.
        'compensacion_grupo_id', 'compensacion_entrada_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date:Y-m-d',
            'monto' => 'decimal:2',
            'tipo_cambio'  => 'decimal:6',
            'monto_moneda' => 'decimal:2',
        ];
    }

    public function venta(): BelongsTo      { return $this->belongsTo(Venta::class); }
    public function user(): BelongsTo       { return $this->belongsTo(User::class); }
    public function turno(): BelongsTo      { return $this->belongsTo(Turno::class); }
    public function metodoPago(): BelongsTo { return $this->belongsTo(MetodoPago::class, 'metodo_pago_id'); }
    public function cuenta(): BelongsTo     { return $this->belongsTo(Cuenta::class); }
    /** Compra contra la que se compensó este abono (sin movimiento de caja). */
    public function compensacionEntrada(): BelongsTo { return $this->belongsTo(Entrada::class, 'compensacion_entrada_id'); }

    public function esCompensacion(): bool { return !empty($this->compensacion_grupo_id); }
}
