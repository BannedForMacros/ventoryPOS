<?php

namespace App\Services\Facturacion;

use App\Models\VentaComprobante;
use Illuminate\Support\Facades\URL;

/**
 * Cómo se le hace llegar al cliente SU comprobante electrónico.
 *
 * Dos canales, un solo documento: WhatsApp (enlace `wa.me` con el mensaje ya
 * escrito) y correo (PDF adjunto). Ambos apuntan al MISMO PDF a través de una
 * URL firmada temporal.
 *
 * ── POR QUÉ URL FIRMADA Y NO LA RUTA NORMAL ──────────────────────────────────
 * `ventas.comprobante.pdf` vive detrás de `auth` + `permiso:ventas,ver`. Si le
 * mandamos ESE enlace al cliente por WhatsApp, lo único que ve es la pantalla de
 * login: el cliente no es usuario del POS y nunca lo va a ser. Las alternativas
 * eran tres y solo una es sensata:
 *
 *   1. Adjuntar siempre el PDF        → en WhatsApp no se puede sin API externa,
 *                                       y el dueño lo prohibió expresamente.
 *   2. Ruta pública por id            → `/comprobante/123/pdf` es adivinable:
 *                                       enumerar ids expone los comprobantes de
 *                                       TODA la instalación (datos del cliente,
 *                                       importes, RUC). Inaceptable.
 *   3. URL firmada temporal (ésta)    → el id viaja acompañado de una firma HMAC
 *                                       derivada de `APP_KEY` y de una fecha de
 *                                       caducidad. Cambiar el id invalida la
 *                                       firma; no se puede forjar sin la clave de
 *                                       la aplicación. Laravel lo valida en el
 *                                       middleware `signed` y responde 403 solo.
 *
 * Es la PRIMERA vez que este repo firma URLs, de ahí el detalle del comentario.
 * Ojo con dos cosas si algún día algo deja de funcionar:
 *   · Rotar `APP_KEY` invalida TODOS los enlaces ya enviados. Es lo correcto
 *     (la clave es el secreto que los sostiene), pero conviene saberlo.
 *   · La firma incluye el host y el esquema: si `APP_URL` no coincide con el
 *     dominio real por el que entra el cliente, la validación falla. En producción
 *     `APP_URL` DEBE ser la URL pública real.
 *
 * ── POR QUÉ 30 DÍAS ──────────────────────────────────────────────────────────
 * Es un equilibrio entre dos riesgos opuestos:
 *   · Demasiado corto (horas/días): el cliente abre el WhatsApp el fin de semana,
 *     el enlace ya caducó y la cajera acaba reenviando comprobantes a mano. El
 *     canal se vuelve inútil y se abandona.
 *   · Sin caducidad: un enlace filtrado (chat reenviado, móvil perdido, backup en
 *     la nube) expone ese comprobante para siempre.
 * 30 días cubre de sobra el uso real —descargar el comprobante para una garantía,
 * un reembolso o la contabilidad del mes— y acota la ventana de un enlace filtrado
 * a un mes. Además coincide con `dias_max_devolucion` por defecto de la empresa,
 * que es justo el plazo en el que el cliente puede necesitar el documento.
 * Si el cliente lo pide después, la cajera vuelve a compartir y se genera uno nuevo.
 */
class CompartirComprobante
{
    /** Vigencia del enlace público del PDF. Ver la justificación en el docblock. */
    public const DIAS_VIGENCIA = 30;

    /**
     * Perú. Los móviles peruanos son 9 dígitos y empiezan por 9; `wa.me` EXIGE el
     * número en formato internacional SIN '+' ni separadores. Mandar "987654321"
     * a secas abre WhatsApp con un contacto inexistente: es, con diferencia, el
     * error más habitual con `wa.me`, y silencioso (el enlace "funciona", pero el
     * mensaje no llega a nadie).
     */
    private const PREFIJO_PAIS = '51';
    private const LARGO_MOVIL_LOCAL = 9;

    /** Catálogo 01 SUNAT → algo que el cliente entienda en un WhatsApp. */
    private const NOMBRES_TIPO = [
        '01' => 'FACTURA',
        '03' => 'BOLETA',
        '07' => 'NOTA DE CRÉDITO',
        '08' => 'NOTA DE DÉBITO',
    ];

    /**
     * URL pública y firmada del PDF, válida DIAS_VIGENCIA días.
     *
     * Se firma sobre el id de `venta_comprobantes` (no sobre el de la venta):
     * el documento que el cliente descarga es el comprobante, y así el enlace
     * sobrevive intacto aunque la venta se edite.
     */
    public function enlaceFirmado(VentaComprobante $ce): string
    {
        return URL::temporarySignedRoute(
            'comprobante.publico.pdf',
            now()->addDays(self::DIAS_VIGENCIA),
            ['ventaComprobante' => $ce->id],
        );
    }

