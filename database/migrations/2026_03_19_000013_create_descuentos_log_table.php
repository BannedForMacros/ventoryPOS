<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('descuentos_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('venta_id')->nullable()->constrained('ventas')->nullOnDelete();
            $table->foreignId('venta_item_id')->nullable()->constrained('venta_items')->nullOnDelete();
            $table->foreignId('descuento_concepto_id')->constrained('descuento_conceptos');
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->foreignId('aprobado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('monto_descuento', 12, 2);
            $table->boolean('requeria_aprobacion')->default(false);
            $table->boolean('notificacion_enviada')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('descuentos_log');
    }
};
