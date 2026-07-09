<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CuentaMovimiento extends Model
{
    protected $fillable = [
        'empresa_id', 'cuenta_id', 'user_id', 'fecha', 'tipo',
        'monto', 'descripcion', 'ref_tipo', 'ref_id',
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

    public function empresa(): BelongsTo { return $this->belongsTo(Empresa::class); }
    public function cuenta(): BelongsTo  { return $this->belongsTo(Cuenta::class); }
    public function user(): BelongsTo    { return $this->belongsTo(User::class); }

    public function scopeDeEmpresa(Builder $q, int $id): Builder { return $q->where('empresa_id', $id); }
}
