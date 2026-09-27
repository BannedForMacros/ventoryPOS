<?php

/**
 * Migración del catálogo de BOTICA FARMACIX (RUC 10771242885) desde el Excel
 * "EXEL DE PRODUCTOS FARMACIX.xlsx" de su sistema anterior.
 *
 * ─── QUÉ HACE ────────────────────────────────────────────────────────────────
 *
 * Lee produccion/data/farmacix_productos.json (generado y revisado con
 * produccion/tools/farmacix_clasificar.py) y crea en la empresa:
 *
 *   1. Las categorías (41), normalizadas: el Excel traía 58 variantes sucias
 *      (TABLETA / TABLETAS / PASTILLAS / PASTILLA0, "A MPOLLA"...) y 636
 *      productos sin categoría, que se clasificaron por el nombre.
 *   2. Los 996 productos con su precio de venta, igual que los crearía el
 *      formulario del catálogo: precio en la presentación base (UND) y el del
 *      producto a 0. Los 2 servicios (INYECCION, ENDOVENOSO) llevan el precio
 *      en ambos y la unidad "Servicio".
 *
 * ─── DECISIONES ──────────────────────────────────────────────────────────────
 *
 * · Código = C.INTERNO del Excel, para que el personal siga buscando por el
 *   código que ya conoce.
 * · `incluye_igv = true`: precios de mostrador, IGV incluido (gravados).
 * · `controla_stock = null`: hereda la configuración de la empresa.
 * · Costo 0 y SIN stock: el stock del sistema viejo tiene 352 productos en
 *   negativo y el costo es 0 en el 92%. El inventario real se carga después
 *   con una entrada / cierre de inventario, que es lo que fija costo y kardex.
 * · Se omiten 25 filas: 16 comodines ("NADA", "NOSE") y 9 duplicados exactos
 *   (mismo nombre y precio). Mismo nombre con precio distinto → se conservan
 *   ambos con el código entre paréntesis.
 *
 * ─── USO ─────────────────────────────────────────────────────────────────────
 *
 *   php produccion/migrar_productos_farmacix.php            (simulación, no escribe)
 *   php produccion/migrar_productos_farmacix.php --aplicar  (escribe)
 *   … --empresa=ID  para forzar la empresa si no se encuentra por RUC.
 *
 * Es idempotente: productos por (empresa, código) y categorías por nombre; si
 * se vuelve a correr, actualiza en vez de duplicar.
 */

use App\Models\Categoria;
use App\Models\Empresa;
use App\Models\Producto;
use App\Models\UnidadMedida;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

const RUC_FARMACIX = '10771242885';

$aplicar = in_array('--aplicar', $argv, true);
$empresaForzada = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--empresa=')) {
        $empresaForzada = (int) substr($arg, 10);
    }
}

$datos = json_decode(file_get_contents(__DIR__ . '/data/farmacix_productos.json'), true, flags: JSON_THROW_ON_ERROR);

echo $aplicar ? "MODO APLICAR — se van a escribir cambios\n" : "MODO SIMULACIÓN — no se escribe nada (usa --aplicar)\n";
echo str_repeat('─', 78) . "\n";

$empresa = $empresaForzada
    ? Empresa::find($empresaForzada)
    : Empresa::where('ruc', RUC_FARMACIX)->first();

if (!$empresa) {
    echo "ERROR: no existe la empresa con RUC " . RUC_FARMACIX . ". Créala desde /admin o usa --empresa=ID.\n";
    exit(1);
}
echo "Empresa: #{$empresa->id} {$empresa->razon_social} (RUC {$empresa->ruc})\n";

DB::beginTransaction();

