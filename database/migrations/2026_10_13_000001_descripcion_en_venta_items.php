<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Descripción opcional por línea de venta.
 *
 * Las empresas que venden el mismo producto en varias líneas (cada una con su
 * precio) no tenían cómo distinguirlas: veinte líneas de "Pulsera" se leían
 * iguales en el carrito y en el ticket. La cajera puede anotar en cada línea
 * algo corto ("roja", "talla M") que sale en el ticket interno, nunca en el
 * comprobante electrónico.
 *
 * En producción esto va por produccion/sql/2026_10_13_descripcion_en_venta_items.sql.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('venta_items', function (Blueprint $table) {
            $table->string('descripcion', 120)->nullable()->after('unidad_nombre');
        });
    }

    public function down(): void
    {
        Schema::table('venta_items', function (Blueprint $table) {
            $table->dropColumn('descripcion');
        });
    }
};
