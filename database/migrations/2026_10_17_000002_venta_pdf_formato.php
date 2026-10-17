<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Formato del PDF de la venta que se manda al cliente por WhatsApp.
 *
 * - empresas.venta_pdf_formato: 'a4' (hoja completa) o 'ticket' (80 mm).
 *   Es el formato por defecto; en el diálogo de envío se puede cambiar.
 *
 * En producción esto va por produccion/sql/2026_10_17_venta_pdf_formato.sql.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            if (! Schema::hasColumn('empresas', 'venta_pdf_formato')) {
                $table->string('venta_pdf_formato', 10)->default('a4');
            }
        });
    }

    public function down(): void
    {
        Schema::table('empresas', fn (Blueprint $table) => $table->dropColumn('venta_pdf_formato'));
    }
};
