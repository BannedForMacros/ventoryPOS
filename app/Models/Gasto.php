<?php

namespace App\Models;

use App\Models\Concerns\AvisaTiempoReal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Gasto extends Model
{
    use AvisaTiempoReal;

    protected static function recursosTiempoReal(): array { return ['turnos']; }

    // Borrado suave: eliminar un gasto conserva la fila (para el filtro
    // "Eliminados" y poder reactivarlo). El egreso de tesorería se revierte
    // al eliminar y se vuelve a asentar al reactivar (ver GastoController).
    use SoftDeletes;

    protected $fillable = [
        'empresa_id', 'local_id', 'user_id', 'turno_id',
        'gasto_tipo_id', 'gasto_concepto_id',
        'monto', 'cuenta_id', 'fecha', 'comentario',
        'moneda', 'tipo_cambio', 'monto_moneda',
        // Flota: el gasto se carga a una placa (utilidad por camión).
        'vehiculo_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha'        => 'date',
            'tipo_cambio'  => 'decimal:6',
            'monto_moneda' => 'decimal:2',
        ];
    }

    public function empresa(): BelongsTo  { return $this->belongsTo(Empresa::class); }
    public function local(): BelongsTo    { return $this->belongsTo(Local::class); }
    public function user(): BelongsTo     { return $this->belongsTo(User::class); }
    public function turno(): BelongsTo    { return $this->belongsTo(Turno::class); }
    public function tipo(): BelongsTo     { return $this->belongsTo(GastoTipo::class, 'gasto_tipo_id'); }
    public function concepto(): BelongsTo { return $this->belongsTo(GastoConcepto::class, 'gasto_concepto_id'); }
    public function cuenta(): BelongsTo   { return $this->belongsTo(Cuenta::class); }
    public function vehiculo(): BelongsTo { return $this->belongsTo(Vehiculo::class); }

    /** Ficha técnica si el gasto es combustible o mantenimiento de un vehículo. */
    public function combustible(): \Illuminate\Database\Eloquent\Relations\HasOne   { return $this->hasOne(VehiculoCombustible::class); }
    public function mantenimiento(): \Illuminate\Database\Eloquent\Relations\HasOne { return $this->hasOne(VehiculoMantenimiento::class); }

    public function scopeDelTurno($q, $turnoId) { return $q->where('turno_id', $turnoId); }
    public function scopeAdministrativos($q)    { return $q->whereNull('turno_id'); }
    public function scopeDeEmpresa($q, $id)     { return $q->where('empresa_id', $id); }

    public function esDelTurno(): bool       { return $this->turno_id !== null; }
    public function esAdministrativo(): bool { return $this->turno_id === null; }
}
