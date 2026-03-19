<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venta_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->constrained('ventas')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos');
            $table->foreignId('producto_unidad_id')->constrained('producto_unidades');
            $table->string('producto_nombre', 150);
            $table->string('unidad_nombre', 50);
            $table->decimal('cantidad', 12, 4);
            $table->decimal('factor_conversion', 12, 4);
            $table->decimal('cantidad_base', 12, 4);
            $table->decimal('precio_unitario', 12, 2);
            $table->decimal('precio_original', 12, 2);
            $table->decimal('descuento_item', 12, 2)->default(0);
            $table->foreignId('descuento_concepto_id')->nullable()->constrained('descuento_conceptos')->nullOnDelete();
            $table->decimal('subtotal', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venta_items');
    }
};
