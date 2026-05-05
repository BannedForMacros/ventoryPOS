<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

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
    public function ventas(): HasMany           { return $this->hasMany(\App\Models\Venta::class); }

    public function scopeAbierto($q)        { return $q->where('estado', 'abierto'); }
    public function scopeCerrado($q)        { return $q->where('estado', 'cerrado'); }
    public function scopeDeEmpresa($q, $id) { return $q->where('empresa_id', $id); }

    /**
     * Calcula el monto esperado en caja al cierre.
     *
     * Composición:
     *   monto_apertura
     * + ventas en efectivo del turno (pagos con método tipo='efectivo' en ventas completadas)
     * - gastos del turno (siempre se asume que se pagan con efectivo de la caja)
     * + monto_caja_chica  (solo si la empresa/local incluye fondos iniciales en la declaración)
     *
     * El último sumando depende de la configuración de la empresa/local: cuando los
     * fondos iniciales se incluyen en la declaración, el cajero al final cuenta también
     * los billetes que estaban como caja chica, así que el sistema espera ver ese monto.
     */
    public function calcularMontoEsperado(): float
    {
        $gastosEfectivo = (float) $this->gastos()->sum('monto');

        $ventasEfectivo = (float) \App\Models\VentaPago::whereHas('venta', fn($q) =>
            $q->where('turno_id', $this->id)->where('estado', 'completada')
        )->whereHas('metodoPago', fn($q) =>
            $q->where('tipo', 'efectivo')
        )->sum('monto');

        $apertura     = (float) $this->monto_apertura;
        $fondos       = (float) $this->monto_caja_chica;
        $sumaFondos   = $this->fondosEntranEnDeclaracion();

        return $apertura
             + $ventasEfectivo
             - $gastosEfectivo
             + ($sumaFondos ? $fondos : 0.0);
    }

    /**
     * true si para este turno los fondos iniciales se cuentan en el arqueo de cierre.
     * Resuelve la jerarquía local→empresa.
     */
    public function fondosEntranEnDeclaracion(): bool
    {
        $local = $this->local;
        if (!$local) return false;
        return app(\App\Services\ConfiguracionOperacionService::class)
            ->fondosInicialesEnDeclaracion($local);
    }

    public function calcularTotalArqueo(): float
    {
        return (float) $this->arqueo()->sum('subtotal');
    }

    /**
     * Recorre las ventas COMPLETADAS del turno y guarda un snapshot agregado por producto.
     * Idempotente: borra y recrea las filas para este turno.
     */
    public function poblarSnapshotProductos(): void
    {
        DB::transaction(function () {
            TurnoCierreProducto::where('turno_id', $this->id)->delete();

            $ventas = \App\Models\VentaItem::whereHas('venta', fn($q) =>
                $q->where('turno_id', $this->id)->where('estado', 'completada')
            )
            ->select(
                'producto_id',
                DB::raw("MIN(producto_nombre) as producto_nombre"),
                DB::raw('SUM(cantidad) as cantidad_vendida'),
                DB::raw('SUM(subtotal) as total'),
                DB::raw('AVG(precio_unitario) as precio_unitario')
            )
            ->groupBy('producto_id')
            ->get();

            foreach ($ventas as $row) {
                TurnoCierreProducto::create([
                    'turno_id'         => $this->id,
                    'producto_id'      => $row->producto_id,
                    'producto_nombre'  => $row->producto_nombre,
                    'cantidad_vendida' => $row->cantidad_vendida,
                    'precio_unitario'  => round((float) $row->precio_unitario, 2),
                    'total'            => round((float) $row->total, 2),
                ]);
            }
        });
    }

    public static function turnoActivoDelUsuario(int $userId): ?self
    {
        return static::where('user_id', $userId)->where('estado', 'abierto')->first();
    }
}
