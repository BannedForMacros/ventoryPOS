<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntradaPago extends Model
{
    protected $fillable = [
        'entrada_id', 'user_id', 'metodo_pago_id', 'cuenta_id',
        'proveedor_adelanto_id', 'fecha', 'monto', 'referencia', 'observacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date:Y-m-d',
            'monto' => 'decimal:2',
        ];
    }

    public function entrada(): BelongsTo    { return $this->belongsTo(Entrada::class); }
    public function user(): BelongsTo       { return $this->belongsTo(User::class); }
    public function metodoPago(): BelongsTo { return $this->belongsTo(MetodoPago::class, 'metodo_pago_id'); }
    public function cuenta(): BelongsTo     { return $this->belongsTo(Cuenta::class); }
    public function adelanto(): BelongsTo   { return $this->belongsTo(ProveedorAdelanto::class, 'proveedor_adelanto_id'); }
}
