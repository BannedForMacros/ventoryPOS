<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gastos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('local_id')->constrained('locales')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('turno_id')->nullable()->constrained('turnos')->nullOnDelete();
            $table->foreignId('gasto_tipo_id')->constrained('gasto_tipos');
            $table->foreignId('gasto_concepto_id')->constrained('gasto_conceptos');
            $table->decimal('monto', 12, 2);
            $table->date('fecha');
            $table->text('comentario')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gastos');
    }
};
