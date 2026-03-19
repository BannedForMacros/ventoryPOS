<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('turno_arqueo', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turno_id')->constrained('turnos')->cascadeOnDelete();
            $table->decimal('denominacion', 8, 2);
            $table->integer('cantidad')->default(0);
            $table->decimal('subtotal', 12, 2)->storedAs('denominacion * cantidad');
            $table->timestamps();
            $table->unique(['turno_id', 'denominacion']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('turno_arqueo');
    }
};
