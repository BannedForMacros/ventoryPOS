<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * F8.1 — La consolidación verifica TODO, no solo efectivo (pedido del
 * cliente): una línea por efectivo y por cada método de pago declarado
 * (Yape, tarjeta, transferencia...). Cada línea guarda declarado (cajera),
 * esperado (sistema) y contado (consolidador), y su diferencia asienta en
 * la cuenta de tesorería correspondiente al método.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('turno_consolidacion_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('turno_consolidacion_id')->constrained('turno_consolidaciones')->cascadeOnDelete();
            // null = EFECTIVO (billetes/monedas); si no, el método verificado.
            $table->foreignId('metodo_pago_id')->nullable()->constrained('metodos_pago')->nullOnDelete();
            $table->foreignId('cuenta_id')->nullable()->constrained('cuentas')->nullOnDelete(); // cuenta donde asienta
            $table->string('etiqueta', 100);                    // "Efectivo", "Yape", ...
            $table->decimal('declarado', 12, 2)->nullable();    // lo que dijo la cajera
            $table->decimal('esperado', 12, 2)->nullable();     // lo que el sistema esperaba
            $table->decimal('contado', 12, 2);                  // lo que verificó el consolidador (manda)
            $table->decimal('diferencia', 12, 2)->nullable();   // contado − esperado
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('turno_consolidacion_items');
    }
};
