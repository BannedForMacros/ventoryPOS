<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ruta o zona de reparto de una empresa ("Ruta 3", zona "Pomalca"). La elige
 * la cajera al registrar un envío y sale en el ticket y en los despachos.
 */
class RutaEntrega extends Model
{
    protected $table = 'rutas_entrega';

    protected $fillable = ['empresa_id', 'nombre', 'zona', 'orden', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean', 'orden' => 'integer'];
    }

    public function empresa(): BelongsTo { return $this->belongsTo(Empresa::class); }
    public function ventas(): HasMany    { return $this->hasMany(Venta::class, 'ruta_entrega_id'); }

    public function scopeDeEmpresa(Builder $q, int $empresaId): Builder
    {
        return $q->where('empresa_id', $empresaId);
    }

    public function scopeActiva(Builder $q): Builder
    {
        return $q->where('activo', true);
    }

    /** "Ruta 3, Pomalca" */
    public function getEtiquetaAttribute(): string
    {
        return trim($this->nombre . ($this->zona ? ', ' . $this->zona : ''));
    }
}
