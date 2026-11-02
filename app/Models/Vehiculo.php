<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Camión o unidad de reparto de una empresa.
 *
 * "En ruta" NO se guarda: un vehículo está en ruta mientras tenga un viaje en
 * ruta. Guardarlo aparte es otra lista que se desincroniza (el viaje se cierra y
 * el camión se queda "en ruta" para siempre). Lo que sí se guarda es lo que
 * decide una persona: en taller o fuera de servicio.
 */
class Vehiculo extends Model
{
    protected $table = 'vehiculos';

    public const ESTADOS = ['disponible', 'taller', 'fuera_servicio'];

    protected $fillable = [
        'empresa_id', 'placa', 'marca', 'modelo', 'anio', 'tipo', 'capacidad_kg',
        'tarjeta_circulacion', 'conductor_id', 'estado', 'km_actual', 'observacion', 'activo',
        'mant_intervalo_km', 'mant_base_km',
    ];

    protected function casts(): array
    {
        return [
            'activo'       => 'boolean',
            'anio'         => 'integer',
            'capacidad_kg' => 'decimal:2',
            'km_actual'    => 'decimal:1',
            'mant_intervalo_km' => 'integer',
            'mant_base_km' => 'decimal:1',
        ];
    }

    public function conductor(): BelongsTo { return $this->belongsTo(Conductor::class); }
    public function viajes(): HasMany      { return $this->hasMany(Viaje::class); }
    public function combustibles(): HasMany   { return $this->hasMany(VehiculoCombustible::class); }
    public function mantenimientos(): HasMany { return $this->hasMany(VehiculoMantenimiento::class); }
    public function documentos(): HasMany     { return $this->hasMany(VehiculoDocumento::class); }
    public function gastos(): HasMany         { return $this->hasMany(Gasto::class); }
    public function capacidades(): HasMany    { return $this->hasMany(VehiculoCapacidad::class); }

    public function scopeDeEmpresa(Builder $q, int $empresaId): Builder
    {
        return $q->where('empresa_id', $empresaId);
    }

    public function scopeActivo(Builder $q): Builder
    {
        return $q->where('activo', true);
    }

    /** Puede salir: activo y ni en taller ni fuera de servicio. */
    public function operativo(): bool
    {
        return $this->activo && $this->estado === 'disponible';
    }

    /** "M9C-822 · Hyundai EX10" */
    public function getEtiquetaAttribute(): string
    {
        $modelo = trim(($this->marca ?? '') . ' ' . ($this->modelo ?? ''));

        return $this->placa . ($modelo !== '' ? " · {$modelo}" : '');
    }

    public static function normalizarPlaca(string $placa): string
    {
        return strtoupper(preg_replace('/\s+/', '', trim($placa)));
    }
}
