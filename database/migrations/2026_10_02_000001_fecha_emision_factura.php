<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fecha de emisión elegible para la factura (opcional por empresa).
 *
 * - empresas.pos_fecha_emision_factura: el POS muestra un selector de fecha al
 *   elegir Factura. Apagado = la factura sale con la fecha de la venta, como siempre.
 * - ventas.fecha_emision: fecha con la que se emite el comprobante. NULL = la de la
 *   venta. Va APARTE de `fecha_venta` a propósito: la venta (caja, turno, reportes)
 *   sigue siendo de hoy; solo la factura lleva la fecha elegida.
 *
 * En producción esto va por produccion/sql/2026_10_02_fecha_emision_factura.sql.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            if (! Schema::hasColumn('empresas', 'pos_fecha_emision_factura')) {
                $table->boolean('pos_fecha_emision_factura')->default(false);
            }
        });

        Schema::table('ventas', function (Blueprint $table) {
            if (! Schema::hasColumn('ventas', 'fecha_emision')) {
                $table->date('fecha_emision')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('ventas', fn (Blueprint $table) => $table->dropColumn('fecha_emision'));
        Schema::table('empresas', fn (Blueprint $table) => $table->dropColumn('pos_fecha_emision_factura'));
    }
};