try {
    // ── 1) Unidades ──────────────────────────────────────────────────────────
    $und = UnidadMedida::where('empresa_id', $empresa->id)
        ->whereRaw("LOWER(abreviatura) = 'und'")->first();
    if (!$und) {
        throw new RuntimeException('La empresa no tiene la unidad UND (la crea el alta de empresa).');
    }
    // Misma convención que ProductoController::crearPresentacionDefaultServicio().
    $srv = UnidadMedida::firstOrCreate(
        ['empresa_id' => $empresa->id, 'nombre' => 'Servicio'],
        ['abreviatura' => 'srv', 'activo' => true],
    );

    // ── 2) Categorías ────────────────────────────────────────────────────────
    $nombresCategoria = collect($datos['productos'])->pluck('categoria')->unique()->sort()->values();
    $categorias = [];
    $creadas = 0;
    foreach ($nombresCategoria as $nombre) {
        $cat = Categoria::where('empresa_id', $empresa->id)
            ->whereRaw('LOWER(nombre) = ?', [mb_strtolower($nombre)])
            ->first();
        if (!$cat) {
            $cat = Categoria::create(['empresa_id' => $empresa->id, 'nombre' => $nombre, 'activo' => true]);
            $creadas++;
        }
        $categorias[$nombre] = $cat->id;
    }
    echo "\n1) CATEGORÍAS: {$nombresCategoria->count()} ({$creadas} nuevas)\n";

    // ── 3) Productos ─────────────────────────────────────────────────────────
    $nuevos = $actualizados = 0;
    $porCategoria = [];
    foreach ($datos['productos'] as $item) {
        $esProducto = $item['tipo'] === 'producto';

        $p = Producto::where('empresa_id', $empresa->id)->where('codigo', $item['codigo'])->first();
        $p ? $actualizados++ : $nuevos++;

        $atributos = [
            'empresa_id'     => $empresa->id,
            'categoria_id'   => $categorias[$item['categoria']],
            'codigo'         => $item['codigo'],
            'nombre'         => $item['nombre'],
            'tipo'           => $item['tipo'],
            'tipo_precio'    => 'fijo',
            'precio_venta'   => $esProducto ? 0 : $item['precio'],
            'precio_costo'   => 0,
            'incluye_igv'    => true,
            'controla_stock' => $esProducto ? null : false,
            'es_retornable'  => $esProducto ? null : false,
            'activo'         => true,
        ];

        $p = $p ? tap($p)->update($atributos) : Producto::create($atributos);

        $p->unidades()->updateOrCreate(
            ['unidad_medida_id' => $esProducto ? $und->id : $srv->id],
            [
                'es_base'           => true,
                'factor_conversion' => 1,
                'tipo_precio'       => 'fijo',
                'precio_venta'      => $item['precio'],
                'precio_costo'      => 0,
                'activo'            => true,
            ],
        );

        $porCategoria[$item['categoria']] = ($porCategoria[$item['categoria']] ?? 0) + 1;
    }

    echo "\n2) PRODUCTOS: " . count($datos['productos']) . " ({$nuevos} nuevos, {$actualizados} actualizados)\n";
    arsort($porCategoria);
    foreach ($porCategoria as $cat => $n) {
        echo sprintf("   %4d  %s\n", $n, $cat);
    }

    echo "\n3) OMITIDOS del Excel: " . count($datos['omitidos']) . "\n";
    foreach ($datos['omitidos'] as $o) {
        echo sprintf("   %-7s %-32s %s\n", $o['codigo'], mb_substr($o['nombre'], 0, 30), $o['motivo']);
    }

    $otros = Producto::where('empresa_id', $empresa->id)
        ->whereNotIn('codigo', collect($datos['productos'])->pluck('codigo'))
        ->count();
    if ($otros > 0) {
        echo "\nAVISO: la empresa tiene {$otros} producto(s) que no vienen en el Excel; no se tocan.\n";
    }

    echo "\n" . str_repeat('─', 78) . "\n";

    if ($aplicar) {
        DB::commit();
        echo "APLICADO.\n";
    } else {
        DB::rollBack();
        echo "SIMULACIÓN: nada se ha escrito. Repite con --aplicar para hacerlo efectivo.\n";
    }
} catch (\Throwable $e) {
    DB::rollBack();
    echo "\nERROR (nada se ha escrito): " . $e->getMessage() . "\n";
    echo $e->getFile() . ':' . $e->getLine() . "\n";
    exit(1);
}
