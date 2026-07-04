<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F6 — Balance diario (réplica del Excel "BALANCE FERRETERIA H&C").
 *
 * Foto patrimonial que el dueño arma cada día:
 *
 *   BALANCE HOY     = Σ(A FAVOR) − Σ(EN CONTRA)
 *   UTILIDAD REAL   = (BALANCE HOY − BALANCE AYER) + GASTOS DEL DÍA
 *
 * El sistema PRE-CALCULA las líneas automáticas (stock valorizado, CxC,
 * CxP, anticipos, adelantos, deudas, gastos del día) y el usuario ingresa
 * las manuales (saldo real de cada cuenta bancaria y efectivo, ajustes),
 * concilia línea por línea (el "OK" de su Excel) y confirma. Al confirmar,
 * el snapshot queda inmutable y sirve de "BALANCE AYER" para el siguiente.
 *
 * balance_diario_items.categoria (slug) agrupa las líneas:
 *   favor : cuenta_bancaria | efectivo | stock | cxc | adelanto_proveedor |
 *           prestamo_otorgado | otro_favor
 *   contra: cxp | anticipo_cliente | deuda | personal | otro_contra
 *
 * ref_tipo/ref_id enlazan la línea con su origen (cuenta, deuda, etc.)
 * sin FK dura, porque el snapshot debe sobrevivir aunque el origen cambie.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('balances_diarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->date('fecha');
            $table->string('estado', 20)->default('borrador'); // borrador | confirmado
            $table->decimal('total_favor', 14, 2)->default(0);
            $table->decimal('total_contra', 14, 2)->default(0);
            $table->decimal('balance_neto', 14, 2)->default(0);
            $table->decimal('balance_anterior', 14, 2)->nullable(); // neto del último confirmado previo
            $table->decimal('diferencia', 14, 2)->nullable();       // neto - anterior
            $table->decimal('gastos_dia', 14, 2)->default(0);
            $table->decimal('utilidad_real', 14, 2)->nullable();    // diferencia + gastos_dia
            $table->text('observacion')->nullable();
            $table->timestamps();

            $table->unique(['empresa_id', 'fecha']);
        });

        Schema::create('balance_diario_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('balance_diario_id')->constrained('balances_diarios')->cascadeOnDelete();
            $table->string('seccion', 10);    // favor | contra
            $table->string('categoria', 30);  // ver doc de cabecera
            $table->string('descripcion', 250);
            $table->string('ref_tipo', 40)->nullable(); // 'cuenta', 'deuda', 'proveedor', 'cliente'...
            $table->unsignedBigInteger('ref_id')->nullable();
            $table->decimal('monto', 14, 2);
            $table->boolean('es_manual')->default(false);  // línea ingresada/ajustada a mano
            $table->boolean('conciliado')->default(false); // el "OK" del Excel
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();

            $table->index(['balance_diario_id', 'seccion']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('balance_diario_items');
        Schema::dropIfExists('balances_diarios');
    }
};
