<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F1 — Ventas a crédito (cuentas por cobrar a clientes).
 *
 * El negocio ferretero vende a crédito a clientes de confianza: entrega la
 * mercadería hoy y cobra en días/semanas. Hasta ahora TODA venta exigía
 * pagos que cubrieran el total; el crédito se llevaba en Excel
 * ("DEUDAS POR COBRAR" del balance diario).
 *
 * Nuevos campos:
 *  - es_credito: true cuando la venta se entrega sin cobrar el total.
 *  - monto_pagado: acumulado de pagos iniciales + abonos posteriores.
 *  - saldo_pendiente: total - monto_pagado (denormalizado para listar CxC
 *    sin agregar; se actualiza en la misma transaccion que cada abono).
 *  - fecha_vencimiento: compromiso de pago opcional.
 *
 * Todos con default para no romper datos existentes (ventas viejas quedan
 * es_credito=false, saldo 0 = pagadas).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->boolean('es_credito')->default(false)->after('estado');
            $table->decimal('monto_pagado', 12, 2)->default(0)->after('es_credito');
            $table->decimal('saldo_pendiente', 12, 2)->default(0)->after('monto_pagado');
            $table->date('fecha_vencimiento')->nullable()->after('saldo_pendiente');

            // Listado de CxC: "ventas a crédito con saldo > 0 de la empresa".
            $table->index(['empresa_id', 'es_credito', 'saldo_pendiente'], 'ventas_cxc_index');
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropIndex('ventas_cxc_index');
            $table->dropColumn(['es_credito', 'monto_pagado', 'saldo_pendiente', 'fecha_vencimiento']);
        });
    }
};
