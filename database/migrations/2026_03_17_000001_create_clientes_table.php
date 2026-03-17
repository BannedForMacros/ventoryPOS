<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();

            $table->enum('tipo_documento', ['DNI', 'RUC', 'CE', 'pasaporte', 'otro'])->default('DNI');
            $table->string('numero_documento', 20)->nullable();

            $table->string('nombres', 100)->nullable();
            $table->string('apellidos', 100)->nullable();
            $table->string('razon_social', 200)->nullable();

            $table->string('telefono', 20)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('direccion', 255)->nullable();

            $table->date('fecha_nacimiento')->nullable();

            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['empresa_id', 'numero_documento']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
