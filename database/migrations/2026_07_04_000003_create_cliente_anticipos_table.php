<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F2 — Anticipos de clientes ("CLIENTES ANTICIPOS" del balance diario).
 *
 * Clientes que confían en la empresa le entregan dinero por adelantado y
 * retiran material después. Es un PASIVO: la empresa debe mercadería (o la
 * devolución del dinero).
 *
 * Valorización — el negocio maneja dos modalidades:
 *  - 'monto': el anticipo es dinero puro; el pasivo es el saldo en soles.
 *  - 'material': el anticipo compró una cantidad de producto a precio
 *    pactado; el pasivo se valoriza A PRECIO DEL DÍA
 *    (cantidad_pendiente × precio_venta actual del producto), porque si el
 *    ladrillo sube, lo que se debe entregar vale más.
 *
 * saldo / cantidad_pendiente se descuentan con cada aplicación
 * (cliente_anticipo_aplicaciones) y el estado pasa a 'aplicado' al agotarse.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('cliente_anticipos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('metodo_pago_id')->nullable()->constrained('metodos_pago')->nullOnDelete();
            $table->foreignId('cuenta_id')->nullable()->constrained('cuentas')->nullOnDelete();
            $table->date('fecha');
            $table->decimal('monto', 12, 2);           // dinero recibido
            $table->decimal('saldo', 12, 2);           // lo aún no aplicado (modalidad monto)
            $table->string('tipo_valorizacion', 20)->default('monto'); // 'monto' | 'material'
            $table->foreignId('producto_id')->nullable()->constrained('productos')->nullOnDelete();
            $table->decimal('cantidad', 12, 4)->nullable();            // comprometida (modalidad material)
            $table->decimal('cantidad_pendiente', 12, 4)->nullable();  // aún por entregar
            $table->string('estado', 20)->default('activo'); // activo | aplicado | devuelto | anulado
            $table->text('observacion')->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'estado']);
        });

        Schema::create('cliente_anticipo_aplicaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_anticipo_id')->constrained('cliente_anticipos')->cascadeOnDelete();
            $table->foreignId('venta_id')->nullable()->constrained('ventas')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->date('fecha');
            $table->decimal('monto', 12, 2);
            $table->decimal('cantidad', 12, 4)->nullable(); // material entregado (modalidad material)
            $table->text('observacion')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cliente_anticipo_aplicaciones');
        Schema::dropIfExists('cliente_anticipos');
    }
};
