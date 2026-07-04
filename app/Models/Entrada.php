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
        'proveedor_id',
        'numero_documento',
        'proveedor',
        'tipo',
        'fecha',
        'estado',
        'observacion',
        'total',
        'monto_pagado',
        'estado_pago',
        'metodo_pago_id',
        'cuenta_id',
    ];

    public function proveedorRel(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }

    public function metodoPago(): BelongsTo
    {
        return $this->belongsTo(MetodoPago::class, 'metodo_pago_id');
    }

    public function cuenta(): BelongsTo
    {
        return $this->belongsTo(Cuenta::class, 'cuenta_id');
    }

    protected function casts(): array
    {
        return [
            'fecha'        => 'date',
            'total'        => 'decimal:2',
            'monto_pagado' => 'decimal:2',
        ];
    }

    public function estaPagada(): bool
    {
        return $this->estado_pago === 'pagado';
    }

    public function pagosParciales(): HasMany
    {
        return $this->hasMany(EntradaPago::class);
    }

    public function saldoPendiente(): float
    {
        return round((float) $this->total - (float) $this->monto_pagado, 2);
    }

    /**
     * Registra un abono y sincroniza monto_pagado + estado_pago
     * (pendiente → parcial → pagado). Llamar dentro de una transacción.
     */
    public function aplicarPago(float $monto): void
    {
        $pagado = round((float) $this->monto_pagado + $monto, 2);
        $total  = (float) $this->total;

        $this->update([
            'monto_pagado' => $pagado,
            'estado_pago'  => $pagado >= $total - 0.01 ? 'pagado' : ($pagado > 0 ? 'parcial' : 'pendiente'),
        ]);
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
