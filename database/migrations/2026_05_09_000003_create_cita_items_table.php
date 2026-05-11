<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Detalle de cita: 1 fila por servicio reservado.
 *
 * Patron snapshot:
 *   precio_estimado se guarda al momento de agendar (referencia historica).
 *   El precio REAL cobrado vive en venta_items cuando se concrete la cita.
 *   Esto permite ver "se reservo por X, se cobro Y" en reportes.
 *
 * Producto/presentacion:
 *   producto_id apunta al servicio (puede ser tipo='servicio' o 'producto').
 *   producto_unidad_id apunta a la presentacion exacta ("Talla mediana", etc).
 *   Esto es identico al modelo de venta_items para que el flujo sea simetrico.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('cita_items', function (Blueprint $table) {
            $table->id();

            // Vinculo a la cabecera. Cascade: si se borra la cita, se borran sus items.
            $table->foreignId('cita_id')->constrained('citas')->cascadeOnDelete();

            // Que se reservo. Restrict para no borrar productos con citas pasadas.
            $table->foreignId('producto_id')->constrained('productos')->restrictOnDelete();
            $table->foreignId('producto_unidad_id')->constrained('producto_unidades')->restrictOnDelete();

            // Cantidad reservada (tipicamente 1, pero permite "3 vacunas" en una visita)
            $table->decimal('cantidad', 12, 4)->default(1);

            // Duracion estimada de este item especifico (para sumar a la cita)
            $table->unsignedSmallInteger('duracion_min')->default(30);

            // Snapshot del precio al momento de reservar (S/.)
            $table->decimal('precio_estimado', 12, 2)->default(0);

            // Notas especificas de este item (ej. "Tipo de baño con shampoo medicado")
            $table->text('observaciones')->nullable();

            // Para mostrar items en orden personalizado (drag&drop futuro)
            $table->unsignedSmallInteger('orden')->default(0);

            $table->timestamps();

            $table->index('cita_id');
            $table->index('producto_id');
            $table->index('producto_unidad_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cita_items');
    }
};
