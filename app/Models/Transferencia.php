<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class Transferencia extends Model
{
    protected $fillable = [
        'empresa_id',
        'almacen_origen_id',
        'almacen_destino_id',
        'user_id',
        'fecha',
        'estado',
        'observacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function almacenOrigen(): BelongsTo
    {
        return $this->belongsTo(Almacen::class, 'almacen_origen_id');
    }

    public function almacenDestino(): BelongsTo
    {
        return $this->belongsTo(Almacen::class, 'almacen_destino_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(TransferenciaDetalle::class);
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
            throw new \LogicException('Solo se puede confirmar una transferencia en estado borrador.');
        }

        // Verificar stock suficiente en origen antes de confirmar
        foreach ($this->detalles as $detalle) {
            $stockOrigen = Stock::where('almacen_id', $this->almacen_origen_id)
                ->where('producto_id', $detalle->producto_id)
                ->first();

            $disponible = $stockOrigen ? (float) $stockOrigen->cantidad : 0;

            if ($disponible < (float) $detalle->cantidad_base) {
                $producto = $detalle->producto;
                throw ValidationException::withMessages([
                    'stock' => "Stock insuficiente para \"{$producto->nombre}\". "
                             . "Disponible: {$disponible}, requerido: {$detalle->cantidad_base}.",
                ]);
            }
        }

        $this->update(['estado' => 'confirmado']);
    }
}
