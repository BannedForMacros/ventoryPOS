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
}
