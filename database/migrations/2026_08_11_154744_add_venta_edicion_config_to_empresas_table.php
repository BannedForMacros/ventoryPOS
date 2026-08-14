<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            if (!Schema::hasColumn('empresas', 'cajera_puede_editar')) {
                $table->boolean('cajera_puede_editar')->default(true)->after('modo_cierre_caja');
            }
            if (!Schema::hasColumn('empresas', 'cajera_puede_anular')) {
                $table->boolean('cajera_puede_anular')->default(true)->after('cajera_puede_editar');
            }
            if (!Schema::hasColumn('empresas', 'venta_edicion_con_contador')) {
                $table->boolean('venta_edicion_con_contador')->default(true)->after('cajera_puede_anular');
            }
            if (!Schema::hasColumn('empresas', 'venta_edicion_minutos')) {
                $table->unsignedTinyInteger('venta_edicion_minutos')->default(3)->after('venta_edicion_con_contador');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn(['cajera_puede_editar', 'cajera_puede_anular', 'venta_edicion_con_contador', 'venta_edicion_minutos']);
        });
    }
};
