<?php

namespace App\Services;

use App\Models\Gasto;
use App\Models\Venta;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * UNA sola regla para la utilidad de un período. La usan el reporte de
 * utilidad, el dashboard y el cierre de mes, para que los tres digan lo mismo.
 *
 *   VENTAS NETAS   = ventas completadas (ya sin descuentos) − dinero devuelto a clientes
 *   COSTO NETO     = costo de lo vendido − costo de lo que volvió al stock
 *   UTILIDAD BRUTA = ventas netas − costo neto
 *   UTILIDAD NETA  = bruta − gastos del período
 *
 * Una devolución NO es un gasto: deshace (parte de) una venta. Si el producto
 * vuelve al stock y se devuelve el dinero, venta y costo se anulan y el
 * patrimonio queda igual. Solo hay pérdida real cuando la mercadería vuelve
 * dañada (sin restock): se devuelve el dinero pero su costo no se recupera.
 * Solo cuentan devoluciones COMPLETADAS (ya movieron dinero y mercadería);
 * "sin reembolso" no resta dinero, salvo la deuda (CxC) que canceló.
 *
 * El costo es el CONGELADO al vender (CostoVentaService::sql). Todo con IGV,
 * igual que el balance diario.
 */
class UtilidadService
{
    /**
     * Resumen del período.
     *
     * @return array{ventas_brutas: float, ventas: float, costo: float, utilidad_bruta: float, margen_bruto: ?float,
     *               descuentos: float, gastos: float, devuelto: float, recuperado: float, costo_danado: float,
     *               devoluciones_count: int, utilidad_neta: float, margen_neto: ?float}
     */
    public function resumen(int $empresa, string $desde, string $hasta, ?int $localId = null): array
    {
        $ventas = Venta::deEmpresa($empresa)
            ->where('estado', 'completada')
            ->whereBetween('fecha_venta', [$desde . ' 00:00:00', $hasta . ' 23:59:59'])
            ->when($localId, fn ($q, $v) => $q->where('local_id', $v));
        $items = $this->items($empresa, $desde, $hasta, $localId);

        $ventasBrutas = (float) (clone $ventas)->sum('total');
        $cogs         = (float) (clone $items)->selectRaw('COALESCE(SUM(vi.cantidad_base * ' . $this->costo() . '), 0) as c')->value('c');
        // Descuentos ya restados en el total: el global de cada venta + el de cada línea (por unidad × cantidad).
        $descuentos   = (float) (clone $ventas)->sum('descuento_total')
            + (float) (clone $items)->selectRaw('COALESCE(SUM(vi.descuento_item * vi.cantidad), 0) as d')->value('d');

        $gastos = (float) Gasto::deEmpresa($empresa)
            ->whereBetween('fecha', [$desde, $hasta])
            ->when($localId, fn ($q, $v) => $q->where('local_id', $v))
            ->sum('monto');

        $dev = $this->devoluciones($empresa, $desde, $hasta, $localId)
            ->selectRaw("COALESCE(SUM({$this->devuelto()}), 0) as devuelto,
                         COALESCE(SUM({$this->recuperado()}), 0) as recuperado,
                         COALESCE(SUM(CASE WHEN NOT dd.restock AND d.forma_reembolso <> 'sin_reembolso'
                                           THEN dd.cantidad_base * ({$this->costo()}) ELSE 0 END), 0) as danado,
                         COUNT(DISTINCT d.id) as devoluciones")
            ->first();

        $ventasNeta = $ventasBrutas - (float) $dev->devuelto;
        $costoNeto  = $cogs - (float) $dev->recuperado;
        $bruta      = round($ventasNeta - $costoNeto, 2);
        $neta       = round($bruta - $gastos, 2);

        return [
            'ventas_brutas'      => round($ventasBrutas, 2),       // lo facturado (sin restar devoluciones)
            'ventas'             => round($ventasNeta, 2),         // neto de devoluciones
            'costo'              => round($costoNeto, 2),          // neto de lo que volvió al stock
            'utilidad_bruta'     => $bruta,
            'margen_bruto'       => $ventasNeta > 0 ? round($bruta / $ventasNeta * 100, 1) : null,
            'descuentos'         => round($descuentos, 2),
            'gastos'             => round($gastos, 2),
            'devuelto'           => round((float) $dev->devuelto, 2),   // dinero devuelto a clientes
            'recuperado'         => round((float) $dev->recuperado, 2), // costo de lo que volvió al stock
            'costo_danado'       => round((float) $dev->danado, 2),     // devuelto dañado: pérdida real
            'devoluciones_count' => (int) $dev->devoluciones,
            'utilidad_neta'      => $neta,
            'margen_neto'        => $ventasNeta > 0 ? round($neta / $ventasNeta * 100, 1) : null,
        ];
    }

    /**
     * Devoluciones por día del período: dinero devuelto y costo recuperado,
     * para restarlos de las ventas y el costo de cada día en las series.
     *
     * @return Collection<string, object{devuelto: float, recuperado: float}>
     */
    public function devolucionesPorDia(int $empresa, string $desde, string $hasta, ?int $localId = null): Collection
    {
        return $this->devoluciones($empresa, $desde, $hasta, $localId)
            ->selectRaw("DATE(d.fecha) as dia,
                         COALESCE(SUM({$this->devuelto()}), 0) as devuelto,
                         COALESCE(SUM({$this->recuperado()}), 0) as recuperado")
            ->groupBy('dia')->get()
            ->mapWithKeys(fn ($r) => [substr((string) $r->dia, 0, 10) => (object) [
                'devuelto'   => (float) $r->devuelto,
                'recuperado' => (float) $r->recuperado,
            ]]);
    }

    /**
     * Utilidad de UNA venta: costo congelado por línea y lo que deshicieron
     * sus devoluciones completadas (misma regla que el período).
     *
     * @return array{items: array<int, array{costo: float, utilidad: float, margen: ?float, sin_costo: bool}>,
     *               total: float, costo: float, utilidad_bruta: float, devuelto: float, recuperado: float,
     *               utilidad: float, margen: ?float, sin_costo: int}
     */
    public function deVenta(Venta $venta): array
    {
        $lineas = DB::table('venta_items as vi')
            ->join('ventas as v', 'v.id', '=', 'vi.venta_id')
            ->join('productos as p', 'p.id', '=', 'vi.producto_id')
            ->where('vi.venta_id', $venta->id)
            ->selectRaw('vi.id, vi.subtotal, vi.cantidad_base * (' . $this->costo() . ') as costo')
            ->get();

        $items = [];
        foreach ($lineas as $l) {
            $sub   = (float) $l->subtotal;
            $costo = round((float) $l->costo, 2);
            $items[$l->id] = [
                'costo'     => $costo,
                'utilidad'  => round($sub - $costo, 2),
                'margen'    => $sub > 0 ? round(($sub - $costo) / $sub * 100, 1) : null,
                'sin_costo' => $sub > 0 && $costo <= 0,
            ];
        }

        $dev = DB::table('devoluciones_detalle as dd')
            ->join('devoluciones as d', 'd.id', '=', 'dd.devolucion_id')
            ->join('venta_items as vi', 'vi.id', '=', 'dd.venta_item_id')
            ->join('ventas as v', 'v.id', '=', 'vi.venta_id')
            ->join('productos as p', 'p.id', '=', 'vi.producto_id')
            ->where('vi.venta_id', $venta->id)
            ->where('d.estado', 'completada')
            ->selectRaw("COALESCE(SUM({$this->devuelto()}), 0) as devuelto, COALESCE(SUM({$this->recuperado()}), 0) as recuperado")
            ->first();

        // El total ya trae el descuento global; el costo, lo que se entregó.
        $total    = (float) $venta->total;
        $costo    = round(array_sum(array_column($items, 'costo')), 2);
        $bruta    = round($total - $costo, 2);
        $devuelto = round((float) $dev->devuelto, 2);
        $recup    = round((float) $dev->recuperado, 2);
        $utilidad = round($bruta - $devuelto + $recup, 2);
        $neto     = $total - $devuelto;

        return [
            'items'          => $items,
            'total'          => round($total, 2),
            'costo'          => $costo,
            'utilidad_bruta' => $bruta,
            'devuelto'       => $devuelto,
            'recuperado'     => $recup,
            'utilidad'       => $utilidad,
            'margen'         => $neto > 0 ? round($utilidad / $neto * 100, 1) : null,
            'sin_costo'      => count(array_filter($items, fn ($i) => $i['sin_costo'])),
        ];
    }

    /* ── Piezas de consulta (también las usa el reporte por producto) ──── */

    /** Ítems vendidos del rango (ventas completadas), alias vi / v / p. */
    public function items(int $empresa, string $desde, string $hasta, ?int $localId = null): Builder
    {
        return DB::table('venta_items as vi')
            ->join('ventas as v', 'v.id', '=', 'vi.venta_id')
            ->join('productos as p', 'p.id', '=', 'vi.producto_id')
            ->where('v.empresa_id', $empresa)
            ->where('v.estado', 'completada')
            ->whereBetween('v.fecha_venta', [$desde . ' 00:00:00', $hasta . ' 23:59:59'])
            ->when($localId, fn ($q, $v) => $q->where('v.local_id', $v));
    }

    /** Líneas de devoluciones completadas del rango con su ítem de venta, alias dd / d / vi / v / p. */
    public function devoluciones(int $empresa, string $desde, string $hasta, ?int $localId = null): Builder
    {
        return DB::table('devoluciones_detalle as dd')
            ->join('devoluciones as d', 'd.id', '=', 'dd.devolucion_id')
            ->join('venta_items as vi', 'vi.id', '=', 'dd.venta_item_id')
            ->join('ventas as v', 'v.id', '=', 'vi.venta_id')
            ->join('productos as p', 'p.id', '=', 'vi.producto_id')
            ->where('d.empresa_id', $empresa)
            ->where('d.estado', 'completada')
            ->whereBetween('d.fecha', [$desde . ' 00:00:00', $hasta . ' 23:59:59'])
            ->when($localId, fn ($q, $v) => $q->where('d.local_id', $v));
    }

    /**
     * Lo que REALMENTE se cobró por una línea de venta: su subtotal menos su
     * parte del descuento GLOBAL de la venta (descuento_total), prorrateado por
     * el subtotal de cada línea. Σ líneas = total de la venta (como en
     * Venta::calcularTotales), así una tabla por producto suma lo mismo que las
     * ventas del período. `$vi` es el alias (o tabla) de venta_items.
     */
    public static function lineaNeta(string $vi = 'vi'): string
    {
        return "({$vi}.subtotal * (1 - COALESCE((SELECT vd.descuento_total
                    / NULLIF((SELECT SUM(vx.subtotal) FROM venta_items vx WHERE vx.venta_id = vd.id), 0)
                FROM ventas vd WHERE vd.id = {$vi}.venta_id AND vd.descuento_total > 0), 0)))";
    }

    /** Costo por unidad base de cada línea: regla única. */
    public function costo(): string
    {
        return CostoVentaService::sql('vi', 'p');
    }

    /**
     * Venta que deshace una línea de devolución: su parte del monto REAL de la
     * devolución, prorrateado por el subtotal bruto de cada línea.
     *
     *  - Con reembolso (dinero, vale o cambio): `monto_devolucion`, que ya trae el
     *    descuento global de la venta (dd.subtotal es el bruto: restarlo bajaba
     *    la utilidad en la parte del descuento).
     *  - Sin reembolso: solo lo que canceló de la CxC de una venta al crédito
     *    (abonos vivos del anticipo de la devolución). Esa venta ya no se va a
     *    cobrar; sin restarla la utilidad quedaba inflada. Al contado da 0.
     */
    public function devuelto(): string
    {
        return "(dd.subtotal * (CASE WHEN d.forma_reembolso <> 'sin_reembolso' THEN d.monto_devolucion
                    ELSE COALESCE((SELECT SUM(va.monto) FROM venta_abonos va
                        JOIN cliente_anticipos ca ON ca.id = va.cliente_anticipo_id
                        WHERE ca.devolucion_id = d.id), 0) END)
                / NULLIF((SELECT SUM(dx.subtotal) FROM devoluciones_detalle dx WHERE dx.devolucion_id = d.id), 0))";
    }

    /** Costo de lo que volvió al stock en una línea de devolución. */
    public function recuperado(): string
    {
        return 'CASE WHEN dd.restock THEN dd.cantidad_base * (' . $this->costo() . ') ELSE 0 END';
    }
}
