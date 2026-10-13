<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Costo de catálogo con 4 decimales (antes 2).
 *
 * Con 2 decimales, el costo de un producto que se vende por unidad suelta
 * (una tableta de S/ 0.0163) se guardaba como 0.02: 23% de error en el margen.
 * El kardex y las entradas ya trabajan con 4. Se pasa a numeric(14,4) para no
 * perder rango entero (12,4 bajaría el máximo a 99,999,999).
 *
 * Solo se amplía: ningún valor existente cambia.
 *
 * En producción esto va por produccion/sql/2026_10_12_costo_catalogo_cuatro_decimales.sql.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE productos ALTER COLUMN precio_costo TYPE numeric(14,4)');
        DB::statement('ALTER TABLE producto_unidades ALTER COLUMN precio_costo TYPE numeric(14,4)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE productos ALTER COLUMN precio_costo TYPE numeric(12,2)');
        DB::statement('ALTER TABLE producto_unidades ALTER COLUMN precio_costo TYPE numeric(12,2)');
    }
};
