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
     * Marca el cierre como confirmado y guarda los totales. El AJUSTE de stock NO
     * se aplica aquí: al quedar 'confirmado', Stock::reconstruir aplica la
     * diferencia de cada item (los cierres confirmados son fuente del recálculo).
     * El controlador reconstruye stock y kardex por producto tras confirmar, así
     * el saldo es canónico, sobrevive a "Recalcular" y el kardex queda cronológico.
     */
    public function confirmar(): void
    {
        if (!$this->esBorrador()) {
            throw new LogicException('Solo se puede confirmar un cierre en estado borrador.');
        }

        $items = $this->items()->get();
        $this->update([
            'estado'            => 'confirmado',
            'total_items'       => $items->count(),
            'total_diferencias' => $items->filter(fn ($i) => abs((float) $i->diferencia) > 0.00009)->count(),
        ]);
    }
}
