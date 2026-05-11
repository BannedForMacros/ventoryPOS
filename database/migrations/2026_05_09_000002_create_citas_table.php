<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cabecera de cita / reserva. Cada cita representa UNA visita del cliente al
 * local, que puede involucrar multiples servicios (ver tabla cita_items).
 *
 * Vinculo con venta:
 *   - Mientras la cita no se cobra, venta_id es null.
 *   - Al "Completar y cobrar", el flujo crea una Venta nueva con sus items
 *     (precios actualizados, posibles ajustes en el momento) y guarda venta_id.
 *   - Los cita_items quedan intactos como historico de lo reservado originalmente.
 *
 * Multidisciplina:
 *   - sujeto_nombre / sujeto_descripcion son campos libres que el frontend
 *     etiqueta segun empresas.agenda_sujeto_label (Mascota, Vehiculo, etc).
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('citas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('local_id')->constrained('locales')->restrictOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes')->restrictOnDelete();

            // Profesional asignado (opcional). Si el user se elimina, la cita queda
            // sin profesional pero se preserva.
            $table->foreignId('profesional_id')->nullable()
                  ->constrained('users')->nullOnDelete();

            // Quien creo la cita (recepcion/admin). No se elimina con el user.
            $table->foreignId('created_by')->nullable()
                  ->constrained('users')->nullOnDelete();

            // Numero amigable opcional (ej. "C-0042"). Lo genera el modelo al crear.
            $table->string('numero', 30)->nullable();

            // Programacion temporal
            $table->timestamp('fecha_hora');             // cuando empieza
            $table->unsignedSmallInteger('duracion_min') // suma de duraciones de items
                  ->default(30);

            // Estado del flujo. Check constraint a nivel BD para integridad.
            $table->string('estado', 20)->default('programada');

            // Texto libre del recepcionista
            $table->text('observaciones')->nullable();

            // Sujeto multidisciplina (mascota / vehiculo / paciente / vacio)
            $table->string('sujeto_nombre', 150)->nullable();
            $table->text('sujeto_descripcion')->nullable();

            // Vinculo con la venta cuando se cobra. La venta es independiente:
            // restrict para que no se borre una venta si tiene cita asociada.
            $table->foreignId('venta_id')->nullable()
                  ->constrained('ventas')->restrictOnDelete();

            // Marcas temporales del flujo (auditoria rapida ademas de la tabla auditoria)
            $table->timestamp('confirmada_at')->nullable();
            $table->timestamp('iniciada_at')->nullable();
            $table->timestamp('completada_at')->nullable();
            $table->timestamp('cancelada_at')->nullable();
            $table->text('motivo_cancelacion')->nullable();

            // Notificaciones (recordatorios). v1: solo timestamp. v2: tabla aparte si hace falta.
            $table->timestamp('recordatorio_enviado_at')->nullable();

            $table->timestamps();

            // Indices para consultas tipicas
            $table->index(['empresa_id', 'fecha_hora']);  // agenda diaria/semanal
            $table->index(['local_id', 'fecha_hora']);    // agenda por local
            $table->index(['cliente_id', 'fecha_hora']);  // historial del cliente
            $table->index(['profesional_id', 'fecha_hora']); // agenda del profesional
            $table->index(['empresa_id', 'estado']);      // filtro por estado
            $table->index('venta_id');                    // join inverso desde venta
        });

        // Check constraint del estado: Postgres-friendly, declarado fuera del
        // Blueprint para mayor control. Si en el futuro se agrega un estado,
        // se hace en una migracion nueva (ALTER ... DROP / ADD CONSTRAINT).
        DB::statement("
            ALTER TABLE citas
            ADD CONSTRAINT citas_estado_check
            CHECK (estado IN ('programada', 'confirmada', 'en_atencion',
                              'completada', 'no_asistio', 'cancelada'))
        ");

        // Numero de cita unico por empresa (cuando exista)
        DB::statement("
            CREATE UNIQUE INDEX citas_numero_empresa_unique
            ON citas (empresa_id, numero)
            WHERE numero IS NOT NULL
        ");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS citas_numero_empresa_unique');
        Schema::dropIfExists('citas');
    }
};
