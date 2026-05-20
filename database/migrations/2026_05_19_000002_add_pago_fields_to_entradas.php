<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M22 — Estado de pago + metodo + cuenta en entradas.
 *
 * Antes: una entrada se confirmaba y movia stock, pero NO sabiamos si ya se
 * habia pagado al proveedor. Tipico flujo "te dejo la mercaderia hoy, me
 * pagas la semana que viene" quedaba sin reflejo en el sistema.
 *
 * Ahora:
 *  - estado_pago: 'pendiente' (default) | 'pagado'. Independiente del estado
 *    de la entrada (puede estar confirmada Y pendiente de pago a la vez).
 *  - metodo_pago_id: FK al metodo elegido cuando se marca como pagado.
 *  - cuenta_id: FK opcional a la cuenta especifica dentro del metodo (ej:
 *    "Yape numero 1" vs "Yape numero 2", o "BCP corriente" vs "BCP ahorros").
 *
 * Todos NULLABLE para no romper datos existentes (que pasan a default
 * 'pendiente' automaticamente).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('entradas', function (Blueprint $table) {
            $table->string('estado_pago', 20)->default('pendiente')->after('estado');
            $table->foreignId('metodo_pago_id')->nullable()->after('estado_pago')->constrained('metodos_pago')->nullOnDelete();
            $table->foreignId('cuenta_id')->nullable()->after('metodo_pago_id')->constrained('cuentas')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('entradas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cuenta_id');
            $table->dropConstrainedForeignId('metodo_pago_id');
            $table->dropColumn('estado_pago');
        });
    }
};
