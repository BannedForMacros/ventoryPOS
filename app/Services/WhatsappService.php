<?php

namespace App\Services;

class WhatsappService
{
    /**
     * Deja el teléfono como lo quiere wa.me: solo dígitos y CON prefijo de país.
     *
     * OJO, ESTO FALTABA: los teléfonos se guardan como los dicta el cliente
     * ("987 654 321", "+51 987654321", "987654321"). wa.me SIEMPRE necesita el
     * país, y un número peruano de 9 cifras sin el 51 abre WhatsApp con un
     * contacto inexistente — la persona cree que avisó y nadie recibió nada.
     *
     * Los móviles peruanos son 9 dígitos y empiezan por 9: ese caso se completa.
     * Cualquier otra cosa (ya trae país, es fijo, o es de fuera) se respeta tal
     * cual: adivinar más allá de lo seguro es cómo se manda un mensaje al país
     * equivocado.
     */
    public static function telefonoWhatsapp(?string $telefono): ?string
    {
        $digitos = preg_replace('/\D/', '', (string) $telefono);

        if ($digitos === '') {
            return null;
        }

        if (strlen($digitos) === 9 && str_starts_with($digitos, '9')) {
            return '51' . $digitos;
        }

        return $digitos;
    }

    /**
     * Genera un URL de WhatsApp (wa.me) con un mensaje pre-escrito.
     * No realiza ninguna llamada HTTP — solo devuelve el enlace.
     */
    public function generarUrlAprobacion(
        string $telefonoSupervisor,
        string $vendedorNombre,
        string $clienteNombre,
        string $conceptoNombre,
        float  $montoDescuento,
        string $ventaNumero
    ): string {
        $telefono = preg_replace('/\D/', '', $telefonoSupervisor);

        $mensaje = "✅ *Solicitud de aprobación de descuento*\n\n"
            . "🧾 Venta: {$ventaNumero}\n"
            . "👤 Vendedor: {$vendedorNombre}\n"
            . "🛒 Cliente: {$clienteNombre}\n"
            . "🏷️ Concepto: {$conceptoNombre}\n"
            . "💰 Monto descuento: S/ " . number_format($montoDescuento, 2) . "\n\n"
            . "Responde *APROBADO* o *RECHAZADO*.";

        return 'https://wa.me/' . $telefono . '?text=' . rawurlencode($mensaje);
    }

    /**
     * Plantilla por defecto del recordatorio. Sirve para peluquería, veterinaria
     * y taller sin cambiar una palabra: por eso dice "tu cita" y no "tu corte".
     */
    public const PLANTILLA_RECORDATORIO =
        "Hola {cliente} 👋\n\nTe recordamos tu cita en *{negocio}*:\n"
        . "📅 {fecha}\n🕐 {hora}\n💇 {servicios}\n\n"
        . "Si no puedes venir, avísanos para reprogramarla. ¡Te esperamos!";

    /**
     * Texto del recordatorio con los datos de la cita ya puestos.
     *
     * Las llaves que la empresa no use simplemente no aparecen; una plantilla a
     * medias no debe dejar "{profesional}" crudo en el mensaje que lee el
     * cliente, así que lo que quede sin reemplazar se limpia al final.
     */
    public static function textoRecordatorio(array $datos, ?string $plantilla = null): string
    {
        $texto = trim((string) $plantilla) !== '' ? $plantilla : self::PLANTILLA_RECORDATORIO;

        foreach ($datos as $clave => $valor) {
            $texto = str_replace('{' . $clave . '}', (string) $valor, $texto);
        }

        // Cualquier variable mal escrita o no provista se borra en vez de viajar.
        return trim(preg_replace('/\{[a-z_]+\}/i', '', $texto));
    }

    /** Enlace de WhatsApp listo para abrir, o null si no hay teléfono usable. */
    public static function urlRecordatorio(?string $telefono, string $mensaje): ?string
    {
        $numero = self::telefonoWhatsapp($telefono);

        return $numero ? 'https://wa.me/' . $numero . '?text=' . rawurlencode($mensaje) : null;
    }

    /**
     * Genera un URL de WhatsApp para notificar al cliente sobre su compra.
     */
    public function generarUrlConfirmacionCliente(
        string $telefonoCliente,
        string $ventaNumero,
        float  $total
    ): string {
        $telefono = preg_replace('/\D/', '', $telefonoCliente);

        $mensaje = "🎉 *Gracias por tu compra!*\n\n"
            . "🧾 Comprobante: {$ventaNumero}\n"
            . "💰 Total: S/ " . number_format($total, 2) . "\n\n"
            . "¡Vuelve pronto!";

        return 'https://wa.me/' . $telefono . '?text=' . rawurlencode($mensaje);
    }
}
