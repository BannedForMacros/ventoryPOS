<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Turno extends Model
{
    protected $fillable = [
        'empresa_id', 'local_id', 'caja_id', 'user_id', 'user_cierre_id',
        'monto_apertura', 'monto_caja_chica',
        'monto_cierre_declarado', 'monto_cierre_esperado', 'diferencia',
        'estado', 'fecha_apertura', 'fecha_cierre',
        'observacion_apertura', 'observacion_cierre',
    ];

    protected function casts(): array
    {
        return [
            'fecha_apertura'          => 'datetime',
            'fecha_cierre'            => 'datetime',
            'monto_apertura'          => 'decimal:2',
            'monto_caja_chica'        => 'decimal:2',
            'monto_cierre_declarado'  => 'decimal:2',
            'monto_cierre_esperado'   => 'decimal:2',
            'diferencia'              => 'decimal:2',
        ];
    }

    public function empresa(): BelongsTo        { return $this->belongsTo(Empresa::class); }
    public function local(): BelongsTo          { return $this->belongsTo(Local::class); }
    public function caja(): BelongsTo           { return $this->belongsTo(Caja::class); }
    public function user(): BelongsTo           { return $this->belongsTo(User::class); }
    public function userCierre(): BelongsTo     { return $this->belongsTo(User::class, 'user_cierre_id'); }
    public function arqueo(): HasMany           { return $this->hasMany(TurnoArqueo::class); }
    public function arqueoMetodos(): HasMany    { return $this->hasMany(TurnoArqueoMetodo::class); }
    public function cierreProductos(): HasMany  { return $this->hasMany(TurnoCierreProducto::class); }
    public function gastos(): HasMany           { return $this->hasMany(Gasto::class); }

    public function scopeAbierto($q)        { return $q->where('estado', 'abierto'); }
    public function scopeCerrado($q)        { return $q->where('estado', 'cerrado'); }
    public function scopeDeEmpresa($q, $id) { return $q->where('empresa_id', $id); }

    public function calcularMontoEsperado(): float
    {
        $gastosEfectivo = $this->gastos()->sum('monto');

        // Ventas en efectivo: pagos con método de tipo 'efectivo' en ventas completadas de este turno
        $ventasEfectivo = \App\Models\VentaPago::whereHas('venta', fn($q) =>
            $q->where('turno_id', $this->id)->where('estado', 'completada')
        )->whereHas('metodoPago', fn($q) =>
            $q->where('tipo', 'efectivo')
        )->sum('monto');

        return (float) $this->monto_apertura + (float) $ventasEfectivo - $gastosEfectivo;
    }

    public function calcularTotalArqueo(): float
    {
        return (float) $this->arqueo()->sum('subtotal');
    }

    public static function turnoActivoDelUsuario(int $userId): ?self
    {
        return static::where('user_id', $userId)->where('estado', 'abierto')->first();
    }
}
