<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('turno_arqueo_metodos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turno_id')->constrained('turnos')->cascadeOnDelete();
            $table->foreignId('metodo_pago_id')->constrained('metodos_pago');
            $table->decimal('monto_declarado', 12, 2)->default(0);
            $table->timestamps();
            $table->unique(['turno_id', 'metodo_pago_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('turno_arqueo_metodos');
    }
};
