<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega `admite_vuelto` a metodos_pago.
 *
 * Antes: el sistema asumia que solo `tipo='efectivo'` admitia sobrepago/vuelto,
 * lo cual es una inferencia fragil:
 *   - El admin no podia configurar metodos nuevos (SIP, Sodexo, etc.) y mi
 *     codigo los trataba como no-efectivo automaticamente.
 *   - Cualquier nuevo tipo requeria migrar el enum + parchar la regla.
 *
 * Ahora: el admin decide explicitamente por cada metodo de pago si admite
 * sobrepago (vuelto fisico). El enum `tipo` queda para clasificacion/reportes,
 * pero la regla de sobrepago en POS usa este flag.
 *
 * Backfill: metodos con tipo='efectivo' arrancan en true; el resto en false.
 * Es lo mas conservador y consistente con el comportamiento anterior. El admin
 * puede modificarlo desde Configuracion → Metodos de Pago.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('metodos_pago', 'admite_vuelto')) {
            Schema::table('metodos_pago', function (Blueprint $table) {
                $table->boolean('admite_vuelto')->default(false)->after('tipo');
            });
        }

        // Backfill: efectivo → true, resto → false (default ya en columna).
        DB::table('metodos_pago')
            ->where('tipo', 'efectivo')
            ->update(['admite_vuelto' => true]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('metodos_pago', 'admite_vuelto')) {
            Schema::table('metodos_pago', function (Blueprint $table) {
                $table->dropColumn('admite_vuelto');
            });
        }
    }
};
