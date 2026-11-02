<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Lo que se le pagó a un chofer o ayudante por sus viajes de un período. El
 * dinero salió con gastos de siempre (uno por placa); anularla los elimina.
 */
class FlotaLiquidacion extends Model
{
    protected $table = 'flota_liquidaciones';

    protected $fillable = [
        'empresa_id', 'numero', 'conductor_id', 'desde', 'hasta', 'viajes', 'vueltas', 'unidades',
        'monto_calculado', 'ajuste', 'ajuste_motivo', 'total', 'detalle', 'estado', 'fecha_pago',
        'motivo_anulacion', 'user_id',
    ];

    protected function casts(): array
    {
        return [
            'desde' => 'date:Y-m-d', 'hasta' => 'date:Y-m-d', 'fecha_pago' => 'date:Y-m-d',
            'unidades' => 'decimal:2', 'monto_calculado' => 'decimal:2', 'ajuste' => 'decimal:2', 'total' => 'decimal:2',
            'detalle' => 'array',
        ];
    }

    public function conductor(): BelongsTo { return $this->belongsTo(Conductor::class); }
    public function user(): BelongsTo      { return $this->belongsTo(User::class); }
    public function viajes(): HasMany      { return $this->hasMany(FlotaLiquidacionViaje::class); }
    public function gastos(): HasMany      { return $this->hasMany(Gasto::class); }

    /** LQ-0001… por empresa. Llamar dentro de una transacción. */
    public static function generarNumero(int $empresaId): string
    {
        DB::select('SELECT pg_advisory_xact_lock(?, ?)', [7302, $empresaId]);
        $max = (int) DB::table('flota_liquidaciones')->where('empresa_id', $empresaId)
            ->selectRaw('COALESCE(MAX(CAST(SUBSTRING(numero FROM 4) AS INTEGER)), 0) as n')->value('n');

        return 'LQ-' . str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }
}
