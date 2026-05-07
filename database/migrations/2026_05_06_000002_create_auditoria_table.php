<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabla de auditoria: registra acciones sensibles del sistema (anulaciones,
 * reaperturas de turno, cambios de permisos, recalculos de stock, etc.).
 * Solo se loggean acciones que importan operativa o legalmente. NO es un
 * change-data-capture sobre todo el modelo.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('auditoria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            // Snapshot del nombre del usuario al momento del evento. Sobrevive
            // aunque despues se elimine o renombre el user.
            $table->string('user_name', 150);
            // Identificador semantico de la accion. Ej: 'venta.anulada', 'turno.reabierto'.
            $table->string('accion', 80);
            // Modelo afectado (opcional). Ej: 'App\Models\Venta', '12'.
            $table->string('modelo_tipo', 150)->nullable();
            $table->unsignedBigInteger('modelo_id')->nullable();
            // Contexto adicional libre (motivo, valores anteriores/nuevos, etc.).
            $table->jsonb('contexto')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index('empresa_id');
            $table->index(['empresa_id', 'created_at']);
            $table->index(['empresa_id', 'accion']);
            $table->index(['modelo_tipo', 'modelo_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditoria');
    }
};
