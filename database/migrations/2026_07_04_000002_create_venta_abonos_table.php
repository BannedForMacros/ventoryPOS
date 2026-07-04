<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F1 — Abonos a ventas a crédito.
 *
 * Cada cobro parcial de una venta a crédito se registra aquí (no en
 * venta_pagos, que representa los pagos DEL MOMENTO de la venta y alimenta
 * el arqueo del turno). Un abono llega días después, muchas veces sin turno
 * abierto (transferencia al BCP), por eso vive en su propia tabla con
 * metodo + cuenta destino.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('venta_abonos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->constrained('ventas')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('metodo_pago_id')->nullable()->constrained('metodos_pago')->nullOnDelete();
            $table->foreignId('cuenta_id')->nullable()->constrained('cuentas')->nullOnDelete();
            $table->date('fecha');
            $table->decimal('monto', 12, 2);
            $table->string('referencia', 200)->nullable();
            $table->text('observacion')->nullable();
            $table->timestamps();

            $table->index(['venta_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venta_abonos');
    }
};
