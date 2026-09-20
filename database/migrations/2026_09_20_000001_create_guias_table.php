<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Guías de remisión: el espejo local de lo que vive en FacturaMac.
 *
 * ─── POR QUÉ SE GUARDA COPIA ───────────────────────────────────────────────────
 *
 * Mismo motivo que `venta_comprobantes`: la guía se imprime EN EL ALMACÉN, con el
 * camión esperando, y no puede depender de que FacturaMac responda en ese momento.
 * Aquí se guarda lo suficiente para listarla, buscarla y saber si el camión puede
 * salir. El documento fiscal sigue viviendo allí.
 *
 * ─── EL CAMPO QUE DECIDE ───────────────────────────────────────────────────────
 *
 * `puede_trasladar` NO se deduce del estado con una lista propia: lo manda FacturaMac
 * ya resuelto y aquí solo se guarda. Es exactamente el fallo que costó dos bugs
 * fiscales con los comprobantes —dos sistemas con listas de estados que dejaron de
 * coincidir— y no se repite.
 *
 * ─── LA GUÍA NO MUEVE STOCK ────────────────────────────────────────────────────
 *
 * Ni una línea de esta tabla toca inventario. El stock lo mueven la venta, el
 * despacho y la transferencia, como hasta ahora. Una guía es el documento del
 * movimiento, no el movimiento.
 *
 * Equivalente en producción: produccion/sql/2026_09_20_guias.sql
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();

            // De dónde salió. Las tres son opcionales porque una guía puede nacer
            // sola: un traslado entre almacenes no tiene venta, y a veces se despacha
            // algo que no se facturó.
            $table->foreignId('venta_id')->nullable()->constrained('ventas')->nullOnDelete();
            $table->foreignId('transferencia_id')->nullable()->constrained('transferencias')->nullOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();

            // Lo que devolvió FacturaMac.
            $table->integer('facturamac_id')->nullable();
            $table->string('serie', 4)->nullable();
            $table->integer('correlativo')->nullable();
            $table->string('numero', 20)->nullable();
            $table->string('estado', 30)->default('pendiente');

            // Lo manda el emisor ya resuelto. No se calcula aquí.
            $table->boolean('puede_trasladar')->default(false);
            $table->string('aviso')->nullable();

            $table->string('sunat_codigo')->nullable();
            $table->text('sunat_descripcion')->nullable();
            $table->text('error')->nullable();
            $table->integer('intentos')->default(0);
            $table->timestamp('enviado_at')->nullable();

            // Copia de lo que se mandó, para poder reintentar y para que la pantalla
            // pueda enseñar la guía sin preguntarle a FacturaMac.
            $table->json('payload')->nullable();
            $table->string('motivo', 40)->nullable();
            $table->string('modalidad', 10)->nullable();
            $table->date('fecha_traslado')->nullable();
            $table->string('destinatario')->nullable();
            $table->string('llegada_direccion')->nullable();

            $table->string('idempotency_key', 100)->nullable();
            $table->string('referencia_externa', 100)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Sin esto, un doble clic en «Emitir guía» manda dos y consume dos
            // números para el mismo despacho. La clave es la misma que viaja a
            // FacturaMac, así que las dos puntas se protegen igual.
            $table->unique(['empresa_id', 'idempotency_key']);
            $table->index(['empresa_id', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guias');
    }
};
