<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('turno_cierre_productos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turno_id')->constrained('turnos')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos');
            $table->string('producto_nombre', 200);
            $table->decimal('cantidad_vendida', 12, 3);
            $table->decimal('precio_unitario', 12, 2);
            $table->decimal('total', 12, 2);
            $table->timestamps();
            $table->unique(['turno_id', 'producto_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('turno_cierre_productos');
    }
};
