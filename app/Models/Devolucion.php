<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use LogicException;

class Devolucion extends Model
{
    protected $table = 'devoluciones';

    protected $fillable = [
        'empresa_id',
        'local_id',
        'turno_id',
        'caja_id',
        'venta_id',
        'user_id',
        'user_aprobacion_id',
        'numero',
        'fecha',
        'motivo_id',
        'forma_reembolso',
        'monto_devolucion',
        'monto_reembolso',
        'requiere_aprobacion',
        'fue_aprobada',
        'estado',
        'observacion',
        'fecha_aprobacion',
        'observacion_aprobacion',
    ];

    protected function casts(): array
    {
        return [
            'fecha'              => 'datetime',
            'fecha_aprobacion'   => 'datetime',
            'monto_devolucion'   => 'decimal:2',
            'monto_reembolso'    => 'decimal:2',
            'requiere_aprobacion' => 'boolean',
            'fue_aprobada'        => 'boolean',
        ];
    }

    public function empresa(): BelongsTo        { return $this->belongsTo(Empresa::class); }
    public function local(): BelongsTo          { return $this->belongsTo(Local::class); }
    public function turno(): BelongsTo          { return $this->belongsTo(Turno::class); }
    public function caja(): BelongsTo           { return $this->belongsTo(Caja::class); }
    public function venta(): BelongsTo          { return $this->belongsTo(Venta::class); }
    public function user(): BelongsTo           { return $this->belongsTo(User::class); }
    public function userAprobacion(): BelongsTo { return $this->belongsTo(User::class, 'user_aprobacion_id'); }
    public function motivo(): BelongsTo         { return $this->belongsTo(DevolucionMotivo::class, 'motivo_id'); }
    public function detalles(): HasMany         { return $this->hasMany(DevolucionDetalle::class); }
    public function pagos(): HasMany            { return $this->hasMany(DevolucionPago::class); }

    public function scopePendiente(Builder $q): Builder   { return $q->where('estado', 'pendiente'); }
    public function scopeCompletada(Builder $q): Builder  { return $q->where('estado', 'completada'); }
    public function scopeAnulada(Builder $q): Builder     { return $q->where('estado', 'anulada'); }
    public function scopeDeEmpresa(Builder $q, int $id): Builder { return $q->where('empresa_id', $id); }

    public function esPendiente(): bool  { return $this->estado === 'pendiente'; }
    public function esAprobada(): bool   { return $this->estado === 'aprobada'; }
    public function esCompletada(): bool { return $this->estado === 'completada'; }
    public function esAnulada(): bool    { return $this->estado === 'anulada'; }

    /**
     * Aprueba la devolución (solo cuando requiere_aprobacion). No aplica stock todavía.
     * Pasa de 'pendiente' a 'aprobada', lista para ser confirmada/completada.
     */
    public function aprobar(int $userId, ?string $observacion = null): void
    {
        if (!$this->esPendiente()) {
            throw new LogicException('Solo se pueden aprobar devoluciones pendientes.');
        }

        $this->update([
            'estado'                 => 'aprobada',
            'fue_aprobada'           => true,
            'user_aprobacion_id'     => $userId,
            'fecha_aprobacion'       => now(),
            'observacion_aprobacion' => $observacion,
        ]);
    }

    public function rechazar(int $userId, ?string $observacion = null): void
    {
        if (!$this->esPendiente()) {
            throw new LogicException('Solo se pueden rechazar devoluciones pendientes.');
        }

        $this->update([
            'estado'                 => 'rechazada',
            'fue_aprobada'           => false,
            'user_aprobacion_id'     => $userId,
            'fecha_aprobacion'       => now(),
            'observacion_aprobacion' => $observacion,
        ]);
    }

    /**
     * Completa la devolución: aplica restock por línea, genera salidas automáticas
     * para productos defectuosos sin restock, y deja la devolución en estado 'completada'.
     */
    public function completar(): void
    {
        if (!in_array($this->estado, ['pendiente', 'aprobada'], true)) {
            throw new LogicException('Solo se pueden completar devoluciones pendientes o aprobadas.');
        }

        if ($this->requiere_aprobacion && !$this->fue_aprobada) {
            throw new LogicException('Esta devolución requiere aprobación antes de completarse.');
        }

        DB::transaction(function () {
            $this->loadMissing(['detalles.producto', 'detalles.motivo', 'venta.local']);

            $almacenId = $this->resolverAlmacenLocal();

            foreach ($this->detalles as $d) {
                if ($d->restock && $almacenId) {
                    Stock::ajustar($almacenId, $d->producto_id, (float) $d->cantidad_base);
                }
                // Si no restockea, no hacemos nada con stock (el producto físicamente no
                // vuelve al inventario; queda en una zona aparte o se descarta).
                // Si la empresa quiere registrar la merma, puede hacerlo manualmente
                // desde el módulo Salidas referenciando la devolución.
            }

            $this->update(['estado' => 'completada']);
        });
    }

    public function anular(): void
    {
        if ($this->esAnulada()) return;

        DB::transaction(function () {
            // Si ya estaba completada, revertir el restock aplicado
            if ($this->esCompletada()) {
                $almacenId = $this->resolverAlmacenLocal();
                $this->loadMissing('detalles');

                foreach ($this->detalles as $d) {
                    if ($d->restock && $almacenId) {
                        Stock::ajustar($almacenId, $d->producto_id, -1 * (float) $d->cantidad_base);
                    }
                }
            }

            $this->update(['estado' => 'anulada']);
        });
    }

    /**
     * Almacén local del turno/local de la devolución (donde el producto vuelve al stock).
     */
    protected function resolverAlmacenLocal(): ?int
    {
        $almacen = Almacen::where('empresa_id', $this->empresa_id)
            ->where('local_id', $this->local_id)
            ->where('tipo', 'local')
            ->where('activo', true)
            ->first();

        if ($almacen) return $almacen->id;

        // Fallback: cualquier almacén activo de la empresa (modo simple legacy)
        return Almacen::where('empresa_id', $this->empresa_id)
            ->where('activo', true)
            ->orderBy('id')
            ->value('id');
    }

    public static function generarNumero(int $turnoId): string
    {
        $count = self::where('turno_id', $turnoId)->count() + 1;
        return 'DEV-' . str_pad($turnoId, 4, '0', STR_PAD_LEFT) . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
    }
}
