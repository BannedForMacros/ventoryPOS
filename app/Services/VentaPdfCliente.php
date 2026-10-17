<?php

namespace App\Services;

use App\Models\Venta;
use App\Support\NumeroEnLetras;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

/**
 * PDF de la venta PARA EL CLIENTE (el que viaja por WhatsApp), en A4 o en
 * ticket de 80 mm según `empresas.venta_pdf_formato`.
 *
 * Es otro documento que `pdf.venta` (la ficha interna del dueño): aquí no van
 * el ID interno, la caja, quién autorizó cada descuento ni las cuentas de
 * cobro; solo lo que el cliente necesita para saber qué compró y cuánto pagó.
 * El cajero sale solo si la plantilla del ticket lo muestra (mostrar_cajero).
 */
class VentaPdfCliente
{
    public const FORMATOS = ['a4', 'ticket'];

    /** 80 mm en puntos (dompdf trabaja en pt). */
    private const ANCHO_TICKET = 226.77;

    public static function formatoDe(?string $formato): string
    {
        return in_array($formato, self::FORMATOS, true) ? $formato : 'a4';
    }

    /** PDF listo para `->stream()` / `->output()`. */
    public function generar(Venta $venta, string $formato)
    {
        $formato = self::formatoDe($formato);
        $datos   = $this->datos($venta);
        $vista   = $formato === 'ticket' ? 'pdf.venta-cliente-ticket' : 'pdf.venta-cliente-a4';

        if ($formato === 'a4') {
            return Pdf::loadView($vista, $datos)->setPaper('a4');
        }

        // Ticket: un solo papel largo, del alto exacto del contenido. Se mide
        // con una pasada sobre un papel de sobra y se vuelve a pintar al alto
        // real (dompdf no tiene "alto automático").
        // El árbol de frames se libera al terminar, así que la posición del
        // marcador #fin se toma con un callback mientras se pinta.
        $alto   = null;
        $medida = Pdf::loadView($vista, $datos)->setPaper([0, 0, self::ANCHO_TICKET, 6000]);
        $medida->getDomPDF()->setCallbacks([[
            'event' => 'end_frame',
            'f'     => function ($frame) use (&$alto) {
                $nodo = $frame->get_node();
                if ($nodo instanceof \DOMElement && $nodo->getAttribute('id') === 'fin') {
                    $alto = (float) $frame->get_position('y') + (float) $frame->get_margin_height();
                }
            },
        ]]);
        $medida->render();

        return Pdf::loadView($vista, $datos)->setPaper([0, 0, self::ANCHO_TICKET, max(150, ($alto ?? 6000) + 14)]);
    }

