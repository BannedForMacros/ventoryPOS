<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * F7 — Tesorería: libro de movimientos por cuenta.
 *
 * Reemplaza el "escribir el efectivo a mano" del balance diario. Cada sol
 * que entra o sale queda registrado con su ORIGEN (ref_tipo + ref_id):
 * venta, abono de cliente, gasto, pago a proveedor, anticipo, adelanto,
 * cuota de deuda, devolución o ajuste manual (con motivo, auditado).
 *
 * El saldo de una cuenta a una fecha = Σ ingresos − Σ egresos hasta esa
 * fecha. El balance diario lee ese saldo y ya no pide digitarlo.
 *
 * "Efectivo" pasa a ser una cuenta más (es_efectivo = true), creada aquí
 * para cada empresa: así TODO movimiento referencia una cuenta y no hay
 * dinero flotando sin destino.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('cuentas', function (Blueprint $table) {
            $table->boolean('es_efectivo')->default(false)->after('titular');
        });

        // Cuenta "Efectivo" para cada empresa existente.
        foreach (DB::table('empresas')->pluck('id') as $empresaId) {
            $existe = DB::table('cuentas')
                ->where('empresa_id', $empresaId)
                ->where('es_efectivo', true)
                ->exists();
            if (!$existe) {
                DB::table('cuentas')->insert([
                    'empresa_id'  => $empresaId,
                    'nombre'      => 'Efectivo',
                    'es_efectivo' => true,
                    'activo'      => true,
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ]);
            }
        }

        // Gastos podrán indicar de qué cuenta salió el dinero (default: efectivo).
        Schema::table('gastos', function (Blueprint $table) {
            $table->foreignId('cuenta_id')->nullable()->after('monto')->constrained('cuentas')->nullOnDelete();
        });

        Schema::create('cuenta_movimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('cuenta_id')->constrained('cuentas')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->date('fecha');
            $table->string('tipo', 10); // ingreso | egreso
            $table->decimal('monto', 12, 2);
            $table->string('descripcion', 250);
            // Origen del movimiento (trazabilidad): 'venta', 'venta_abono',
            // 'gasto', 'entrada_pago', 'entrada', 'cliente_anticipo',
            // 'proveedor_adelanto', 'deuda_pago', 'devolucion', 'ajuste'.
            $table->string('ref_tipo', 40)->nullable();
            $table->unsignedBigInteger('ref_id')->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'cuenta_id', 'fecha']);
            $table->index(['ref_tipo', 'ref_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cuenta_movimientos');
        Schema::table('gastos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cuenta_id');
        });
        Schema::table('cuentas', function (Blueprint $table) {
            $table->dropColumn('es_efectivo');
        });
    }
};
