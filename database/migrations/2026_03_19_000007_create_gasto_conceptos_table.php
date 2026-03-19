<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gasto_conceptos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('gasto_tipo_id')->constrained('gasto_tipos')->cascadeOnDelete();
            $table->string('nombre', 150);
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->unique(['empresa_id', 'nombre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gasto_conceptos');
    }
};
