<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Envío del PDF de la venta por WhatsApp (opcional por empresa).
 *
 * - empresas.venta_whatsapp_pdf: la lista de ventas muestra el botón WhatsApp,
 *   que abre WhatsApp con el número del cliente y el enlace firmado al PDF.
 *   Apagado = no hay botón y los enlaces ya enviados dejan de abrir.
 *
 * En producción esto va por produccion/sql/2026_10_17_venta_whatsapp_pdf.sql.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            if (! Schema::hasColumn('empresas', 'venta_whatsapp_pdf')) {
                $table->boolean('venta_whatsapp_pdf')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('empresas', fn (Blueprint $table) => $table->dropColumn('venta_whatsapp_pdf'));
    }
};
