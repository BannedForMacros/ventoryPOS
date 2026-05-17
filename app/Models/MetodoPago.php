<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MetodoPago extends Model
{
    protected $table = 'metodos_pago';

    protected $fillable = [
        'empresa_id',
        'nombre',
        'tipo_id',
        'admite_vuelto',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'admite_vuelto' => 'boolean',
            'activo'        => 'boolean',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    /**
     * Catalogo del tipo (efectivo, yape, plin, etc.). Reemplaza el enum nativo.
     * Para queries antiguas que comparaban con string usa: $metodo->tipo->slug.
     */
    public function tipo(): BelongsTo
    {
        return $this->belongsTo(TipoMetodoPago::class, 'tipo_id');
    }

    public function cuentas(): BelongsToMany
    {
        return $this->belongsToMany(Cuenta::class, 'cuenta_metodo_pago');
    }

    public function scopeActivo(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    public function scopeDeEmpresa(Builder $query, int $empresaId): Builder
    {
        return $query->where('empresa_id', $empresaId);
    }

    /** Helper para filtrar metodos por slug de tipo. */
    public function scopeDeTipo(Builder $q, string $slug): Builder
    {
        return $q->whereHas('tipo', fn($t) => $t->where('slug', $slug));
    }

    /**
     * Indica si este método admite vuelto físico (sobrepago).
     * Fuente de verdad: la columna `admite_vuelto` del método, configurable
     * por el admin. Si no estuviera definida (legado) cae al default del tipo.
     */
    public function getCalculaVueltoAttribute(): bool
    {
        if ($this->admite_vuelto !== null) {
            return (bool) $this->admite_vuelto;
        }
        return (bool) ($this->tipo?->admite_vuelto_default ?? false);
    }

    public function getRequiereNumeroOperacionAttribute(): bool
    {
        return (bool) ($this->tipo?->requiere_referencia ?? false);
    }

    public function getTieneCuentasAttribute(): bool
    {
        return $this->cuentas->isNotEmpty();
    }
}
