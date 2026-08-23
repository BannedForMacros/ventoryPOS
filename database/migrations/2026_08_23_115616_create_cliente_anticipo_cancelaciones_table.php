<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cliente_anticipo_cancelaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_anticipo_id')->constrained('cliente_anticipos')->cascadeOnDelete();
            $table->foreignId('cliente_anticipo_item_id')->constrained('cliente_anticipo_items')->cascadeOnDelete();
            $table->foreignId('empresa_id')->constrained('empresas');
            $table->foreignId('user_id')->constrained('users');
            $table->date('fecha');
            $table->decimal('cantidad', 16, 4);
            $table->decimal('monto', 12, 2);
            $table->text('motivo');
            $table->foreignId('turno_id')->nullable()->constrained('turnos');
            $table->foreignId('caja_id')->nullable()->constrained('cajas');
            $table->foreignId('metodo_pago_id')->nullable()->constrained('metodos_pago');
            $table->foreignId('cuenta_id')->nullable()->constrained('cuentas');
            $table->string('observacion', 500)->nullable();
            $table->string('moneda', 3)->default('PEN');
            $table->decimal('tipo_cambio', 12, 6)->nullable();
            $table->decimal('monto_moneda', 12, 2)->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'fecha']);
            $table->index('cliente_anticipo_id');
            $table->index('turno_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cliente_anticipo_cancelaciones');
    }
};
