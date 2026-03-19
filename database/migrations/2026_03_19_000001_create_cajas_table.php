<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cajas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('local_id')->constrained('locales')->cascadeOnDelete();
            $table->string('nombre', 100);
            $table->boolean('caja_chica_activa')->default(false);
            $table->decimal('caja_chica_monto_sugerido', 12, 2)->default(0);
            $table->boolean('caja_chica_en_arqueo')->default(false);
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->unique(['local_id', 'nombre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cajas');
    }
};
