<?php

namespace App\Observers;

use App\Models\Entrada;
use App\Models\Stock;

class EntradaObserver
{
    public function updated(Entrada $entrada): void
    {
        // Solo actuar cuando el estado cambia a 'confirmado'
        if (!$entrada->wasChanged('estado') || $entrada->estado !== 'confirmado') {
            return;
        }

        $entrada->loadMissing('detalles');

        foreach ($entrada->detalles as $detalle) {
            Stock::ajustar(
                almacenId:    $entrada->almacen_id,
                productoId:   $detalle->producto_id,
                cantidadBase: (float) $detalle->cantidad_base,
                costoNuevo:   (float) $detalle->precio_costo,
            );
        }

        // Recalcular total (por si se editó el detalle antes de confirmar)
        $total = $entrada->detalles->sum('subtotal');
        $entrada->withoutEvents(fn () => $entrada->update(['total' => $total]));
    }
}
