<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProveedorAdelanto extends Model
{
    protected $fillable = [
        'empresa_id', 'proveedor_id', 'user_id', 'metodo_pago_id', 'cuenta_id', 'turno_id',
        'fecha', 'monto', 'saldo', 'estado', 'referencia', 'observacion',
        'moneda', 'tipo_cambio', 'monto_moneda',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date:Y-m-d',
            'monto' => 'decimal:2',
            'saldo' => 'decimal:2',
            'tipo_cambio'  => 'decimal:6',
            'monto_moneda' => 'decimal:2',
        ];
    }

    public function empresa(): BelongsTo    { return $this->belongsTo(Empresa::class); }
    public function proveedor(): BelongsTo  { return $this->belongsTo(Proveedor::class); }
    public function user(): BelongsTo       { return $this->belongsTo(User::class); }
    public function metodoPago(): BelongsTo { return $this->belongsTo(MetodoPago::class, 'metodo_pago_id'); }
    public function cuenta(): BelongsTo     { return $this->belongsTo(Cuenta::class); }
    public function turno(): BelongsTo      { return $this->belongsTo(Turno::class); }
    public function aplicaciones(): HasMany { return $this->hasMany(ProveedorAdelantoAplicacion::class); }

    public function scopeActivo(Builder $q): Builder             { return $q->where('estado', 'activo'); }
    public function scopeDeEmpresa(Builder $q, int $id): Builder { return $q->where('empresa_id', $id); }
}
