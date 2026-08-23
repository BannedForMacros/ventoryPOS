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
}
