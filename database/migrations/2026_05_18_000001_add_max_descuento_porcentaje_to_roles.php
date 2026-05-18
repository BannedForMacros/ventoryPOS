<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M20 — Tope porcentual de descuento por rol.
 *
 * Antes: cualquier cajero podía aplicar 100% de descuento a cualquier venta
 * sin justificar nada. Con este campo, el admin define cuánto puede descontar
 * cada rol (NULL = sin tope, es_admin típicamente NULL).
 *
 * El check se ejecuta en StoreVentaRequest. Si el descuento solicitado supera
 * el tope del rol del cajero, la venta no pasa validación.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            // 0–100% con 2 decimales. NULL = sin tope.
            $table->decimal('max_descuento_porcentaje', 5, 2)->nullable()->after('es_admin');
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('max_descuento_porcentaje');
        });
    }
};
