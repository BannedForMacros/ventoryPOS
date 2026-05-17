<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migra metodos_pago.tipo (enum nativo) → metodos_pago.tipo_id (FK a tipos_metodo_pago).
 *
 * Estrategia segura:
 *   1. Agregar tipo_id nullable.
 *   2. Backfill mapeando enum string → id de tipos_metodo_pago.slug.
 *   3. Hacer tipo_id NOT NULL.
 *   4. Dropear columna tipo y tipo PG metodo_pago_tipo_enum.
 *   5. Recrear UNIQUE (empresa_id, nombre).
 *
 * Rollback recrea el enum y restituye los valores antiguos.
 */
return new class extends Migration {
    public function up(): void
    {
        // 1. Columna FK nullable temporalmente
        Schema::table('metodos_pago', function (Blueprint $table) {
            $table->foreignId('tipo_id')->nullable()->after('nombre')
                  ->constrained('tipos_metodo_pago')->restrictOnDelete();
        });

        // 2. Backfill: por cada slug, copiar tipos_metodo_pago.id a metodos_pago.tipo_id
        //    donde metodos_pago.tipo coincida con el slug.
        $tipos = DB::table('tipos_metodo_pago')->pluck('id', 'slug');
        foreach ($tipos as $slug => $id) {
            DB::table('metodos_pago')->where('tipo', $slug)->update(['tipo_id' => $id]);
        }

        // 3. Validar que no quede ningun registro sin mapear; si lo hay, hacer fallback a 'otro'.
        $otroId = $tipos['otro'] ?? null;
        if ($otroId) {
            DB::table('metodos_pago')->whereNull('tipo_id')->update(['tipo_id' => $otroId]);
        }

        // 4. tipo_id NOT NULL
        DB::statement('ALTER TABLE metodos_pago ALTER COLUMN tipo_id SET NOT NULL');

        // 5. Drop columna tipo y enum PG
        Schema::table('metodos_pago', function (Blueprint $table) {
            $table->dropColumn('tipo');
        });
        DB::statement('DROP TYPE IF EXISTS metodo_pago_tipo_enum');
    }

    public function down(): void
    {
        // Recrear enum
        DB::statement("CREATE TYPE metodo_pago_tipo_enum AS ENUM (
            'efectivo','tarjeta_debito','tarjeta_credito','transferencia','yape','plin','otro'
        )");

        Schema::table('metodos_pago', function (Blueprint $table) {
            $table->string('tipo_tmp', 30)->default('efectivo')->after('nombre');
        });

        // Restituir slug desde la FK
        $tipos = DB::table('tipos_metodo_pago')->pluck('slug', 'id');
        foreach ($tipos as $id => $slug) {
            DB::table('metodos_pago')->where('tipo_id', $id)->update(['tipo_tmp' => $slug]);
        }

        // Convertir la columna tmp al tipo enum
        DB::statement('ALTER TABLE metodos_pago ADD COLUMN tipo metodo_pago_tipo_enum');
        DB::statement("UPDATE metodos_pago SET tipo = tipo_tmp::metodo_pago_tipo_enum");
        DB::statement('ALTER TABLE metodos_pago ALTER COLUMN tipo SET NOT NULL');
        DB::statement("ALTER TABLE metodos_pago ALTER COLUMN tipo SET DEFAULT 'efectivo'::metodo_pago_tipo_enum");

        Schema::table('metodos_pago', function (Blueprint $table) {
            $table->dropForeign(['tipo_id']);
            $table->dropColumn(['tipo_id', 'tipo_tmp']);
        });
    }
};
