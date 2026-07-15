<?php

namespace App\Services;

use App\Models\Caja;
use App\Models\ClienteAnticipoAplicacion;
use App\Models\Cotizacion;
use App\Models\Empresa;
use App\Models\Venta;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

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
            // Logo de la empresa en base64 (sale si el agente lo soporta).
            'logo'       => $this->logoBase64($empresa),
        ];
    }

    /**
     * Construye el ticket imprimible de una COTIZACIÓN (proforma). Espejo de
     * payloadDeVenta: mismo contrato de claves, pero sin datos de pago (aún no
     * se cobra) y con el pie como proforma.
     */
    public function payloadDeCotizacion(Cotizacion $cot): array
    {
        $cot->loadMissing(['empresa', 'local', 'user', 'cliente', 'items']);

        $empresa = $cot->empresa;
        $local   = $cot->local;

        // Cliente (nombre_completo = razon_social o "nombres apellidos").
        $cliente   = $cot->cliente;
        $nombreCli = trim((string) ($cliente?->nombre_completo ?? ''));
        $docCli    = ($cliente && $cliente->numero_documento)
            ? trim(($cliente->tipo_documento ? $cliente->tipo_documento . ' ' : '') . $cliente->numero_documento)
            : null;

        // La cotización no tiene caja propia: enrutamos a la impresora del local.
        // Leemos el atributo del modelo (select *) para no romper si la columna
        // token_impresora aún no existe en algún entorno.
        $token = (string) (Caja::where('local_id', $cot->local_id)->get()
            ->pluck('token_impresora')->filter()->first() ?? '');

        $validez = $cot->fecha_vencimiento
            ? Carbon::parse($cot->fecha_vencimiento)->format('d/m/Y')
            : null;

        $pie = trim(
            ($cot->observacion ? $cot->observacion . "\n" : '')
            . 'Proforma — no es comprobante de pago'
            . ($validez ? "\nVálida hasta {$validez}" : '')
        );

        return [
            'token' => $token,

            'negocio' => [
                'nombre'    => $empresa?->nombre_comercial ?: $empresa?->razon_social,
                'ruc'       => $empresa?->ruc,
                'direccion' => $local?->direccion ?: $empresa?->direccion,
                'telefono'  => $local?->telefono ?: $empresa?->telefono,
            ],

            'documento' => [
                'tipo'     => 'COTIZACIÓN / PROFORMA',
                'serie'    => null,
                'numero'   => $cot->numero,
                'fecha'    => $cot->fecha ? Carbon::parse($cot->fecha)->format('d/m/Y') : null,
                'vendedor' => $cot->user?->name,
                'caja'     => null,
            ],

            'cliente' => [
                'nombre'    => $nombreCli !== '' ? $nombreCli : 'Cliente Varios',
                'doc'       => $docCli,
                'direccion' => null,
            ],

            'items' => $cot->items->map(fn ($item) => [
                'cant'    => (float) $item->cantidad,
                'desc'    => $item->producto_nombre,
                'precio'  => (float) $item->precio_unitario,
                'importe' => (float) $item->subtotal,
                'unidad'  => $item->unidad_nombre,
            ])->values()->all(),

            'totales' => [
                'subtotal'  => (float) $cot->subtotal,
                'igv'       => (float) $cot->igv,
                'descuento' => (float) $cot->descuento_total,
                'total'     => (float) $cot->total,
                'moneda'    => 'PEN',
            ],

            // Una cotización no lleva pago: se cobra al convertirla en venta.
            'pago' => [
                'metodo'   => null,
                'recibido' => null,
                'vuelto'   => null,
            ],

            'pie'        => $pie,
            'qr'         => null,
            'abrirCajon' => false,
            'copias'     => 1,
            'logo'       => $this->logoBase64($empresa),
        ];
    }

    /**
     * Payload para el ticket de una ENTREGA de anticipo (constancia de entrega
     * de mercadería pagada por adelantado). Mismo contrato de claves.
     */
    public function payloadDeEntregaAnticipo(ClienteAnticipoAplicacion $entrega): array
    {
        $entrega->loadMissing([
            'anticipo.empresa', 'anticipo.cliente', 'anticipo.venta.local',
            'user', 'items.item',
        ]);

        $anticipo = $entrega->anticipo;
        $empresa  = $anticipo?->empresa;
        $local    = $anticipo?->venta?->local;
        $cliente  = $anticipo?->cliente;

        $nombreCli = trim((string) ($cliente?->nombre_completo ?? ''));
        $docCli    = ($cliente && $cliente->numero_documento)
            ? trim(($cliente->tipo_documento ? $cliente->tipo_documento . ' ' : '') . $cliente->numero_documento)
            : null;

        $localId = $anticipo?->venta?->local_id;
        $token   = $localId
            ? (string) (Caja::where('local_id', $localId)->get()->pluck('token_impresora')->filter()->first() ?? '')
            : '';

        $items = $entrega->items->map(fn ($ai) => [
            'cant'    => (float) $ai->cantidad,
            'desc'    => $ai->item?->producto_nombre ?? 'Producto',
            'precio'  => (float) ($ai->item?->precio_unitario ?? 0),
            'importe' => round((float) $ai->cantidad * (float) ($ai->item?->precio_unitario ?? 0), 2),
            'unidad'  => $ai->item?->unidad_nombre ?? 'und',
        ])->values()->all();

        // Total: valor de lo entregado (si hay ítems) o el monto de la aplicación.
        $total = count($items) > 0 ? array_sum(array_column($items, 'importe')) : (float) $entrega->monto;

        $pie = trim(
            ($entrega->observacion ? $entrega->observacion . "\n" : '')
            . 'Constancia de ENTREGA de mercadería (anticipo)'
            . ($anticipo?->venta?->numero ? "\nVenta origen: {$anticipo->venta->numero}" : '')
        );

        return [
            'token' => $token,

            'negocio' => [
                'nombre'    => $empresa?->nombre_comercial ?: $empresa?->razon_social,
                'ruc'       => $empresa?->ruc,
                'direccion' => $local?->direccion ?: $empresa?->direccion,
                'telefono'  => $local?->telefono ?: $empresa?->telefono,
            ],

            'documento' => [
                'tipo'     => 'ENTREGA DE ANTICIPO',
                'serie'    => null,
                'numero'   => $entrega->numero,
                'fecha'    => $entrega->fecha ? Carbon::parse($entrega->fecha)->format('d/m/Y') : null,
                'vendedor' => $entrega->user?->name,
                'caja'     => null,
            ],

            'cliente' => [
                'nombre'    => $nombreCli !== '' ? $nombreCli : 'Cliente Varios',
                'doc'       => $docCli,
                'direccion' => null,
            ],

            'items' => $items,

            'totales' => [
                'subtotal'  => round($total, 2),
                'igv'       => 0.0,
                'descuento' => 0.0,
                'total'     => round($total, 2),
                'moneda'    => 'PEN',
            ],

            'pago' => [
                'metodo'   => null,
                'recibido' => null,
                'vuelto'   => null,
            ],

            'pie'        => $pie,
            'qr'         => null,
            'abrirCajon' => false,
            'copias'     => 1,
            'logo'       => $this->logoBase64($empresa),
        ];
    }

    /**
     * Logo de la empresa como data URI base64 (disco 'public'), o null si no hay
     * logo o el archivo no existe. png/jpg/webp detectado por la extensión.
     */
    private function logoBase64(?Empresa $empresa): ?string
    {
        $path = $empresa?->logo;
        if (!$path) {
            return null;
        }

        try {
            if (!Storage::disk('public')->exists($path)) {
                return null;
            }
            $bytes = Storage::disk('public')->get($path);
        } catch (\Throwable $e) {
            return null;
        }

        $ext  = strtolower(pathinfo((string) $path, PATHINFO_EXTENSION));
        $mime = match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'webp'        => 'image/webp',
            default       => 'image/png',
        };

        return 'data:' . $mime . ';base64,' . base64_encode($bytes);
    }
}
