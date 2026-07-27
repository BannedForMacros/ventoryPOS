<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * V1b (gap G9) — Traducción de las unidades de medida del POS al Catálogo 03 de SUNAT.
 *
 * El POS deja que cada empresa invente sus abreviaturas ("BOL", "PQT", "CIL", "und"…);
 * SUNAT solo acepta un catálogo cerrado (NIU, ZZ, KGM, LTR, MTR, BX, BG, PK…). Sin esta
 * tabla habría que elegir entre rechazar la emisión o mandar todo como NIU perdiendo
 * información. La salida es: mapeo configurable por empresa, default NIU, y la
 * abreviatura original del POS conservada entre paréntesis en la descripción del ítem
 * ("Cemento Sol 42.5kg (BOL)") para que el comprobante siga siendo legible.
 *
 * Dos formas de resolver, en este orden:
 *   1. `unidad_medida_id` — match exacto por FK. Es el camino preferente.
 *   2. `abreviatura`      — match por texto, para sembrar defaults razonables
 *      ("KG" → KGM) sin tener que enumerar el id de cada empresa. Útil también
 *      cuando el ítem histórico ya no tiene la unidad viva.
 *
 * El unique es (empresa_id, unidad_medida_id): una unidad concreta se mapea una sola
 * vez por empresa. Las filas con `unidad_medida_id` NULL son las reglas por texto;
 * Postgres no considera dos NULL iguales, así que conviven sin chocar con el unique.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unidad_sunat_map', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('unidad_medida_id')->nullable()
                  ->constrained('unidades_medida')->cascadeOnDelete();

            // Abreviatura del POS para el match por texto cuando no hay id.
            $table->string('abreviatura', 20)->nullable();

            // Catálogo 03 SUNAT. NIU ("unidad") es el fallback universal seguro.
            $table->string('codigo_sunat', 5)->default('NIU');

            $table->timestamps();

            $table->unique(['empresa_id', 'unidad_medida_id']);
            $table->index(['empresa_id', 'abreviatura']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unidad_sunat_map');
    }
};
