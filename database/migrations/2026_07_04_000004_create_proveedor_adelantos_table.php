<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F3 — Adelantos a proveedores ("ADELANTO DE PROVEEDORES" del balance).
 *
 * La empresa paga al proveedor ANTES de recibir el material (asegura precio
 * o cupo de producción: Uyustools, Ardiles, Prodac, Cofesa...). Es un
 * ACTIVO: plata de la empresa estacionada en manos del proveedor.
 *
 * El saldo baja cuando el adelanto se aplica contra una entrada de
 * mercadería (proveedor_adelanto_aplicaciones) o cuando el proveedor
 * devuelve el dinero (estado 'devuelto').
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('proveedor_adelantos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('proveedor_id')->constrained('proveedores');
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('metodo_pago_id')->nullable()->constrained('metodos_pago')->nullOnDelete();
            $table->foreignId('cuenta_id')->nullable()->constrained('cuentas')->nullOnDelete();
            $table->date('fecha');
            $table->decimal('monto', 12, 2);
            $table->decimal('saldo', 12, 2);
            $table->string('estado', 20)->default('activo'); // activo | aplicado | devuelto | anulado
            $table->string('referencia', 200)->nullable();
            $table->text('observacion')->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'estado']);
        });

        Schema::create('proveedor_adelanto_aplicaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proveedor_adelanto_id')->constrained('proveedor_adelantos')->cascadeOnDelete();
            $table->foreignId('entrada_id')->nullable()->constrained('entradas')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->date('fecha');
            $table->decimal('monto', 12, 2);
            $table->text('observacion')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proveedor_adelanto_aplicaciones');
        Schema::dropIfExists('proveedor_adelantos');
    }
};
