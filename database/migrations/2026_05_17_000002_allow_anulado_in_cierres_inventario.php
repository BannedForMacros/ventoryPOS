<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A8 — Permitir estado='anulado' en cierres_inventario.
 *
 * Cuando se reabre un turno cerrado en modo `declarado`, el cierre de
 * inventario confirmado asociado debe marcarse como anulado para que el
 * próximo cierre del mismo turno no choque y para que el historial refleje
 * claramente cuál cierre quedó sin validez.
 */
return new class extends Migration {
    public function up(): void
    {
        DB::statement('ALTER TABLE cierres_inventario DROP CONSTRAINT IF EXISTS cierres_inventario_estado_check');
        DB::statement("ALTER TABLE cierres_inventario ADD CONSTRAINT cierres_inventario_estado_check "
            . "CHECK (estado IN ('borrador', 'confirmado', 'anulado'))");
    }

    public function down(): void
    {
        // Reversión: si algún cierre quedó 'anulado', migrar a 'confirmado'
        // primero para no violar el constraint anterior.
        DB::statement("UPDATE cierres_inventario SET estado='confirmado' WHERE estado='anulado'");
        DB::statement('ALTER TABLE cierres_inventario DROP CONSTRAINT IF EXISTS cierres_inventario_estado_check');
        DB::statement("ALTER TABLE cierres_inventario ADD CONSTRAINT cierres_inventario_estado_check "
            . "CHECK (estado IN ('borrador', 'confirmado'))");
    }
};
