<?php

namespace App\Services;

use App\Models\Venta;
use App\Services\Facturacion\CompartirComprobante;
use Illuminate\Support\Facades\URL;

/**
 * Enviar el PDF de una VENTA (cualquiera: ticket, boleta o factura) por WhatsApp.
 *
 * Opcional por empresa (`empresas.venta_whatsapp_pdf`). Mismo patrón que
 * CompartirComprobante, y por las mismas razones:
 *   · `wa.me` no adjunta archivos: lo que viaja es un ENLACE al PDF dentro del
 *     mensaje ya escrito. Quien pulsa «Enviar» es la persona, desde su WhatsApp
 *     (cero APIs externas, cero costo por mensaje).
 *   · El enlace es una URL FIRMADA temporal (no adivinable por id) porque el
 *     cliente no tiene usuario en el POS: con la ruta normal vería el login.
 *
 * Si la venta tiene comprobante electrónico ya emitido, el enlace público
 * redirige a la representación impresa OFICIAL (la de FacturaMac), igual que
 * hace `ventas.pdf` para el usuario logueado.
 */
class CompartirVentaPdf
{
    public function __construct(private CompartirComprobante $comprobante) {}

    /**
     * URL pública y firmada del PDF de la venta, válida los mismos 30 días.
     * El formato (a4 / ticket) va DENTRO de la firma: no se puede cambiar a mano.
     */
    public function enlaceFirmado(Venta $venta, string $formato = 'a4'): string
    {
        return URL::temporarySignedRoute(
            'venta.publica.pdf',
            now()->addDays(CompartirComprobante::DIAS_VIGENCIA),
            ['venta' => $venta->id, 'formato' => VentaPdfCliente::formatoDe($formato)],
        );
    }

    /** Mensaje corto para WhatsApp, con el enlace al PDF. */
    public function mensaje(Venta $venta, string $formato = 'a4'): string
    {
        $empresa = $venta->empresa;
        $negocio = $empresa?->nombre_comercial ?: $empresa?->razon_social ?: 'tu tienda';
        $total   = number_format((float) $venta->total, 2);
        $doc     = $venta->numero_comprobante ?: $venta->numero;

        return "¡Hola! 👋 Te enviamos el detalle de tu compra en *{$negocio}*.\n\n"
            . "🧾 Venta {$doc}\n"
            . "💰 Total: S/ {$total}\n\n"
            . "Descárgala en PDF:\n"
            . $this->enlaceFirmado($venta, $formato) . "\n\n"
            . 'El enlace estará disponible ' . CompartirComprobante::DIAS_VIGENCIA . ' días. ¡Gracias por tu compra!';
    }

    /**
     * Lo que necesita el botón WhatsApp de la lista de ventas. El enlace `wa.me`
     * lo arma el navegador con el número que se escriba en el diálogo; aquí va
     * el número del cliente ya normalizado (con 51) para precargarlo.
     * Se mandan los dos formatos: el de la empresa viene marcado y en el
     * diálogo se puede cambiar sin volver a pedir nada al servidor.
     */
    public function payload(Venta $venta): array
    {
        $venta->loadMissing('cliente', 'empresa');
        $cliente = $venta->cliente;

        $enlaces = [];
        foreach (VentaPdfCliente::FORMATOS as $f) {
            $enlaces[$f] = ['pdf' => $this->enlaceFirmado($venta, $f), 'mensaje' => $this->mensaje($venta, $f)];
        }

        return [
            'formato'  => VentaPdfCliente::formatoDe($venta->empresa?->venta_pdf_formato),
            'enlaces'  => $enlaces,
            'telefono' => $cliente?->es_cliente_general
                ? ''
                : $this->comprobante->normalizarTelefono($cliente?->telefono),
            'cliente'  => $cliente && ! $cliente->es_cliente_general
                ? ($cliente->razon_social ?: trim(($cliente->nombres ?? '') . ' ' . ($cliente->apellidos ?? '')))
                : null,
        ];
    }
}
