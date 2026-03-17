<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Entrada extends Model
{
    protected $fillable = [
        'empresa_id',
        'almacen_id',
        'user_id',
        'numero_documento',
        'proveedor',
        'tipo',
        'fecha',
        'estado',
        'observacion',
        'total',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'total' => 'decimal:2',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function almacen(): BelongsTo
    {
        return $this->belongsTo(Almacen::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(EntradaDetalle::class);
    }

    // ── Scopes ──────────────────────────────────────────────

    public function scopeBorrador(Builder $query): Builder
    {
        return $query->where('estado', 'borrador');
    }

    public function scopeConfirmado(Builder $query): Builder
    {
        return $query->where('estado', 'confirmado');
    }

    public function scopeDeEmpresa(Builder $query, int $empresaId): Builder
    {
        return $query->where('empresa_id', $empresaId);
    }

    // ── Métodos de negocio ───────────────────────────────────

    public function esBorrador(): bool
    {
        return $this->estado === 'borrador';
    }

    public function confirmar(): void
    {
        if (!$this->esBorrador()) {
            throw new \LogicException('Solo se puede confirmar una entrada en estado borrador.');
        }

        $this->update(['estado' => 'confirmado']);
    }
}
