<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F4 — Pagos parciales a proveedores (cuentas por pagar con abonos).
 *
 * M22 agregó estado_pago pendiente/pagado como flag binario, pero el negocio
 * paga a proveedores EN ABONOS ("te dejo 5 mil hoy, el resto la próxima
 * semana"). Cada abono se registra aquí y `entradas.monto_pagado` acumula.
 *
 * estado_pago gana el valor intermedio 'parcial':
 *   pendiente (0 pagado) → parcial (0 < pagado < total) → pagado.
 *
 * Un pago puede salir de un adelanto previamente entregado al proveedor
 * (proveedor_adelanto_id): en ese caso no sale dinero nuevo, se consume el
 * activo "adelanto" contra el pasivo "cuenta por pagar".
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('entradas', function (Blueprint $table) {
            $table->decimal('monto_pagado', 12, 2)->default(0)->after('total');
        });

        // Backfill: las entradas ya marcadas 'pagado' (flag binario de M22)
        // quedan con monto_pagado = total para que el saldo dé 0.
        DB::table('entradas')->where('estado_pago', 'pagado')->update([
            'monto_pagado' => DB::raw('total'),
        ]);

        Schema::create('entrada_pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entrada_id')->constrained('entradas')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('metodo_pago_id')->nullable()->constrained('metodos_pago')->nullOnDelete();
            $table->foreignId('cuenta_id')->nullable()->constrained('cuentas')->nullOnDelete();
            $table->foreignId('proveedor_adelanto_id')->nullable()->constrained('proveedor_adelantos')->nullOnDelete();
            $table->date('fecha');
            $table->decimal('monto', 12, 2);
            $table->string('referencia', 200)->nullable();
            $table->text('observacion')->nullable();
            $table->timestamps();

            $table->index(['entrada_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entrada_pagos');
        Schema::table('entradas', function (Blueprint $table) {
            $table->dropColumn('monto_pagado');
        });
    }
};
