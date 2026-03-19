<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ventas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('local_id')->constrained('locales')->cascadeOnDelete();
            $table->foreignId('turno_id')->constrained('turnos');
            $table->foreignId('caja_id')->constrained('cajas');
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->string('numero', 20)->nullable();
            $table->enum('tipo_comprobante', ['ticket', 'boleta', 'factura'])->default('ticket');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('descuento_total', 12, 2)->default(0);
            $table->foreignId('descuento_concepto_id')->nullable()->constrained('descuento_conceptos')->nullOnDelete();
            $table->decimal('igv', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->enum('estado', ['completada', 'anulada'])->default('completada');
            $table->text('observacion')->nullable();
            $table->datetime('fecha_venta');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ventas');
    }
};
