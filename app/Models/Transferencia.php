<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * Transferencia entre almacenes (siempre central → local en modo central_y_local).
 *
 * Flujo en 3 etapas:
 *   1. borrador  → editable libremente, no toca stock
 *   2. enviada   → el central despachó. Descuenta stock del origen.
 *                  Stock destino aún NO se actualiza (está "en tránsito").
 *   3. recibida  → el local confirmó recepción con cantidades reales.
 *                  Suma stock al destino. Si hay diferencia con lo enviado,
 *                  se registra en `diferencia_base` (y queda como pérdida en tránsito
 *                  no contabilizada en stock — el origen ya descontó lo despachado).
 *
 * Adicional: anulada → revierte todos los movimientos previos.
 *
 * Edición flexible: en cualquier estado se puede editar. El controller debe
 * llamar revertirEfectoStock() ANTES de modificar y aplicarEfectoStock() DESPUÉS.
 */
class Transferencia extends Model
{
    protected $fillable = [
        'empresa_id',
        'almacen_origen_id',
        'almacen_destino_id',
        'user_id',
        'fecha',
        'estado',
        'fecha_envio',
        'fecha_recepcion',
        'user_envio_id',
        'user_recepcion_id',
        'observacion_envio',
        'observacion_recepcion',
    ];

    protected function casts(): array
    {
        return [
            'fecha'           => 'date',
            'fecha_envio'     => 'datetime',
            'fecha_recepcion' => 'datetime',
        ];
    }

    public function empresa(): BelongsTo        { return $this->belongsTo(Empresa::class); }
    public function almacenOrigen(): BelongsTo  { return $this->belongsTo(Almacen::class, 'almacen_origen_id'); }
    public function almacenDestino(): BelongsTo { return $this->belongsTo(Almacen::class, 'almacen_destino_id'); }
    public function user(): BelongsTo           { return $this->belongsTo(User::class); }
    public function userEnvio(): BelongsTo      { return $this->belongsTo(User::class, 'user_envio_id'); }
    public function userRecepcion(): BelongsTo  { return $this->belongsTo(User::class, 'user_recepcion_id'); }
    public function detalles(): HasMany         { return $this->hasMany(TransferenciaDetalle::class); }

    public function scopeBorrador(Builder $q): Builder { return $q->where('estado', 'borrador'); }
    public function scopeEnviada(Builder $q): Builder  { return $q->where('estado', 'enviada'); }
    public function scopeRecibida(Builder $q): Builder { return $q->where('estado', 'recibida'); }
    public function scopeAnulada(Builder $q): Builder  { return $q->where('estado', 'anulada'); }
    public function scopeDeEmpresa(Builder $q, int $id): Builder { return $q->where('empresa_id', $id); }

    public function esBorrador(): bool { return $this->estado === 'borrador'; }
    public function esEnviada(): bool  { return $this->estado === 'enviada'; }
    public function esRecibida(): bool { return $this->estado === 'recibida'; }
    public function esAnulada(): bool  { return $this->estado === 'anulada'; }

    // ── Métodos de negocio ───────────────────────────────────

    /**
     * Pasa de borrador → enviada. Descuenta stock del almacén origen.
     */
    public function enviar(int $userId, ?string $observacion = null): void
    {
        if ($this->esEnviada() || $this->esRecibida()) {
            throw new LogicException('La transferencia ya fue enviada.');
        }
        if ($this->esAnulada()) {
            throw new LogicException('No se puede enviar una transferencia anulada.');
        }

        DB::transaction(function () use ($userId, $observacion) {
            $this->validarStockOrigen();

            foreach ($this->detalles as $d) {
                Stock::ajustar(
                    $this->almacen_origen_id,
                    $d->producto_id,
                    -1 * (float) $d->cantidad_base_enviada,
                    contexto: [
                        'tipo'            => 'transferencia_envio',
                        'referencia_tipo' => 'transferencia',
                        'referencia_id'   => $this->id,
                        'fecha'           => now(),
                        'user_id'         => $userId,
                        'empresa_id'      => $this->empresa_id,
                    ],
                );
            }

            $this->update([
                'estado'            => 'enviada',
                'fecha_envio'       => now(),
                'user_envio_id'     => $userId,
                'observacion_envio' => $observacion,
            ]);
        });
    }

