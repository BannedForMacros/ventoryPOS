<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Entrada extends Model
{
    protected $fillable = [
        'empresa_id',
        'almacen_id',
        'user_id',
        'proveedor_id',
        'numero_documento',
        'correlativo',
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
        'moneda',
        'tipo_cambio',
        'monto_moneda',
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
            'tipo_cambio'  => 'decimal:6',
            'monto_moneda' => 'decimal:2',
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

    /**
     * Correlativo interno de la entrada: E-AAAAMMDD-NNN, donde AAAAMMDD es la
     * `fecha` de la entrada y NNN es la secuencia (3 dígitos) dentro de
     * (empresa_id, ese día). Ej: primera entrada del 2026-07-17 → E-20260717-001.
     *
     * ROBUSTEZ ante huecos: se usa MAX de la secuencia ya emitida ese día — NO
     * COUNT — para que borrar una entrada intermedia no reutilice su número (una
     * vez asignado es inmutable, como una factura).
     *
     * CONCURRENCIA: esta función NO bloquea filas. La unicidad la garantiza el
     * índice único parcial `entradas_empresa_correlativo_uq (empresa_id,
     * correlativo)`; si dos requests calculan el mismo número, el segundo recibe
     * UniqueConstraintViolationException y el llamador reintenta (optimistic +
     * retry, mismo patrón que Venta::generarNumero).
     */
    public static function generarCorrelativo(int $empresaId, string $fecha): string
    {
        $dia     = str_replace('-', '', substr($fecha, 0, 10)); // AAAAMMDD
        $prefijo = "E-{$dia}-";

        // split_part(correlativo, '-', 3) devuelve la porción NNN de 'E-AAAAMMDD-NNN'.
        $max = (int) DB::table('entradas')
            ->where('empresa_id', $empresaId)
            ->where('correlativo', 'like', $prefijo . '%')
            ->selectRaw("COALESCE(MAX(CAST(split_part(correlativo, '-', 3) AS INTEGER)), 0) as n")
            ->value('n');

        return $prefijo . str_pad((string) ($max + 1), 3, '0', STR_PAD_LEFT);
    }
}
