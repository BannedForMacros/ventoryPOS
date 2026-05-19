<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M21 — Factura por item en entradas_detalle.
 *
 * Antes: una entrada tenia un solo numero_documento (cabecera). Si un proveedor
 * facturaba la mercaderia en multiples facturas pero llegaba en una sola entrega,
 * habia que crear varias entradas separadas para reflejarlo.
 *
 * Ahora: cada item del detalle puede tener su propio numero_documento. NULL
 * significa "hereda el de la cabecera" — no se duplica el valor para mantener
 * la verdad en un solo lugar.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('entradas_detalle', function (Blueprint $table) {
            $table->string('numero_documento', 50)->nullable()->after('subtotal');
        });
    }

    public function down(): void
    {
        Schema::table('entradas_detalle', function (Blueprint $table) {
            $table->dropColumn('numero_documento');
        });
    }
};
