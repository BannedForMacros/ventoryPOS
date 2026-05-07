<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega idempotency_key a ventas para que el POS pueda reintentar sin duplicar.
 * Se usa un UNIQUE INDEX parcial (Postgres): se respeta la unicidad solo cuando
 * el valor no es null, para no afectar las ventas historicas que vienen sin key.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('ventas', 'idempotency_key')) {
            Schema::table('ventas', function ($table) {
                $table->string('idempotency_key', 100)->nullable()->after('numero');
            });
        }

        // Indice unico parcial: solo aplica cuando el valor NO es null.
        DB::statement(
            'CREATE UNIQUE INDEX IF NOT EXISTS ventas_idempotency_key_unique
             ON ventas (idempotency_key) WHERE idempotency_key IS NOT NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS ventas_idempotency_key_unique');
        if (Schema::hasColumn('ventas', 'idempotency_key')) {
            Schema::table('ventas', function ($table) {
                $table->dropColumn('idempotency_key');
            });
        }
    }
};
