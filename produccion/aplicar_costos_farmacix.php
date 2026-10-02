<?php

/**
 * BOTICA FARMACIX (empresa 1104): costo de catálogo de 304 productos.
 *
 * Origen: "Farmacix_costo_promedio_productos.xlsx" — costo promedio ponderado
 * de sus compras del 21/08 al 28/09/2026 (importe pagado / cantidad recibida,
 * con IGV; las bonificaciones bajan el promedio).
 *
 * Emparejamiento revisado a mano (produccion/data/farmacix_costos.json):
 *  · Nombre de la factura ↔ producto del catálogo, rechazando si no coincide
 *    la medida (ml, gr, mg, °) o la variante (talla, aroma, marca).
 *  · El catálogo vende por UNIDAD (tableta, sobre, pañal…): si la factura es
 *    por caja, el costo se divide entre lo que trae la caja.
 *  · Varias filas del Excel al mismo producto → promedio ponderado por cantidad.
 *  · Fuera: costo 0 (solo bonificación), promociones de S/ 0.01 y lo dudoso.
 *
 * Escribe productos.precio_costo y el de su presentación base. NO toca stock,
 * kardex ni ventas: el costo de catálogo es el que usa el sistema mientras el
 * producto no tenga costo propio en el kardex (entradas).
 * Solo escribe donde el costo sigue en 0: no pisa lo que alguien ya cargó.
 *
 *   php produccion/aplicar_costos_farmacix.php            (simulación)
 *   php produccion/aplicar_costos_farmacix.php --aplicar  (escribe)
 */

use App\Models\User;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

const EMPRESA = 1104;
$aplicar = in_array('--aplicar', $argv, true);
echo $aplicar ? "MODO APLICAR\n" : "MODO SIMULACIÓN (usa --aplicar para escribir)\n";

$empresa = DB::table('empresas')->where('id', EMPRESA)->first(['id', 'razon_social', 'ruc']);
if (!$empresa || $empresa->ruc !== '10771242885') throw new RuntimeException('La empresa 1104 no es Farmacix.');
echo "Empresa: {$empresa->razon_social}\n";

$filas = json_decode(file_get_contents(__DIR__ . '/data/farmacix_costos.json'), true);
$cambia = $yaTenia = $noEsta = 0;
$detalle = [];

DB::beginTransaction();
foreach ($filas as $f) {
    $p = DB::table('productos')->where('id', $f['producto_id'])->where('empresa_id', EMPRESA)->where('codigo', $f['codigo'])->first(['id', 'nombre', 'precio_costo']);
    if (!$p) { $noEsta++; echo "  NO ENCONTRADO: {$f['codigo']} {$f['nombre']}\n"; continue; }
    $u = DB::table('producto_unidades')->where('id', $f['unidad_id'])->where('producto_id', $p->id)->first(['id', 'precio_costo']);
    if ((float) $p->precio_costo > 0 || ($u && (float) $u->precio_costo > 0)) { $yaTenia++; continue; }

    DB::table('productos')->where('id', $p->id)->update(['precio_costo' => $f['costo'], 'updated_at' => now()]);
    if ($u) DB::table('producto_unidades')->where('id', $u->id)->update(['precio_costo' => $f['costo'], 'updated_at' => now()]);
    $cambia++;
    $detalle[] = ['id' => $p->id, 'codigo' => $f['codigo'], 'costo' => $f['costo']];
}

$admin = User::where('empresa_id', EMPRESA)->whereHas('rol', fn ($q) => $q->where('es_admin', true))->orderBy('id')->first();
if ($cambia) {
    DB::table('auditoria')->insert([
        'empresa_id' => EMPRESA, 'user_id' => $admin?->id, 'user_name' => 'Carga de costos (soporte)',
        'accion' => 'productos.costos_cargados', 'modelo_tipo' => 'App\\Models\\Producto', 'modelo_id' => null,
        'contexto' => json_encode(['origen' => 'Farmacix_costo_promedio_productos.xlsx (compras 21/08–28/09/2026)', 'productos' => $detalle], JSON_UNESCAPED_UNICODE),
        'created_at' => now(),
    ]);
}

echo "Con costo nuevo: {$cambia} · ya tenían costo (no se tocan): {$yaTenia} · no encontrados: {$noEsta}\n";
if ($aplicar) { DB::commit(); echo "APLICADO.\n"; }
else          { DB::rollBack(); echo "SIMULACIÓN: nada se escribió.\n"; }
