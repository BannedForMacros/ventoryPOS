<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('metodo_pago_cuentas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('metodo_pago_id')->constrained('metodos_pago')->cascadeOnDelete();

            $table->string('nombre', 150);
            $table->string('numero_cuenta', 100)->nullable();
            $table->string('banco', 100)->nullable();
            $table->string('cci', 50)->nullable();
            $table->string('titular', 150)->nullable();

            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('metodo_pago_cuentas');
    }
};
