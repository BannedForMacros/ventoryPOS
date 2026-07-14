<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use LogicException;

class CierreInventario extends Model
{
    protected $table = 'cierres_inventario';

    protected $fillable = [
        'empresa_id',
        'almacen_id',
        'user_id',
        'turno_id',
        'fecha',
        'estado',
        'observacion',
        'total_items',
        'total_diferencias',
    ];

    protected function casts(): array
    {
        return [
            'fecha' => 'date',
        ];
    }

    public function empresa(): BelongsTo  { return $this->belongsTo(Empresa::class); }
    public function almacen(): BelongsTo  { return $this->belongsTo(Almacen::class); }
    public function user(): BelongsTo     { return $this->belongsTo(User::class); }
    public function turno(): BelongsTo    { return $this->belongsTo(Turno::class); }
    public function items(): HasMany      { return $this->hasMany(CierreInventarioItem::class, 'cierre_id'); }

    public function scopeBorrador(Builder $q): Builder    { return $q->where('estado', 'borrador'); }
    public function scopeConfirmado(Builder $q): Builder  { return $q->where('estado', 'confirmado'); }
    public function scopeDeEmpresa(Builder $q, int $id): Builder { return $q->where('empresa_id', $id); }

    public function esBorrador(): bool   { return $this->estado === 'borrador'; }
    public function esConfirmado(): bool { return $this->estado === 'confirmado'; }

    /**
     * Confirma el cierre: ajusta el stock de cada producto al valor declarado.
     * Las diferencias quedan registradas en los items.
     */
    public function confirmar(): void
    {
        if (!$this->esBorrador()) {
            throw new LogicException('Solo se puede confirmar un cierre en estado borrador.');
        }

        DB::transaction(function () {
            $items = $this->items()->get();
            $totalDiferencias = 0;

            foreach ($items as $item) {
                if ((float) $item->diferencia !== 0.0) {
                    Stock::ajustar(
                        $this->almacen_id,
                        $item->producto_id,
                        (float) $item->diferencia,
                        contexto: [
                            'tipo'            => 'cierre',
                            'referencia_tipo' => 'cierre',
                            'referencia_id'   => $this->id,
                            'fecha'           => $this->fecha,
                            'user_id'         => $this->user_id,
                            'empresa_id'      => $this->empresa_id,
                        ],
                    );
                    $totalDiferencias++;
                }
            }

            $this->update([
                'estado'            => 'confirmado',
                'total_items'       => $items->count(),
                'total_diferencias' => $totalDiferencias,
            ]);
        });
    }
}
