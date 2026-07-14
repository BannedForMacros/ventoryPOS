<?php

namespace App\Services;

use App\Models\Venta;

/**
 * Arma el payload de impresión de tickets para el agente local
 * VentoryPrint.exe (escucha en http://127.0.0.1:9111 en la PC de cada caja).
 *
 * CONTRATO: las claves de este array deben coincidir con TicketPayload en
 * resources/js/lib/ticketPrinter.ts y con Ticket.cs del agente. No renombrar
 * sin actualizar ambos lados.
 */
class TicketPrintService
{
    /** Etiqueta imprimible por tipo de comprobante del POS. */
    private const TIPOS_DOCUMENTO = [
        'ticket'  => 'NOTA DE VENTA',
        'boleta'  => 'BOLETA DE VENTA',
        'factura' => 'FACTURA',
    ];

    /**
     * Construye el ticket imprimible de una venta.
     * El token enruta el ticket a la impresora de la caja correcta: el agente
     * solo imprime si coincide con el token configurado en esa PC.
     */
    public function payloadDeVenta(Venta $venta): array
    {
        $venta->loadMissing([
            'empresa', 'local', 'caja', 'turno.caja', 'user', 'cliente',
            'items', 'pagos.metodoPago.tipo',
        ]);

        $empresa = $venta->empresa;
        $local   = $venta->local;
        // La caja directa de la venta; si falta (ventas antiguas), la del turno.
        $caja    = $venta->caja ?? $venta->turno?->caja;

        // ── Cliente ─────────────────────────────────────────────────────────
        // nombre_completo = razon_social o "nombres apellidos" (accessor del modelo).
        $cliente   = $venta->cliente;
        $nombreCli = trim((string) ($cliente?->nombre_completo ?? ''));
        $docCli    = ($cliente && $cliente->numero_documento)
            ? trim(($cliente->tipo_documento ? $cliente->tipo_documento . ' ' : '') . $cliente->numero_documento)
            : null;

        // ── Pagos ───────────────────────────────────────────────────────────
        // Efectivo se detecta por el slug del tipo del método (catálogo
        // tipos_metodo_pago), igual que en Turno::calcularMontoEsperado().
        // En venta_pagos, `monto` es lo ENTREGADO por el cliente y `vuelto`
        // el cambio devuelto (ver VentaService::crear).
        $pagos       = $venta->pagos;
        $hayEfectivo = $pagos->contains(fn ($p) => $p->metodoPago?->tipo?->slug === 'efectivo');
        $metodo      = $pagos->map(fn ($p) => $p->metodoPago?->nombre)->filter()->unique()->implode(' + ');
        $vuelto      = round((float) $pagos->sum('vuelto'), 2);

        return [
            'token' => (string) ($venta->caja?->token_impresora
                ?? $venta->turno?->caja?->token_impresora
                ?? ''),

            'negocio' => [
                'nombre'    => $empresa?->nombre_comercial ?: $empresa?->razon_social,
                'ruc'       => $empresa?->ruc,
                'direccion' => $local?->direccion ?: $empresa?->direccion,
                'telefono'  => $local?->telefono ?: $empresa?->telefono,
            ],

            'documento' => [
                'tipo'     => self::TIPOS_DOCUMENTO[$venta->tipo_comprobante] ?? 'NOTA DE VENTA',
                'serie'    => null,
                'numero'   => $venta->numero,
                'fecha'    => $venta->fecha_venta?->format('d/m/Y h:i A'),
                'vendedor' => $venta->user?->name,
                'caja'     => $caja?->nombre,
            ],

            'cliente' => [
                'nombre'    => $nombreCli !== '' ? $nombreCli : 'Cliente Varios',
                'doc'       => $docCli,
                'direccion' => null,
            ],

            'items' => $venta->items->map(fn ($item) => [
                'cant'    => (float) $item->cantidad,
                'desc'    => $item->producto_nombre,
                'precio'  => (float) $item->precio_unitario,
                // subtotal del item = (precio - descuento_item) * cantidad
                'importe' => (float) $item->subtotal,
                'unidad'  => $item->unidad_nombre,
            ])->values()->all(),

            'totales' => [
                'subtotal'  => (float) $venta->subtotal,
                'igv'       => (float) $venta->igv,
                'descuento' => (float) $venta->descuento_total,
                'total'     => (float) $venta->total,
                'moneda'    => $venta->moneda ?? 'PEN',
            ],

            'pago' => [
                'metodo'   => $metodo !== '' ? $metodo : null,
                'recibido' => $hayEfectivo ? round((float) $pagos->sum('monto'), 2) : null,
                'vuelto'   => $vuelto > 0 ? $vuelto : null,
            ],

            'pie'        => 'Gracias por su preferencia',
            'qr'         => null,
            // Abrir el cajón portamonedas solo cuando entró efectivo.
            'abrirCajon' => $hayEfectivo,
            'copias'     => 1,
        ];
    }
}
