<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Chofer o ayudante de reparto. El chofer lleva licencia; el ayudante solo va
 * en el viaje (sirve para saber quién fue y, más adelante, para su pago).
 */
class Conductor extends Model
{
    protected $table = 'conductores';

    public const CHOFER   = 'chofer';
    public const AYUDANTE = 'ayudante';

    /** Días antes del vencimiento de la licencia en que se avisa. */
    public const DIAS_AVISO_LICENCIA = 30;

    protected $fillable = [
        'empresa_id', 'tipo', 'nombres', 'apellidos', 'tipo_documento', 'numero_documento',
        'telefono', 'licencia', 'licencia_categoria', 'licencia_vence', 'observacion', 'activo',
    ];

    protected $appends = ['nombre_completo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean', 'licencia_vence' => 'date:Y-m-d'];
    }

    public function vehiculos(): HasMany { return $this->hasMany(Vehiculo::class); }
    public function viajes(): HasMany    { return $this->hasMany(Viaje::class); }

    public function scopeDeEmpresa(Builder $q, int $empresaId): Builder
    {
        return $q->where('empresa_id', $empresaId);
    }

    public function scopeActivo(Builder $q): Builder
    {
        return $q->where('activo', true);
    }

    public function getNombreCompletoAttribute(): string
    {
        return trim($this->nombres . ' ' . ($this->apellidos ?? ''));
    }

    /** 'vigente' | 'por_vencer' | 'vencida' | null (sin fecha). */
    public function estadoLicencia(?Carbon $hoy = null): ?string
    {
        if (!$this->licencia_vence) return null;
        $hoy ??= Carbon::today();
        if ($this->licencia_vence->lt($hoy)) return 'vencida';

        return $this->licencia_vence->lte($hoy->copy()->addDays(self::DIAS_AVISO_LICENCIA)) ? 'por_vencer' : 'vigente';
    }
}
