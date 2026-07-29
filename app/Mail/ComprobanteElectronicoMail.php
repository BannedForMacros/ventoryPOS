<?php

namespace App\Mail;

use App\Models\VentaComprobante;
use App\Services\Facturacion\CompartirComprobante;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Correo con el comprobante electrónico para el cliente.
 *
 * Lleva el PDF ADJUNTO **y** el enlace firmado en el cuerpo, a propósito y con
 * redundancia deliberada:
 *   · El adjunto es lo que el cliente espera y lo que su contador va a archivar;
 *     además sobrevive a la caducidad del enlace y a que rotemos APP_KEY.
 *   · El enlace cubre el caso contrario: correos corporativos que recortan o
 *     bloquean adjuntos PDF, y clientes que leen desde el móvil y prefieren
 *     abrirlo en el navegador.
 *
 * El PDF se recibe YA DESCARGADO (binario) en vez de descargarlo aquí: quien
 * construye el Mailable es el controlador, que ya sabe hablar con FacturaMac y
 * traducir sus fallos a un mensaje para la cajera. Un Mailable que hace llamadas
 * de red al renderizarse falla en el peor sitio posible —dentro del envío— y sin
 * forma decente de avisar.
 */
class ComprobanteElectronicoMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public VentaComprobante $comprobante,
        /** Binario del PDF ya descargado de FacturaMac. Null si no se pudo obtener. */
        public ?string $pdf = null,
    ) {}

    public function envelope(): Envelope
    {
        $empresa = $this->comprobante->venta?->empresa;
        $negocio = $empresa?->nombre_comercial ?: $empresa?->razon_social ?: config('app.name');

        return new Envelope(
            subject: $this->etiqueta() . " — {$negocio}",
        );
    }

    public function content(): Content
    {
        $compartir = app(CompartirComprobante::class);
        $venta     = $this->comprobante->venta;
        $empresa   = $venta?->empresa;

        return new Content(
            view: 'emails.comprobante-electronico',
            with: [
                'etiqueta'      => $this->etiqueta(),
                'negocio'       => $empresa?->nombre_comercial ?: $empresa?->razon_social ?: config('app.name'),
                'empresa'       => $empresa,
                'clienteNombre' => $venta?->cliente?->nombre_completo ?: 'Cliente',
                // Comprobantes solo en PEN (guarda G12): el símbolo no es variable.
                'total'         => number_format((float) ($venta?->total ?? 0), 2),
                'fecha'         => $venta?->fecha_venta?->format('d/m/Y') ?? '',
                'enlace'        => $compartir->enlaceFirmado($this->comprobante),
                'dias'          => CompartirComprobante::DIAS_VIGENCIA,
                'tieneAdjunto'  => $this->pdf !== null,
            ],
        );
    }

    /** @return array<int, Attachment> */
    public function attachments(): array
    {
        if ($this->pdf === null) {
            return [];
        }

        // `fromData` y no `fromPath`: el PDF nunca toca el disco. Es un documento
        // fiscal con datos del cliente; guardarlo en storage solo para adjuntarlo
        // añadiría un sitio más del que limpiarlo.
        return [
            Attachment::fromData(fn () => $this->pdf, $this->nombreArchivo())
                ->withMime('application/pdf'),
        ];
    }

    private function etiqueta(): string
    {
        return app(CompartirComprobante::class)->etiqueta($this->comprobante);
    }

    private function nombreArchivo(): string
    {
        $base = $this->comprobante->numero
            ?: ('comprobante-' . ($this->comprobante->venta?->numero ?? $this->comprobante->id));

        return $base . '.pdf';
    }
}
