<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Planilla de caja por turno (función opcional por empresa).
 *
 * - empresas.usa_planilla_caja: activa la planilla en el detalle del turno.
 * - planilla_columnas: las columnas de dinero que la empresa quiere ver
 *   (ej. "Efectivo", "Depósitos", "Yape / Cuenta BCP").
 * - metodos_pago.planilla_columna_id: a qué columna suma cada medio de pago.
 *   Sin columna, el medio sale en una columna con su propio nombre.
 *
 * En producción esto va por produccion/sql/2026_09_29_planilla_caja.sql.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            if (!Schema::hasColumn('empresas', 'usa_planilla_caja')) {
                $table->boolean('usa_planilla_caja')->default(false);
            }
        });

        if (!Schema::hasTable('planilla_columnas')) {
            Schema::create('planilla_columnas', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->string('nombre', 60);
                $table->unsignedSmallInteger('orden')->default(0);
                $table->timestamps();
                $table->index(['empresa_id', 'orden']);
            });
        }

        Schema::table('metodos_pago', function (Blueprint $table) {
            if (!Schema::hasColumn('metodos_pago', 'planilla_columna_id')) {
                $table->foreignId('planilla_columna_id')->nullable()->constrained('planilla_columnas')->nullOnDelete();
            }
        });

    }

    public function down(): void
    {
        Schema::table('metodos_pago', function (Blueprint $table) {
            $table->dropConstrainedForeignId('planilla_columna_id');
        });
        Schema::dropIfExists('planilla_columnas');
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn('usa_planilla_caja');
        });
    }
};
