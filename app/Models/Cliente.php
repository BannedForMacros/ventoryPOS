<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class Cliente extends Model
{
    protected $fillable = [
        'empresa_id',
        'tipo_documento',
        'numero_documento',
        'nombres',
        'apellidos',
        'razon_social',
        'telefono',
        'email',
        'direccion',
        'fecha_nacimiento',
        'activo',
    ];

    protected function casts(): array
    {
        return [
            'fecha_nacimiento' => 'date',
            'activo'           => 'boolean',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
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

    public function getNombreCompletoAttribute(): string
    {
        if ($this->razon_social) {
            return $this->razon_social;
        }

        return trim($this->nombres . ' ' . $this->apellidos);
    }

    public function getEsClienteGeneralAttribute(): bool
    {
        return is_null($this->numero_documento) && $this->nombres === 'Cliente';
    }
}