    /**
     * Enlace `wa.me` con el mensaje ya redactado.
     *
     * NO envía nada: solo construye el enlace. Es el patrón que ya usa
     * WhatsappService y la restricción explícita del dueño — cero APIs externas
     * de WhatsApp, cero costes por mensaje, cero cuentas de Business que mantener.
     * Quien pulsa "Enviar" es la persona, desde su propio WhatsApp.
     *
     * Sin teléfono devuelve `https://wa.me/?text=...`, que abre WhatsApp con el
     * mensaje escrito y deja elegir el destinatario en el momento. Es exactamente
     * lo que hace falta para el cliente de mostrador que no está registrado:
     * mejor eso que un botón deshabilitado.
     */
    public function urlWhatsapp(VentaComprobante $ce, ?string $telefono = null): string
    {
        $numero = $this->normalizarTelefono($telefono ?? $ce->venta?->cliente?->telefono);

        return 'https://wa.me/' . $numero . '?text=' . rawurlencode($this->mensaje($ce));
    }

    /**
     * Mensaje que se le manda al cliente. Corto: en el móvil, un texto largo se
     * lee como spam y el enlace queda enterrado. Tono cordial y emojis con
     * cuentagotas, como el resto del repo.
     */
    public function mensaje(VentaComprobante $ce): string
    {
        $venta   = $ce->venta;
        $empresa = $venta?->empresa;
        $negocio = $empresa?->nombre_comercial ?: $empresa?->razon_social ?: 'tu tienda';

        // Los comprobantes electrónicos solo se emiten en PEN (guarda G12 de
        // VentaAComprobante), así que el símbolo es siempre S/.
        $total = number_format((float) ($venta?->total ?? 0), 2);

        return "¡Hola! 👋 Aquí tienes tu comprobante electrónico de *{$negocio}*.\n\n"
            . '🧾 ' . $this->etiqueta($ce) . "\n"
            . "💰 Total: S/ {$total}\n\n"
            . "Descárgalo en PDF:\n"
            . $this->enlaceFirmado($ce) . "\n\n"
            . 'El enlace estará disponible ' . self::DIAS_VIGENCIA . " días. ¡Gracias por tu compra!";
    }

    /** "BOLETA B002-00000123" — lo que el cliente reconoce como "su" documento. */
    public function etiqueta(VentaComprobante $ce): string
    {
        $tipo   = self::NOMBRES_TIPO[$ce->tipo] ?? 'COMPROBANTE';
        $numero = $ce->numero ?: trim(($ce->serie ?? '') . '-' . ($ce->correlativo ?? ''), '-');

        return trim("{$tipo} {$numero}");
    }

    /** Correo registrado del cliente de la venta, si lo hay. */
    public function emailCliente(VentaComprobante $ce): ?string
    {
        $email = trim((string) ($ce->venta?->cliente?->email ?? ''));

        return $email !== '' ? $email : null;
    }

    /**
     * Bloque `compartir` que consume el frontend (lista de ventas y detalle).
     *
     * Se expone TODO junto y ya construido a propósito: el enlace firmado no se
     * puede armar en el navegador —la firma exige APP_KEY— y el prefijo de país
     * es justo la clase de detalle que se olvida al replicar la lógica en TS.
     */
    public function payload(VentaComprobante $ce): array
    {
        return [
            'whatsapp'      => $this->urlWhatsapp($ce),
            'pdf_publico'   => $this->enlaceFirmado($ce),
            'email_cliente' => $this->emailCliente($ce),
        ];
    }

    /**
     * Deja el teléfono como lo quiere `wa.me`: solo dígitos, con prefijo de país.
     *
     * En la BD los teléfonos vienen como los tecleó la cajera: "987 654 321",
     * "+51 987-654-321", "(01) 4567890". Se limpia igual que en WhatsappService
     * y se antepone el 51 SOLO cuando el número parece un móvil peruano local
     * (9 dígitos). No se toca nada más: un número de 11 dígitos ya trae prefijo
     * y un fijo de 8 no es destino válido de WhatsApp, así que se deja tal cual
     * en vez de inventarle un prefijo que lo rompería.
     */
    public function normalizarTelefono(?string $telefono): string
    {
        $digitos = preg_replace('/\D/', '', (string) $telefono) ?? '';

        if ($digitos === '') {
            return '';
        }

        if (strlen($digitos) === self::LARGO_MOVIL_LOCAL && ! str_starts_with($digitos, self::PREFIJO_PAIS)) {
            return self::PREFIJO_PAIS . $digitos;
        }

        return $digitos;
    }
}
