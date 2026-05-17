<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cataloga los tipos de metodo de pago. Reemplaza el enum nativo
 * `metodo_pago_tipo_enum` por una tabla editable.
 *
 * Beneficios:
 *   - El admin puede agregar tipos nuevos (BIM, Tunki, Sodexo, etc.) sin migrar.
 *   - El default de `admite_vuelto` se hereda del tipo, pero sigue siendo
 *     configurable por método.
 *   - El icono del POS se elige al crear el tipo.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('tipos_metodo_pago', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 50)->unique();   // identificador estable usado para queries (efectivo, yape, etc.)
            $table->string('nombre', 80);           // etiqueta visible
            $table->string('icono', 50)->nullable();// nombre de icono lucide-react
            $table->boolean('admite_vuelto_default')->default(false);
            $table->boolean('requiere_referencia')->default(false); // yape/plin/transferencia piden numero de operacion
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // Seed inicial: los valores que estaban en el enum.
        $now = now();
        DB::table('tipos_metodo_pago')->insert([
            ['slug' => 'efectivo',        'nombre' => 'Efectivo',        'icono' => 'Banknote',      'admite_vuelto_default' => true,  'requiere_referencia' => false, 'orden' => 10, 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'tarjeta_debito',  'nombre' => 'Tarjeta débito',  'icono' => 'CreditCard',    'admite_vuelto_default' => false, 'requiere_referencia' => false, 'orden' => 20, 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'tarjeta_credito', 'nombre' => 'Tarjeta crédito', 'icono' => 'CreditCard',    'admite_vuelto_default' => false, 'requiere_referencia' => false, 'orden' => 30, 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'transferencia',   'nombre' => 'Transferencia',   'icono' => 'ArrowLeftRight','admite_vuelto_default' => false, 'requiere_referencia' => true,  'orden' => 40, 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'yape',            'nombre' => 'Yape',            'icono' => 'Smartphone',    'admite_vuelto_default' => false, 'requiere_referencia' => true,  'orden' => 50, 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'plin',            'nombre' => 'Plin',            'icono' => 'Smartphone',    'admite_vuelto_default' => false, 'requiere_referencia' => true,  'orden' => 60, 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
            ['slug' => 'otro',            'nombre' => 'Otro',            'icono' => 'Wallet',        'admite_vuelto_default' => false, 'requiere_referencia' => false, 'orden' => 99, 'activo' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('tipos_metodo_pago');
    }
};
