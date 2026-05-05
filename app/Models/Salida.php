<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use LogicException;
use RuntimeException;

class Salida extends Model
{
    protected $fillable = [
        'empresa_id',
        'almacen_id',
        'user_id',
        'turno_id',
        'salida_tipo_id',
        'numero_documento',
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

    public function empresa(): BelongsTo    { return $this->belongsTo(Empresa::class); }
    public function almacen(): BelongsTo    { return $this->belongsTo(Almacen::class); }
    public function user(): BelongsTo       { return $this->belongsTo(User::class); }
    public function turno(): BelongsTo      { return $this->belongsTo(Turno::class); }
    public function tipo(): BelongsTo       { return $this->belongsTo(SalidaTipo::class, 'salida_tipo_id'); }
    public function detalles(): HasMany     { return $this->hasMany(SalidaDetalle::class); }

    public function scopeBorrador(Builder $q): Builder   { return $q->where('estado', 'borrador'); }
    public function scopeConfirmado(Builder $q): Builder { return $q->where('estado', 'confirmado'); }
    public function scopeDeEmpresa(Builder $q, int $id): Builder { return $q->where('empresa_id', $id); }

    public function esBorrador(): bool   { return $this->estado === 'borrador'; }
    public function esConfirmado(): bool { return $this->estado === 'confirmado'; }

    /**
     * Confirma la salida: descuenta stock por cada detalle y marca el documento como confirmado.
     * Antes de descontar valida que haya stock disponible para no dejar negativos.
     * Captura `costo_unitario` snapshot del producto (costo promedio actual).
     */
    public function confirmar(): void
    {
        if (!$this->esBorrador()) {
            throw new LogicException('Solo se puede confirmar una salida en estado borrador.');
        }

        DB::transaction(function () {
            $detalles = $this->detalles()->get();

            // Validar stock disponible producto por producto (agrupar por producto)
            $necesitadoPorProducto = [];
            foreach ($detalles as $d) {
                $necesitadoPorProducto[$d->producto_id] = ($necesitadoPorProducto[$d->producto_id] ?? 0) + (float) $d->cantidad_base;
            }

            $stocks = Stock::where('almacen_id', $this->almacen_id)
                ->whereIn('producto_id', array_keys($necesitadoPorProducto))
                ->get()
                ->keyBy('producto_id');

            foreach ($necesitadoPorProducto as $productoId => $necesitado) {
                $disponible = (float) ($stocks[$productoId]->cantidad ?? 0);
                if ($necesitado > $disponible) {
                    $producto = Producto::find($productoId);
                    $nombre   = $producto?->nombre ?? "ID {$productoId}";
                    throw new RuntimeException(
                        "Stock insuficiente de '{$nombre}'. Disponible: {$disponible}, requerido: {$necesitado}."
                    );
                }
            }

            // Aplicar descuentos y guardar costo_unitario snapshot
            $totalDocumento = 0.0;
            foreach ($detalles as $d) {
                $stock = $stocks[$d->producto_id] ?? null;
                $costoUnitario = $stock ? (float) $stock->costo_promedio : 0.0;
                $subtotal = round($costoUnitario * (float) $d->cantidad_base, 2);

                $d->update([
                    'costo_unitario' => $costoUnitario,
                    'subtotal'       => $subtotal,
                ]);

                Stock::ajustar($this->almacen_id, $d->producto_id, -1 * (float) $d->cantidad_base);

                $totalDocumento += $subtotal;
            }

            $this->update([
                'estado' => 'confirmado',
                'total'  => round($totalDocumento, 2),
            ]);
        });
    }
}
