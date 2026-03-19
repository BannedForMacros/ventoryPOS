<?php

namespace App\Observers;

use App\Models\TurnoCierreProducto;
use App\Models\Venta;

class VentaObserver
{
    /**
     * Cuando se crea una venta completada, actualiza el snapshot de productos
     * del cierre del turno (turno_cierre_productos).
     * Si el registro ya existe para ese turno+producto, suma la cantidad.
     */
    public function created(Venta $venta): void
    {
        if ($venta->estado !== 'completada' || !$venta->turno_id) {
            return;
        }

        $venta->load('items.producto');

        foreach ($venta->items as $item) {
            TurnoCierreProducto::updateOrCreate(
                [
                    'turno_id'   => $venta->turno_id,
                    'producto_id'=> $item->producto_id,
                ],
                []
            );

            // Actualizar los campos sumando con una query directa para seguridad concurrente
            TurnoCierreProducto::where('turno_id', $venta->turno_id)
                ->where('producto_id', $item->producto_id)
                ->update([
                    'producto_nombre'  => $item->producto_nombre,
                    'precio_unitario'  => $item->precio_unitario,
                    'cantidad_vendida' => \Illuminate\Support\Facades\DB::raw("cantidad_vendida + {$item->cantidad_base}"),
                    'total'            => \Illuminate\Support\Facades\DB::raw("total + {$item->subtotal}"),
                ]);
        }
    }

    /**
     * Cuando una venta se anula, descuenta del snapshot de cierre de turno.
     */
    public function updated(Venta $venta): void
    {
        if (!$venta->wasChanged('estado') || $venta->estado !== 'anulada' || !$venta->turno_id) {
            return;
        }

        $venta->loadMissing('items');

        foreach ($venta->items as $item) {
            TurnoCierreProducto::where('turno_id', $venta->turno_id)
                ->where('producto_id', $item->producto_id)
                ->update([
                    'cantidad_vendida' => \Illuminate\Support\Facades\DB::raw("GREATEST(0, cantidad_vendida - {$item->cantidad_base})"),
                    'total'            => \Illuminate\Support\Facades\DB::raw("GREATEST(0, total - {$item->subtotal})"),
                ]);
        }
    }
}
