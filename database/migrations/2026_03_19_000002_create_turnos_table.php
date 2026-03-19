<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('turnos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('local_id')->constrained('locales')->cascadeOnDelete();
            $table->foreignId('caja_id')->constrained('cajas')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('user_cierre_id')->nullable()->constrained('users');
            $table->decimal('monto_apertura', 12, 2)->default(0);
            $table->decimal('monto_caja_chica', 12, 2)->default(0);
            $table->decimal('monto_cierre_declarado', 12, 2)->nullable();
            $table->decimal('monto_cierre_esperado', 12, 2)->nullable();
            $table->decimal('diferencia', 12, 2)->nullable();
            $table->enum('estado', ['abierto', 'cerrado'])->default('abierto');
            $table->datetime('fecha_apertura');
            $table->datetime('fecha_cierre')->nullable();
            $table->text('observacion_apertura')->nullable();
            $table->text('observacion_cierre')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('turnos');
    }
};
