<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vale de devolución a nombre de una persona.
 *
 * - devoluciones.vale_cliente_id: a quién queda el vale (anticipo) cuando el
 *   reembolso es «Vale / Crédito a favor». NULL = el cliente de la venta (las
 *   devoluciones anteriores). Sirve cuando la venta fue a «Cliente General»:
 *   el vale no puede quedar sin dueño. La venta y su comprobante no cambian.
 *
 * En producción esto va por produccion/sql/2026_10_22_vale_cliente_en_devoluciones.sql.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('devoluciones', function (Blueprint $table) {
            if (! Schema::hasColumn('devoluciones', 'vale_cliente_id')) {
                $table->foreignId('vale_cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('devoluciones', fn (Blueprint $table) => $table->dropConstrainedForeignId('vale_cliente_id'));
    }
};
