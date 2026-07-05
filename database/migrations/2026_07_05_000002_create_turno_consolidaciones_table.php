<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F8 — Consolidación de caja + descuentos de planilla.
 *
 * Flujo: la cajera cierra su turno declarando el efectivo contado (arqueo,
 * ya existía). Si la empresa activa `requiere_consolidacion_caja`, un
 * supervisor (consolidador) cuenta ÉL MISMO el dinero y registra su monto
 * viendo lo declarado. La diferencia real del turno (contado − esperado)
 * se asienta en tesorería como sobrante/faltante trazable por turno y por
 * cajera; el balance diario lee tesorería, así que el número que manda es
 * el del consolidador (o el del cierre si la consolidación está apagada).
 *
 * planilla_descuentos: faltantes (u otros cargos) que se descuentan al
 * trabajador cuando se paga la planilla. Cada caja es de una persona, así
 * que el faltante tiene responsable directo.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->boolean('requiere_consolidacion_caja')->default(false)->after('fondos_iniciales_en_declaracion');
        });

        Schema::create('turno_consolidaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turno_id')->unique()->constrained('turnos')->cascadeOnDelete();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users'); // consolidador
            $table->date('fecha');
            // Snapshot de lo que había al consolidar (inmutable aunque el turno cambie):
            $table->decimal('efectivo_declarado', 12, 2)->nullable(); // lo que contó la cajera
            $table->decimal('efectivo_esperado', 12, 2)->nullable();  // lo que el sistema esperaba
            $table->decimal('caja_chica', 12, 2)->default(0);
            $table->decimal('efectivo_contado', 12, 2);               // lo que contó el CONSOLIDADOR (manda)
            $table->decimal('diferencia_vs_declarado', 12, 2)->nullable(); // contado − declarado (cajera vs supervisor)
            $table->decimal('diferencia_vs_esperado', 12, 2)->nullable();  // contado − esperado (sobrante/faltante real)
            $table->text('observacion')->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'fecha']);
        });

        Schema::create('planilla_descuentos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');        // trabajador afectado
            $table->foreignId('registrado_por')->constrained('users'); // quién lo generó
            $table->date('fecha');
            $table->decimal('monto', 12, 2);
            $table->string('motivo', 250);
            // Origen (trazabilidad): 'turno_consolidacion' | 'turno' | null (manual)
            $table->string('ref_tipo', 40)->nullable();
            $table->unsignedBigInteger('ref_id')->nullable();
            $table->string('estado', 20)->default('pendiente'); // pendiente | aplicado | anulado
            $table->foreignId('aplicado_por')->nullable()->constrained('users');
            $table->date('fecha_aplicacion')->nullable();
            $table->text('observacion')->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'user_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planilla_descuentos');
        Schema::dropIfExists('turno_consolidaciones');
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn('requiere_consolidacion_caja');
        });
    }
};
