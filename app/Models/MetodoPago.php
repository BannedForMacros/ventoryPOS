<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class MetodoPago extends Model
{
    protected $table = 'metodos_pago';

    protected $fillable = [
        'empresa_id',
        'nombre',
        'tipo',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function cuentas(): HasMany
    {
        return $this->hasMany(MetodoPagoCuenta::class);
    }

    // ── Scopes ──────────────────────────────────────────────────────────────

    public function scopeActivo(Builder $query): Builder
    {
        return $query->where('activo', true);
    }

    public function scopeDeEmpresa(Builder $query, int $empresaId): Builder
    {
        return $query->where('empresa_id', $empresaId);
    }

    // ── Accessors ───────────────────────────────────────────────────────────

    public function getRequiereNumeroOperacionAttribute(): bool
    {
        return in_array($this->tipo, ['yape', 'plin', 'transferencia']);
    }

    public function getCalculaVueltoAttribute(): bool
    {
        return $this->tipo === 'efectivo';
    }

    public function getTieneCuentasAttribute(): bool
    {
        return $this->cuentas->where('activo', true)->isNotEmpty();
    }
}
