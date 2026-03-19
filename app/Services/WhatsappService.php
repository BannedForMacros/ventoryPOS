<?php

namespace App\Services;

class WhatsappService
{
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
