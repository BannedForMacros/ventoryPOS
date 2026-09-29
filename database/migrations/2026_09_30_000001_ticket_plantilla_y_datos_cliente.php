<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ticket por plantilla y datos del cliente en la venta (opcionales por empresa).
 *
 * - empresas.ticket_plantilla: qué plantilla usa la empresa, qué secciones
 *   salen, en qué orden y con qué textos. Null = el ticket de siempre.
 * - empresas.pos_datos_cliente: el POS pide teléfono, dirección y observación.
 * - users.telefono: celular de quien atendió, para el ticket.
 * - ventas / cotizaciones .cliente_telefono y .cliente_direccion: los datos
 *   con que se atendió ESA operación (la obra puede no ser la dirección de la
 *   ficha del cliente).
 *
 * En producción esto va por produccion/sql/2026_09_30_ticket_plantilla.sql.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            if (!Schema::hasColumn('empresas', 'ticket_plantilla')) {
                $table->json('ticket_plantilla')->nullable();
            }
            if (!Schema::hasColumn('empresas', 'pos_datos_cliente')) {
                $table->boolean('pos_datos_cliente')->default(false);
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'telefono')) {
                $table->string('telefono', 20)->nullable();
            }
        });

        foreach (['ventas', 'cotizaciones'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) use ($tabla) {
                if (!Schema::hasColumn($tabla, 'cliente_telefono')) {
                    $table->string('cliente_telefono', 30)->nullable();
                }
                if (!Schema::hasColumn($tabla, 'cliente_direccion')) {
                    $table->string('cliente_direccion', 255)->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('empresas', fn (Blueprint $t) => $t->dropColumn(['ticket_plantilla', 'pos_datos_cliente']));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('telefono'));
        foreach (['ventas', 'cotizaciones'] as $tabla) {
            Schema::table($tabla, fn (Blueprint $t) => $t->dropColumn(['cliente_telefono', 'cliente_direccion']));
        }
    }
};
