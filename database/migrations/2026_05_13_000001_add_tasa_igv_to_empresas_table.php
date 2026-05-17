<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tasa de IGV configurable por empresa.
 *
 * Hasta ahora el calculo de IGV usaba 18% hardcoded, lo que generaba dos
 * problemas reales en clientes con casuistica mixta:
 *   - Productos exonerados (medicamentos veterinarios, ciertos alimentos)
 *     terminaban pagando IGV porque el total se multiplicaba por 0.18 entero.
 *   - Si SUNAT cambia la tasa o se atiende otro pais, habia que recompilar.
 *
 * tasa_igv guarda el porcentaje (ej. 18.00 = 18%). La logica de calculo en
 * Venta::calcularTotales lo aplica unicamente a la base gravada del documento.
 */
return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('empresas', 'tasa_igv')) {
            Schema::table('empresas', function (Blueprint $table) {
                $table->decimal('tasa_igv', 5, 2)->default(18.00)->after('activo');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('empresas', 'tasa_igv')) {
            Schema::table('empresas', function (Blueprint $table) {
                $table->dropColumn('tasa_igv');
            });
        }
    }
};
