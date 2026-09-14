<?php

namespace App\Services;

use App\Models\Producto;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * UNA sola regla para el COSTO de lo vendido.
 *
 * Antes cada lugar decidía por su cuenta: al vender se tomaba primero el costo
 * de catálogo (a veces de meses atrás) y luego el costo promedio VIVO de la
 * tabla stock; si no había, se guardaba 0. Los reportes leían ese 0 como "no
 * sé" y lo reemplazaban en silencio por el costo vivo de HOY — así el margen de
 * una venta vieja cambiaba con el tiempo, y el 10/09 un costo promedio
 * explotado convirtió 1,000 ladrillos en S/ 2.26 millones de costo.
 *
 * Regla única:
 *  - Mercadería: costo promedio del KARDEX al momento de la venta.
 *  - Nunca comprada (sin costo en el kardex): costo de catálogo si lo tiene.
 *  - Servicio sin costo de catálogo: 0 (un cero real, no un dato faltante).
 *  - Nada de lo anterior: NULL = DESCONOCIDO, visible como tal.
 *
 * Al leer (sql()), un costo faltante se completa con el costo del kardex EN EL
 * MOMENTO DE ESA VENTA, nunca con el costo vivo de hoy.
 */
class CostoVentaService
{
    private static ?bool $hayKardex = null;

    /** Costo por unidad base a congelar en una línea de venta. */
    public function paraVender(Producto $producto, ?int $almacenId, $fechaVenta = null): ?float
    {
        $catalogo = (float) ($producto->precio_costo ?? 0);

        if ($producto->tipo === 'servicio') {
            return $catalogo > 0 ? round($catalogo, 4) : 0.0;
        }

        if ($almacenId) {
            $cpp = $this->cppAlMomento($almacenId, $producto->id, $fechaVenta);
            if ($cpp !== null) {
                return round($cpp, 4);
            }
        }

        return $catalogo > 0 ? round($catalogo, 4) : null;
    }

    /**
     * Costo promedio de un producto en un almacén al momento indicado: la última
     * fila del kardex hasta ese instante; si el kardex no tiene nada, el de la
     * tabla stock (que el motor único mantiene igual a la última fila).
     */
    public function cppAlMomento(int $almacenId, int $productoId, $momento = null): ?float
    {
        $corte = $momento ? Carbon::parse($momento) : now();

        if (self::$hayKardex ??= Schema::hasTable('movimientos_inventario')) {
            $k = DB::table('movimientos_inventario')
                ->where('almacen_id', $almacenId)->where('producto_id', $productoId)
                ->where('fecha', '<=', $corte->format('Y-m-d H:i:s'))
                ->orderByDesc('fecha')->orderByDesc('id')
                ->value('costo_promedio');
            if ($k !== null && (float) $k > 0) {
                return (float) $k;
            }
        }

        $vivo = DB::table('stock')->where('almacen_id', $almacenId)->where('producto_id', $productoId)->value('costo_promedio');

        return $vivo !== null && (float) $vivo > 0 ? (float) $vivo : null;
    }

    /**
     * Costo ponderado al juntar dos cantidades (sumar a una línea existente).
     * Si uno de los dos es desconocido, manda el conocido.
     */
    public static function ponderado(?float $costoA, float $cantidadA, ?float $costoB, float $cantidadB): ?float
    {
        if ($costoA === null || $costoA <= 0) return $costoB;
        if ($costoB === null || $costoB <= 0) return $costoA;
        $total = $cantidadA + $cantidadB;

        return $total > 0 ? round(($costoA * $cantidadA + $costoB * $cantidadB) / $total, 4) : $costoA;
    }

    /**
     * Expresión SQL del costo por unidad base de una línea de venta, para los
     * reportes. $vi, $p y $v son los alias (o nombres) de venta_items, productos
     * y ventas.
     *
     * Cadena, siempre con costos que NO cambian con compras futuras:
     *  1. el costo congelado en la línea;
     *  2. el costo del kardex en el movimiento de ESA venta;
     *  3. el primer costo conocido del producto desde la fecha de la venta (p. ej.
     *     el inventario inicial, para ventas anteriores a la apertura);
     *  4. el último costo conocido antes de la venta;
     *  5. el costo de catálogo (servicios);
     *  6. recién ahí 0. Antes el respaldo era el costo vivo de HOY.
     */
    public static function sql(string $vi = 'vi', string $p = 'p', string $v = 'v'): string
    {
        return "CASE WHEN {$vi}.costo_unitario_base > 0 THEN {$vi}.costo_unitario_base ELSE COALESCE(
            (SELECT NULLIF(mi.costo_promedio, 0) FROM movimientos_inventario mi
              WHERE mi.referencia_tipo = 'venta' AND mi.referencia_id = {$vi}.venta_id
                AND mi.producto_id = {$vi}.producto_id AND mi.tipo IN ('venta', 'entrega_pendiente')
              ORDER BY mi.id LIMIT 1),
            (SELECT mi.costo_promedio FROM movimientos_inventario mi
              WHERE mi.producto_id = {$vi}.producto_id AND mi.costo_promedio > 0 AND mi.fecha >= {$v}.fecha_venta
              ORDER BY mi.fecha, mi.id LIMIT 1),
            (SELECT mi.costo_promedio FROM movimientos_inventario mi
              WHERE mi.producto_id = {$vi}.producto_id AND mi.costo_promedio > 0 AND mi.fecha < {$v}.fecha_venta
              ORDER BY mi.fecha DESC, mi.id DESC LIMIT 1),
            NULLIF({$p}.precio_costo, 0), 0) END";
    }
}
