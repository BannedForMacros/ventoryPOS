<?php

namespace App\Observers;

use App\Models\Stock;
use App\Models\Transferencia;

class TransferenciaObserver
{
    public function updated(Transferencia $transferencia): void
    {
        // Solo actuar cuando el estado cambia a 'confirmado'
        if (!$transferencia->wasChanged('estado') || $transferencia->estado !== 'confirmado') {
            return;
        }

        $transferencia->loadMissing('detalles');

        foreach ($transferencia->detalles as $detalle) {
            // Descuenta del origen
            Stock::ajustar(
                almacenId:    $transferencia->almacen_origen_id,
                productoId:   $detalle->producto_id,
                cantidadBase: -(float) $detalle->cantidad_base,
                costoNuevo:   0,
            );

            // Suma al destino con el costo capturado en el detalle al momento del borrador
            Stock::ajustar(
                almacenId:    $transferencia->almacen_destino_id,
                productoId:   $detalle->producto_id,
                cantidadBase: (float) $detalle->cantidad_base,
                costoNuevo:   (float) $detalle->costo_unitario,
            );
        }
    }
}
