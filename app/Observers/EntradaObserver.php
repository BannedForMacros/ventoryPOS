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
            // Entrada en o antes de la fecha del inventario inicial: su mercadería
            // ya quedó DENTRO del conteo físico (apertura). Aplicarle stock la
            // duplicaría; solo es registro documental de la compra.
            if (Stock::absorbidoPorApertura($entrada->almacen_id, $detalle->producto_id, $entrada->fecha)) {
                continue;
            }

            Stock::ajustar(
                almacenId:    $entrada->almacen_id,
                productoId:   $detalle->producto_id,
                cantidadBase: (float) $detalle->cantidad_base,
                costoNuevo:   (float) $detalle->precio_costo,
                contexto: [
                    'tipo'            => 'entrada',
                    'referencia_tipo' => 'entrada',
                    'referencia_id'   => $entrada->id,
                    'documento'       => $entrada->numero_documento,
                    'fecha'           => $entrada->fecha,
                    'user_id'         => $entrada->user_id,
                    'empresa_id'      => $entrada->empresa_id,
                ],
            );
        }

        // Recalcular total (por si se editó el detalle antes de confirmar)
        $total = $entrada->detalles->sum('subtotal');
        $entrada->withoutEvents(fn () => $entrada->update(['total' => $total]));
    }
}
