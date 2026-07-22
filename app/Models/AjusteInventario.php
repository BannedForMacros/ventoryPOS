<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Ajuste de inventario: sube (ingreso) o baja (salida) el stock de UN producto
 * por cantidad, con fecha propia (retrofechable) y SIN mover dinero (no toca
 * tesorería, CxP ni proveedor). Es un documento fuente: Stock::reconstruir y
 * kardex:reconstruir lo cuentan, así que sobrevive al "Recalcular stock".
 *
 * El signo lo da `tipo` (ingreso +, salida −); `cantidad_base` es siempre
 * positiva. Sin costo propio: entra/sale al costo promedio vigente (no diluye
 * el CPP). Correlativo AJ-0001 por empresa.
 */
class AjusteInventario extends Model
{
    protected $table = 'ajustes_inventario';

    protected $fillable = [
        'empresa_id', 'almacen_id', 'producto_id', 'user_id', 'turno_id',
        'numero', 'tipo', 'cantidad_base', 'fecha', 'estado', 'motivo',
    ];

    protected function casts(): array
    {
        return [
            'fecha'         => 'date:Y-m-d',
            'cantidad_base' => 'decimal:4',
        ];
    }

    public function almacen(): BelongsTo  { return $this->belongsTo(Almacen::class); }
    public function producto(): BelongsTo { return $this->belongsTo(Producto::class); }
    public function user(): BelongsTo     { return $this->belongsTo(User::class); }

    public function scopeDeEmpresa(Builder $q, int $empresaId): Builder { return $q->where('empresa_id', $empresaId); }

    public function esBorrador(): bool   { return $this->estado === 'borrador'; }
    public function esConfirmado(): bool { return $this->estado === 'confirmado'; }

    /** Signo del movimiento según la dirección del ajuste. */
    public function signo(): int { return $this->tipo === 'ingreso' ? 1 : -1; }

    /** Correlativo AJ-0001 por empresa (optimistic-insert; el índice único protege). */
    public static function generarNumero(int $empresaId): string
    {
        $max = (int) DB::table('ajustes_inventario')
            ->where('empresa_id', $empresaId)
            ->selectRaw("COALESCE(MAX(CAST(SUBSTRING(numero FROM 4) AS INTEGER)), 0) as n")
            ->value('n');

        return 'AJ-' . str_pad((string) ($max + 1), 4, '0', STR_PAD_LEFT);
    }

    /**
     * Confirma el ajuste: aplica el stock en vivo (ingreso +, salida −) y traza
     * el kardex con el mismo tipo/referencia que emite kardex:reconstruir. Un
     * ajuste administrativo puede dejar el stock negativo (permitirNegativo=true).
     */
    public function confirmar(): void
    {
        if (!$this->esBorrador()) {
            throw new LogicException('Solo se puede confirmar un ajuste en borrador.');
        }

        DB::transaction(function () {
            Stock::ajustar(
                $this->almacen_id,
                $this->producto_id,
                $this->signo() * (float) $this->cantidad_base,
                0,     // sin costo: entra/sale al costo promedio vigente (no diluye CPP)
                true,  // permitirNegativo: el ajuste es una orden explícita del admin
                contexto: [
                    'tipo'            => $this->tipo === 'ingreso' ? 'ajuste_ingreso' : 'ajuste_salida',
                    'referencia_tipo' => 'ajuste',
                    'referencia_id'   => $this->id,
                    'documento'       => $this->numero,
                    'fecha'           => $this->fecha,
                    'user_id'         => $this->user_id,
                    'empresa_id'      => $this->empresa_id,
                ],
            );

            $this->update(['estado' => 'confirmado']);
        });
    }

    /**
     * Anula un ajuste confirmado: revierte el stock con el signo inverso y lo
     * marca 'anulado' (así el recálculo ya no lo cuenta y en vivo queda
     * neutralizado). No hay dinero que revertir.
     */
    public function anular(?int $userId = null): void
    {
        if (!$this->esConfirmado()) {
            throw new LogicException('Solo se puede anular un ajuste confirmado.');
        }

        DB::transaction(function () use ($userId) {
            Stock::ajustar(
                $this->almacen_id,
                $this->producto_id,
                -1 * $this->signo() * (float) $this->cantidad_base, // signo inverso
                0,
                true,
                contexto: [
                    'tipo'            => $this->tipo === 'ingreso' ? 'ajuste_salida' : 'ajuste_ingreso',
                    'referencia_tipo' => 'ajuste_anulacion',
                    'referencia_id'   => $this->id,
                    'documento'       => $this->numero,
                    'fecha'           => now(),
                    'user_id'         => $userId ?? $this->user_id,
                    'empresa_id'      => $this->empresa_id,
                ],
            );

            $this->update(['estado' => 'anulado']);
        });
    }
}
