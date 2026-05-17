<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A10 — Garantizar unicidad de (turno_id, numero) en ventas.
 *
 * El método anterior `Venta::generarNumero` usaba `lockForUpdate` sobre una
 * subquery, lo cual no garantiza bloqueo confiable en Postgres y permitía
 * que dos requests concurrentes generen el mismo número.
 *
 * Con este UNIQUE constraint, dos inserts simultáneos chocan a nivel de BD
 * y el segundo recibe UniqueConstraintViolationException. VentaService captura
 * el error, regenera el número y reintenta — patrón "optimistic insert + retry".
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->unique(['turno_id', 'numero'], 'ventas_turno_id_numero_unique');
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropUnique('ventas_turno_id_numero_unique');
        });
    }
};
