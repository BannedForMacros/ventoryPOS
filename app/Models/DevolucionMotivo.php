<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Catálogo de motivos de devolución, configurable por empresa.
 *
 * `afecta_restock_default`:
 *  - permite      → al elegir este motivo, el detalle puede restockear (default sí)
 *  - impide       → impide restockear (ej: "Vencido" → nunca vuelve al stock)
 *  - obliga_merma → no restockea Y genera salida automática tipo merma
 */
class DevolucionMotivo extends Model
{
    protected $table = 'devolucion_motivos';

    protected $fillable = [
        'empresa_id',
        'nombre',
        'slug',
        'afecta_restock_default',
        'es_sistema',
        'activo',
        'orden',
    ];

    protected function casts(): array
    {
        return [
            'es_sistema' => 'boolean',
            'activo'     => 'boolean',
        ];
    }

    public function empresa(): BelongsTo    { return $this->belongsTo(Empresa::class); }
    public function devoluciones(): HasMany { return $this->hasMany(Devolucion::class, 'motivo_id'); }

    public function scopeActivo(Builder $q): Builder { return $q->where('activo', true); }
    public function scopeDeEmpresa(Builder $q, int $id): Builder { return $q->where('empresa_id', $id); }
}
