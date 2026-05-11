<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Activa el modulo Agenda por empresa y configura el "sujeto" que se atiende.
 * Esto permite que el mismo modulo sirva multidisciplina sin tablas extras:
 *
 *   Veterinaria → label="Mascota",  requerido=true
 *   Taller      → label="Vehiculo", requerido=true
 *   Peluqueria  → label=null,       requerido=false  (cliente es suficiente)
 *   Bodega      → usa_agenda=false  (sidebar oculto)
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            // Switch maestro: si false, el sidebar no muestra "Agenda" para esta empresa.
            $table->boolean('usa_agenda')->default(false)->after('restock_default');

            // Etiqueta del campo libre que se atiende (ej. "Mascota", "Vehiculo").
            // Si es null, el formulario de cita NO muestra los campos sujeto_*.
            $table->string('agenda_sujeto_label', 50)->nullable()->after('usa_agenda');

            // Si el sujeto es obligatorio al crear una cita.
            // Solo aplica si agenda_sujeto_label tiene valor.
            $table->boolean('agenda_sujeto_requerido')->default(false)->after('agenda_sujeto_label');
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn(['usa_agenda', 'agenda_sujeto_label', 'agenda_sujeto_requerido']);
        });
    }
};
