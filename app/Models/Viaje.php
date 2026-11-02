<?php

namespace App\Models;

use App\Models\Concerns\AvisaTiempoReal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Salida de un vehículo con su chofer.
 *
 *  - reparto:        lleva ventas a los clientes (sus paradas).
 *  - abastecimiento: trae carga del proveedor o la fábrica al almacén
 *                    ("vuelta de fábrica"); puede ligarse a la entrada.
 *  - flete:          transporte para un tercero, cobrado con una venta de
 *                    servicio que va como parada directa.
 *
 * Un viaje NO mueve stock por sí mismo: ver ViajeService.
 */
class Viaje extends Model
{
    use AvisaTiempoReal;

    // Despachos también: muestra en qué viaje va cada pendiente.
    protected static function recursosTiempoReal(): array { return ['viajes', 'despachos']; }

    protected $table = 'viajes';

    public const TIPOS   = ['reparto', 'abastecimiento', 'flete'];
    public const ABIERTOS = ['programado', 'en_ruta'];

    protected $fillable = [
        'empresa_id', 'numero', 'tipo', 'fecha', 'hora_salida', 'vehiculo_id', 'conductor_id',
        'almacen_id', 'ruta_entrega_id', 'estado', 'origen', 'destino', 'proveedor_id',
        'entrada_id', 'observacion', 'motivo_anulacion', 'user_id', 'salida_at', 'cierre_at',
    ];

    protected function casts(): array
    {
        return [
            'fecha'     => 'date:Y-m-d',
            'salida_at' => 'datetime',
            'cierre_at' => 'datetime',
        ];
    }

    public function vehiculo(): BelongsTo    { return $this->belongsTo(Vehiculo::class); }
    public function conductor(): BelongsTo   { return $this->belongsTo(Conductor::class); }
    public function almacen(): BelongsTo     { return $this->belongsTo(Almacen::class); }
    public function rutaEntrega(): BelongsTo { return $this->belongsTo(RutaEntrega::class); }
    public function proveedor(): BelongsTo   { return $this->belongsTo(Proveedor::class); }
    public function entrada(): BelongsTo     { return $this->belongsTo(Entrada::class); }
    public function user(): BelongsTo        { return $this->belongsTo(User::class); }
    public function paradas(): HasMany       { return $this->hasMany(ViajeParada::class)->orderBy('orden')->orderBy('id'); }

    public function ayudantes(): BelongsToMany
    {
        return $this->belongsToMany(Conductor::class, 'viaje_ayudantes', 'viaje_id', 'conductor_id');
    }

    public function scopeDeEmpresa(Builder $q, int $empresaId): Builder
    {
        return $q->where('viajes.empresa_id', $empresaId);
    }

    public function scopeAbierto(Builder $q): Builder
    {
        return $q->whereIn('viajes.estado', self::ABIERTOS);
    }

    public function abierto(): bool
    {
        return in_array($this->estado, self::ABIERTOS, true);
    }

    /** VJ-0001, VJ-0002… por empresa. Llamar dentro de una transacción. */
    public static function generarNumero(int $empresaId): string
    {
        // Cerrojo por empresa: dos viajes creados a la vez no sacan el mismo número.
        DB::select('SELECT pg_advisory_xact_lock(?, ?)', [7301, $empresaId]);

        $max = (int) DB::table('viajes')
            ->where('empresa_id', $empresaId)
            ->selectRaw("COALESCE(MAX(CAST(SUBSTRING(numero FROM 4) AS INTEGER)), 0) as n")
            ->value('n');

        return 'VJ-' . str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }
}