    /**
     * Pasa de enviada → recibida. Suma stock al almacén destino con cantidades reales declaradas.
     *
     * @param array<int, float> $cantidadesPorDetalle [detalle_id => cantidad_recibida (en unidad de presentación)]
     */
    public function recibir(array $cantidadesPorDetalle, int $userId, ?string $observacion = null): void
    {
        if (!$this->esEnviada()) {
            throw new LogicException('Solo se pueden recibir transferencias en estado enviada.');
        }

        DB::transaction(function () use ($cantidadesPorDetalle, $userId, $observacion) {
            foreach ($this->detalles as $d) {
                $cantRecibida = (float) ($cantidadesPorDetalle[$d->id] ?? $d->cantidad_enviada);
                $cantBaseRecibida = round($cantRecibida * (float) $d->factor_conversion, 4);
                $diferenciaBase = round($cantBaseRecibida - (float) $d->cantidad_base_enviada, 4);

                $d->update([
                    'cantidad_recibida'      => $cantRecibida,
                    'cantidad_base_recibida' => $cantBaseRecibida,
                    'diferencia_base'        => $diferenciaBase,
                ]);

                Stock::ajustar(
                    $this->almacen_destino_id,
                    $d->producto_id,
                    $cantBaseRecibida,
                    (float) $d->costo_unitario,
                    contexto: [
                        'tipo'            => 'transferencia_recepcion',
                        'referencia_tipo' => 'transferencia',
                        'referencia_id'   => $this->id,
                        'fecha'           => now(),
                        'user_id'         => $userId,
                        'empresa_id'      => $this->empresa_id,
                    ],
                );
            }

            $this->update([
                'estado'                => 'recibida',
                'fecha_recepcion'       => now(),
                'user_recepcion_id'     => $userId,
                'observacion_recepcion' => $observacion,
            ]);
        });
    }

    /**
     * Anula la transferencia revirtiendo todos los movimientos de stock aplicados.
     */
    public function anular(): void
    {
        if ($this->esAnulada()) return;

        DB::transaction(function () {
            $this->revertirEfectoStock();
            $this->update(['estado' => 'anulada']);
        });
    }

    /**
     * Revierte el efecto en stock que tenga la transferencia según su estado actual.
     * - borrador / anulada → no hay nada que revertir
     * - enviada   → revierte el descuento del origen
     * - recibida  → revierte el descuento del origen Y la entrada del destino
     */
    public function revertirEfectoStock(): void
    {
        if ($this->esBorrador() || $this->esAnulada()) return;

        $this->loadMissing('detalles');

        foreach ($this->detalles as $d) {
            // Revertir descuento del origen (devolver stock al central)
            Stock::ajustar(
                $this->almacen_origen_id,
                $d->producto_id,
                (float) $d->cantidad_base_enviada
            );
        }

        if ($this->esRecibida()) {
            foreach ($this->detalles as $d) {
                $cantBaseRecibida = (float) ($d->cantidad_base_recibida ?? $d->cantidad_base_enviada);
                // Reverso administrativo: si entre tanto se vendio en el destino, podria quedar
                // negativo transitoriamente. No bloqueamos para preservar consistencia contable.
                Stock::ajustar(
                    $this->almacen_destino_id,
                    $d->producto_id,
                    -1 * $cantBaseRecibida,
                    permitirNegativo: true,
                );
            }
        }
    }

    /**
     * Reaplica el efecto en stock según el estado actual y los detalles vigentes.
     * Útil después de editar para volver a aplicar el efecto correcto.
     */
    public function aplicarEfectoStock(): void
    {
        if ($this->esBorrador() || $this->esAnulada()) return;

        $this->loadMissing('detalles');

        foreach ($this->detalles as $d) {
            Stock::ajustar(
                $this->almacen_origen_id,
                $d->producto_id,
                -1 * (float) $d->cantidad_base_enviada
            );
        }

        if ($this->esRecibida()) {
            foreach ($this->detalles as $d) {
                $cantBaseRecibida = (float) ($d->cantidad_base_recibida ?? $d->cantidad_base_enviada);
                Stock::ajustar(
                    $this->almacen_destino_id,
                    $d->producto_id,
                    $cantBaseRecibida,
                    (float) $d->costo_unitario
                );
            }
        }
    }

    /**
     * Verifica que haya stock suficiente en el origen para todos los productos.
     * Útil antes de enviar.
     */
    public function validarStockOrigen(): void
    {
        $this->loadMissing('detalles.producto');

        foreach ($this->detalles as $d) {
            $stockOrigen = Stock::where('almacen_id', $this->almacen_origen_id)
                ->where('producto_id', $d->producto_id)
                ->first();

            $disponible = $stockOrigen ? (float) $stockOrigen->cantidad : 0;

            if ($disponible < (float) $d->cantidad_base_enviada) {
                $nombre = $d->producto?->nombre ?? "ID {$d->producto_id}";
                throw ValidationException::withMessages([
                    'stock' => "Stock insuficiente en el origen para \"{$nombre}\". "
                             . "Disponible: {$disponible}, requerido: {$d->cantidad_base_enviada}.",
                ]);
            }
        }
    }
}
