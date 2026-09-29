<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Entregas (función opcional por empresa): recojo en tienda o envío, con ruta
 * y fecha y hora programadas.
 *
 * - empresas.usa_entregas: activa la función. Apagada, la venta queda igual.
 * - empresas.entrega_config (JSON): monto del aviso, qué es obligatorio en un
 *   envío y si la mercadería de un envío sale del stock recién al entregarse.
 * - rutas_entrega: las rutas o zonas de reparto de la empresa.
 * - ventas.tipo_entrega ('recojo' | 'envio'), ruta_entrega_id, entrega_programada.
 *
 * La mercadería pendiente y sus entregas parciales siguen viviendo donde ya
 * estaban (cliente_anticipos de tipo material + sus aplicaciones).
 *
 * En producción esto va por produccion/sql/2026_10_01_entregas.sql.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            if (!Schema::hasColumn('empresas', 'usa_entregas')) {
                $table->boolean('usa_entregas')->default(false);
            }
            if (!Schema::hasColumn('empresas', 'entrega_config')) {
                $table->json('entrega_config')->nullable();
            }
        });

        if (!Schema::hasTable('rutas_entrega')) {
            Schema::create('rutas_entrega', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->string('nombre', 60);
                $table->string('zona', 120)->nullable();
                $table->unsignedSmallInteger('orden')->default(0);
                $table->boolean('activo')->default(true);
                $table->timestamps();
                $table->index(['empresa_id', 'orden']);
            });
        }

        Schema::table('ventas', function (Blueprint $table) {
            if (!Schema::hasColumn('ventas', 'tipo_entrega')) {
                $table->string('tipo_entrega', 10)->nullable();
            }
            if (!Schema::hasColumn('ventas', 'ruta_entrega_id')) {
                $table->foreignId('ruta_entrega_id')->nullable()->constrained('rutas_entrega')->nullOnDelete();
            }
            if (!Schema::hasColumn('ventas', 'entrega_programada')) {
                $table->timestamp('entrega_programada')->nullable();
                $table->index(['empresa_id', 'entrega_programada']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ruta_entrega_id');
            $table->dropColumn(['tipo_entrega', 'entrega_programada']);
        });
        Schema::dropIfExists('rutas_entrega');
        Schema::table('empresas', fn (Blueprint $t) => $t->dropColumn(['usa_entregas', 'entrega_config']));
    }
};
