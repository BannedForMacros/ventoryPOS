<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Stock extends Model
{
    protected $table = 'stock';

    protected $fillable = [
        'almacen_id',
        'producto_id',
        'cantidad',
        'costo_promedio',
    ];

    protected function casts(): array
    {
        return [
            'cantidad'       => 'decimal:4',
            'costo_promedio' => 'decimal:4',
        ];
    }

    public function almacen(): BelongsTo
    {
        return $this->belongsTo(Almacen::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    // ── Lógica central de stock ──────────────────────────────────────────────
    //
    // Este es el ÚNICO método que debe modificar el stock.
    // Nunca actualices cantidad/costo_promedio directamente desde un controller.
    //
    // Parámetros:
    //   $cantidadBase  → positivo = entrada, negativo = salida
    //   $costoNuevo    → solo se usa si es entrada (cantidad positiva)
    //                    para recalcular el costo promedio ponderado.
    //                    En salidas pasa 0 (no afecta el costo promedio).

    public static function ajustar(
        int   $almacenId,
        int   $productoId,
        float $cantidadBase,
        float $costoNuevo = 0
    ): self {
        $stock = self::firstOrCreate(
            ['almacen_id' => $almacenId, 'producto_id' => $productoId],
            ['cantidad' => 0, 'costo_promedio' => 0]
        );

        if ($cantidadBase > 0) {
            // Entrada: recalcular costo promedio ponderado
            // CPP = (stock_actual * costo_actual + cantidad_nueva * costo_nuevo)
            //       / (stock_actual + cantidad_nueva)
            $cantidadActual = (float) $stock->cantidad;
            $costoActual    = (float) $stock->costo_promedio;

            $nuevaCantidad = $cantidadActual + $cantidadBase;

            $nuevoCosto = $nuevaCantidad > 0
                ? (($cantidadActual * $costoActual) + ($cantidadBase * $costoNuevo)) / $nuevaCantidad
                : 0;

            $stock->cantidad       = $nuevaCantidad;
            $stock->costo_promedio = round($nuevoCosto, 4);
        } else {
            // Salida: solo descuenta cantidad, no toca costo promedio
            $stock->cantidad = max(0, (float) $stock->cantidad + $cantidadBase);
        }

        $stock->save();

        return $stock;
    }

    /**
     * Recalcula el costo promedio de este registro desde cero,
     * procesando todas las entradas confirmadas en orden cronológico.
     * Útil para el botón "Recalcular stock" en el panel admin.
     */
    public static function recalcularDesdeEntradas(int $almacenId, int $productoId): self
    {
        $stock = self::firstOrCreate(
            ['almacen_id' => $almacenId, 'producto_id' => $productoId],
            ['cantidad' => 0, 'costo_promedio' => 0]
        );

        $detalles = EntradaDetalle::whereHas('entrada', fn ($q) =>
            $q->where('almacen_id', $almacenId)
              ->where('estado', 'confirmado')
        )
        ->where('producto_id', $productoId)
        ->join('entradas', 'entradas_detalle.entrada_id', '=', 'entradas.id')
        ->orderBy('entradas.fecha')
        ->orderBy('entradas.id')
        ->select('entradas_detalle.*')
        ->get();

        $cantidad = 0.0;
        $costo    = 0.0;

        foreach ($detalles as $d) {
            $nuevaCantidad = $cantidad + (float) $d->cantidad_base;
            $costo = $nuevaCantidad > 0
                ? (($cantidad * $costo) + ((float) $d->cantidad_base * (float) $d->precio_costo)) / $nuevaCantidad
                : 0;
            $cantidad = $nuevaCantidad;
        }

        $stock->cantidad       = $cantidad;
        $stock->costo_promedio = round($costo, 4);
        $stock->save();

        return $stock;
    }
}
