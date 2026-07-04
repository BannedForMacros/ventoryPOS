<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F5 — Deudas y préstamos (bancarios, personales, al personal).
 *
 * El balance del negocio lista pasivos que no son proveedores:
 *  "DEUDA BCP 1 - 7630", "DEUDA BCP 2 - 5557" (préstamos bancarios),
 *  "JEINER HERRERA", "JORDIN HERRERA" (préstamos de personas),
 *  "DEBEMOS AL PERSONAL" (sueldos pendientes)...
 * y también activos: préstamos QUE la empresa otorgó a terceros.
 *
 *  - direccion: 'por_pagar' (EN CONTRA) | 'por_cobrar' (A FAVOR).
 *  - tipo: bancaria | personal | trabajador | otro (solo clasificación).
 *  - saldo: baja con cada amortización en deuda_pagos y puede subir con
 *    movimientos tipo 'incremento' (nuevo desembolso sobre la misma línea).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('deudas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->string('direccion', 20); // por_pagar | por_cobrar
            $table->string('tipo', 20)->default('otro'); // bancaria | personal | trabajador | otro
            $table->string('nombre', 200);   // "Deuda BCP 1 - 7630", "Jeiner Herrera"...
            $table->decimal('monto_original', 12, 2);
            $table->decimal('saldo', 12, 2);
            $table->date('fecha_inicio');
            $table->date('fecha_vencimiento')->nullable();
            $table->string('estado', 20)->default('activa'); // activa | pagada | anulada
            $table->text('observacion')->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'direccion', 'estado']);
        });

        Schema::create('deuda_pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('deuda_id')->constrained('deudas')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('metodo_pago_id')->nullable()->constrained('metodos_pago')->nullOnDelete();
            $table->foreignId('cuenta_id')->nullable()->constrained('cuentas')->nullOnDelete();
            $table->date('fecha');
            $table->string('tipo', 20)->default('amortizacion'); // amortizacion | incremento
            $table->decimal('monto', 12, 2);
            $table->text('observacion')->nullable();
            $table->timestamps();

            $table->index(['deuda_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deuda_pagos');
        Schema::dropIfExists('deudas');
    }
};