    public function datos(Venta $venta): array
    {
        $venta->loadMissing([
            'empresa', 'local', 'user', 'cliente', 'items',
            'pagos.metodoPago', 'abonos.metodoPago', 'anticipos.items',
        ]);
        $empresa = $venta->empresa;
        $cfg     = is_array($empresa?->ticket_config) ? $empresa->ticket_config : [];
        $cliente = $venta->cliente;
        $general = ! $cliente || $cliente->es_cliente_general;

        $tipos = [
            'ticket'          => 'NOTA DE VENTA',
            'boleta'          => 'BOLETA DE VENTA',
            'factura'         => 'FACTURA',
            'boleta_externa'  => 'BOLETA DE VENTA',
            'factura_externa' => 'FACTURA',
        ];

        $pagos = $venta->pagos->map(fn ($p) => [
            'metodo' => $p->metodoPago?->nombre ?? 'Pago',
            'monto'  => (float) $p->monto,
        ])->values()->all();

        $pendientes = collect($venta->anticipos ?? [])
            ->where('estado', 'activo')
            ->flatMap(fn ($a) => $a->items ?? collect())
            ->filter(fn ($ai) => (float) $ai->cantidad_pendiente > 0.0001)
            ->map(fn ($ai) => [
                'producto'  => $ai->producto_nombre,
                'unidad'    => $ai->unidad_nombre,
                'pendiente' => (float) $ai->cantidad_pendiente,
            ])->values()->all();

        $tasa = (float) ($empresa?->tasa_igv ?? 18);
        $pie  = trim((string) ($cfg['pie'] ?? '')) ?: 'Gracias por su preferencia';

        return [
            'negocio'   => $this->texto($empresa?->nombre_comercial) ?? $this->texto($empresa?->razon_social) ?? 'Mi negocio',
            'razon'     => $this->texto($empresa?->razon_social),
            'ruc'       => ($cfg['mostrar_ruc'] ?? true) ? $this->texto($empresa?->ruc) : null,
            'direccion' => $this->texto($venta->local?->direccion) ?? $this->texto($empresa?->direccion),
            'telefono'  => $this->texto($venta->local?->telefono) ?? $this->texto($empresa?->telefono),
            'email'     => $this->texto($empresa?->email),
            'logo'      => $this->logo($empresa?->logo),

            'doc_tipo'   => $tipos[$venta->tipo_comprobante] ?? 'NOTA DE VENTA',
            'doc_numero' => $venta->numero_comprobante ?: $venta->numero,
            'fecha'      => $venta->fecha_venta?->format('d/m/Y'),
            'hora'       => $venta->fecha_venta?->format('h:i A'),
            'atendido'   => ($cfg['mostrar_cajero'] ?? true) ? $venta->user?->name : null,
            'anulada'    => $venta->estado === 'anulada' || $venta->anulada_nc_devolucion_id,

            'cliente' => $general ? null : [
                'nombre'    => $cliente->nombre_completo,
                'documento' => $cliente->numero_documento
                    ? trim(($cliente->tipo_documento ? $cliente->tipo_documento . ' ' : '') . $cliente->numero_documento)
                    : null,
                'direccion' => trim((string) ($venta->cliente_direccion ?? '')) ?: $cliente->direccion,
                'telefono'  => trim((string) ($venta->cliente_telefono ?? '')) ?: $cliente->telefono,
            ],

            'items' => $venta->items->map(fn ($it) => [
                'producto'    => $it->producto_nombre,
                'descripcion' => $it->descripcion,
                'unidad'      => $it->unidad_nombre,
                'cantidad'    => (float) $it->cantidad,
                'precio'      => (float) $it->precio_unitario,
                'descuento'   => (float) $it->descuento_item * (float) $it->cantidad,
                'importe'     => (float) $it->subtotal,
            ])->values()->all(),

            'subtotal'    => (float) $venta->subtotal,
            'descuento'   => (float) $venta->descuento_total,
            'igv'         => (float) $venta->igv,
            'tasa_igv'    => rtrim(rtrim(number_format($tasa, 2, '.', ''), '0'), '.'),
            'gravada'     => round((float) $venta->total - (float) $venta->igv, 2),
            'total'       => (float) $venta->total,
            'en_letras'   => NumeroEnLetras::importe((float) $venta->total, 'PEN'),
            'mostrar_igv' => $venta->tipo_comprobante !== 'ticket' || (bool) ($cfg['mostrar_igv'] ?? false),

            'pagos'   => $pagos,
            'vuelto'  => round((float) $venta->pagos->sum('vuelto'), 2),
            'credito' => $venta->es_credito ? [
                'pagado' => (float) $venta->monto_pagado,
                'saldo'  => (float) $venta->saldo_pendiente,
                'vence'  => $venta->fecha_vencimiento ? \Illuminate\Support\Carbon::parse($venta->fecha_vencimiento)->format('d/m/Y') : null,
            ] : null,
            'pendientes'  => $pendientes,
            'observacion' => $venta->observacion,
            'pie'         => $pie,
            'extras'      => array_values(array_filter((array) ($cfg['lineas_extra'] ?? []))),
        ];
    }

    /** Dato de la ficha o null si está vacío o es un relleno tipo "-" / "." */
    private function texto(?string $v): ?string
    {
        $v = trim((string) $v);

        return $v === '' || trim($v, '-. ') === '' ? null : $v;
    }

    /** Logo incrustado en base64: carga siempre, sin depender de storage:link ni APP_URL. */
    private function logo(?string $ruta): ?string
    {
        try {
            $disk = Storage::disk('public');
            if ($ruta && $disk->exists($ruta)) {
                $mime = match (strtolower(pathinfo($ruta, PATHINFO_EXTENSION))) {
                    'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif', default => 'image/jpeg',
                };

                return 'data:' . $mime . ';base64,' . base64_encode($disk->get($ruta));
            }
        } catch (\Throwable) {
        }

        return null;
    }
}
