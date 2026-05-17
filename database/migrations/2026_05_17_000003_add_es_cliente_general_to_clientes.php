<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A15 — Reemplazar la convención mágica "DNI 99999999 = Cliente General"
 * por una columna booleana explícita.
 *
 * Motivación: si SUNAT cambia el rango de DNIs válidos, o si la empresa
 * opera en otro país (RUC, CE), el sistema deja de poder distinguir el
 * Cliente General y rompe (VentaService no encuentra fallback, ClienteController
 * permite editar/borrar lo que no debería).
 *
 * Esta migración:
 *  1) Agrega la columna `es_cliente_general` (boolean, default false).
 *  2) Hace backfill: clientes con numero_documento='99999999' pasan a true.
 *  3) Agrega un partial unique index para garantizar que cada empresa tenga
 *     a lo sumo un cliente general (la convención lo asume implícitamente).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->boolean('es_cliente_general')->default(false)->after('activo');
        });

        // Backfill desde la convención anterior (DNI 99999999 por empresa).
        DB::table('clientes')
            ->where('numero_documento', '99999999')
            ->update(['es_cliente_general' => true]);

        // Garantiza un solo cliente general por empresa (regla implícita).
        DB::statement(
            'CREATE UNIQUE INDEX clientes_un_general_por_empresa '
            . 'ON clientes (empresa_id) WHERE es_cliente_general = true'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS clientes_un_general_por_empresa');
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn('es_cliente_general');
        });
    }
};
