<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/** SOAT, revisión técnica, tarjeta de propiedad… de un vehículo, con su vencimiento. */
class VehiculoDocumento extends Model
{
    protected $table = 'vehiculo_documentos';

    /** Tipos sugeridos; se acepta cualquier otro nombre. */
    public const TIPOS = ['SOAT', 'Revisión técnica', 'Tarjeta de propiedad', 'TUC / habilitación MTC', 'Póliza de seguro', 'GPS'];

    /** Días antes del vencimiento en que pasa a "por vencer". */
    public const DIAS_AVISO = 30;

    protected $fillable = ['empresa_id', 'vehiculo_id', 'tipo', 'numero', 'emision', 'vence', 'archivo', 'archivo_nombre', 'observacion'];

    protected function casts(): array
    {
        return ['emision' => 'date:Y-m-d', 'vence' => 'date:Y-m-d'];
    }

    public function vehiculo(): BelongsTo { return $this->belongsTo(Vehiculo::class); }

    /** 'vigente' | 'por_vencer' | 'vencido' | null (sin fecha). */
    public function estado(?Carbon $hoy = null): ?string
    {
        if (!$this->vence) return null;
        $hoy ??= Carbon::today();
        if ($this->vence->lt($hoy)) return 'vencido';

        return $this->vence->lte($hoy->copy()->addDays(self::DIAS_AVISO)) ? 'por_vencer' : 'vigente';
    }
}
