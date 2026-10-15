<?php

namespace App\Services;

use App\Models\Empresa;
use Carbon\Carbon;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Reporte de ventas ÍTEM POR ÍTEM (PDF para mandar al cliente):
 *  1) consolidado por producto (cantidad, nº de ventas, precio promedio, total) y
 *  2) detalle línea por línea agrupado por día, con subtotal diario.
 *
 * Solo ventas completadas. Los montos de línea son los cobrados en la línea
 * (después del descuento por ítem); el descuento global de cada venta se
 * muestra aparte para que el total cuadre con el total cobrado.
 * Usa la misma consulta filtrada que la pantalla (closure fn(desde, hasta)).
 */
class ReporteVentasItemsService
{
    /** Más líneas que esto no caben en un PDF razonable: solo va el consolidado. */
    public const MAX_LINEAS_DETALLE = 4000;

    private const DIAS = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];

    /**
     * @param  Closure(string, string): \Illuminate\Database\Eloquent\Builder  $consulta
     */
    public function armar(Empresa $empresa, string $desde, string $hasta, Closure $consulta, array $filtros = [], bool $conDetalle = true): array
    {
        $d1  = Carbon::parse($desde);
        $d2  = Carbon::parse($hasta);
        $ok  = $consulta($desde, $hasta)->where('estado', 'completada');
        $ids = (clone $ok)->select('id');

        $totales = (clone $ok)->selectRaw('COUNT(*) as n, COALESCE(SUM(total), 0) as total,
                COALESCE(SUM(descuento_total), 0) as desc_global, COALESCE(SUM(igv), 0) as igv')
            ->toBase()->first();

        $productos = $this->productos($ids);
        $nLineas   = (int) DB::table('venta_items')->whereIn('venta_id', $ids)->count();
        $detalle   = $conDetalle && $nLineas <= self::MAX_LINEAS_DETALLE ? $this->detalle($ids) : null;

        $sumaLineas = round(array_sum(array_column($productos, 'total')), 2);
        $descItems  = round(array_sum(array_column($productos, 'descuento')), 2);

        return [
            'empresa'    => $empresa,
            'logo'       => $this->logo($empresa),
            'periodo'    => 'Del ' . $d1->format('d/m/Y') . ' al ' . $d2->format('d/m/Y'),
            'filtros'    => $filtros,
            'generado'   => now()->format('d/m/Y H:i'),
            'k' => [
                'ventas'      => (int) $totales->n,
                'total'       => round((float) $totales->total, 2),
                'igv'         => round((float) $totales->igv, 2),
                'base'        => round((float) $totales->total - (float) $totales->igv, 2),
                'lineas'      => $nLineas,
                'productos'   => count($productos),
                'suma_lineas' => $sumaLineas,
                'desc_items'  => $descItems,
                'desc_global' => round((float) $totales->desc_global, 2),
                'unidades'    => array_sum(array_column($productos, 'cantidad')),
            ],
            'productos'     => $productos,
            'dias'          => $detalle,
            'detalle_pedido'=> $conDetalle,
            'max_lineas'    => self::MAX_LINEAS_DETALLE,
            'devoluciones'  => $this->devoluciones($ids),
        ];
    }

    /** Una fila por producto y unidad de venta. */
    private function productos($ids): array
    {
        return DB::table('venta_items as vi')
            ->whereIn('vi.venta_id', $ids)
            ->groupBy('vi.producto_id', 'vi.unidad_nombre')
            ->selectRaw('vi.producto_id, MIN(vi.producto_nombre) as nombre, vi.unidad_nombre as unidad,
                         SUM(vi.cantidad) as cantidad, COUNT(DISTINCT vi.venta_id) as ventas,
                         SUM(vi.subtotal) as total, SUM(vi.descuento_item * vi.cantidad) as descuento,
                         MIN(vi.precio_unitario) as p_min, MAX(vi.precio_unitario) as p_max')
            ->orderByDesc('total')->orderBy('nombre')
            ->get()
            ->map(fn ($r) => [
                'nombre'    => $r->nombre,
                'unidad'    => $r->unidad,
                'cantidad'  => (float) $r->cantidad,
                'ventas'    => (int) $r->ventas,
                'total'     => round((float) $r->total, 2),
                'descuento' => round((float) $r->descuento, 2),
                'promedio'  => $r->cantidad > 0 ? round((float) $r->total / (float) $r->cantidad, 2) : 0.0,
                'p_min'     => (float) $r->p_min,
                'p_max'     => (float) $r->p_max,
            ])->all();
    }

    /** Líneas agrupadas por día (y por venta, para el descuento global). */
    private function detalle($ids): array
    {
        $filas = DB::table('venta_items as vi')
            ->join('ventas as v', 'v.id', '=', 'vi.venta_id')
            ->leftJoin('clientes as c', 'c.id', '=', 'v.cliente_id')
            ->whereIn('vi.venta_id', $ids)
            ->orderBy('v.fecha_venta')->orderBy('v.id')->orderBy('vi.id')
            ->get([
                'v.id as venta_id', 'v.fecha_venta', 'v.numero', 'v.numero_comprobante', 'v.tipo_comprobante',
                'v.descuento_total', 'v.total as total_venta',
                'c.razon_social', 'c.nombres', 'c.apellidos', 'c.es_cliente_general',
                'vi.producto_nombre', 'vi.descripcion', 'vi.unidad_nombre', 'vi.cantidad',
                'vi.precio_unitario', 'vi.descuento_item', 'vi.subtotal',
            ]);

        $dias = [];
        foreach ($filas as $f) {
            $fecha = Carbon::parse($f->fecha_venta);
            $clave = $fecha->toDateString();
            $dias[$clave] ??= [
                'titulo' => self::DIAS[$fecha->isoWeekday()] . ' ' . $fecha->format('d/m/Y'),
                'lineas' => [], 'ventas' => [], 'total' => 0.0, 'desc_global' => 0.0,
            ];
            $d = &$dias[$clave];

            $primera = ! isset($d['ventas'][$f->venta_id]);
            if ($primera) {
                $d['ventas'][$f->venta_id] = true;
                $d['total']       += (float) $f->total_venta;
                $d['desc_global'] += (float) $f->descuento_total;
            }

            $cliente = $f->es_cliente_general ? '' : ($f->razon_social ?: trim(($f->nombres ?? '') . ' ' . ($f->apellidos ?? '')));
            // El descuento global va en la última línea de la venta: se corre si llega otra.
            if (! $primera) {
                $d['lineas'][array_key_last($d['lineas'])]['desc_global'] = 0.0;
            }
            $d['lineas'][] = [
                'primera'     => $primera,
                'hora'        => $fecha->format('H:i'),
                'documento'   => $f->numero_comprobante ?: $f->numero,
                'cliente'     => $cliente,
                'producto'    => $f->producto_nombre . ($f->descripcion ? ' — ' . $f->descripcion : ''),
                'unidad'      => $f->unidad_nombre,
                'cantidad'    => (float) $f->cantidad,
                'precio'      => (float) $f->precio_unitario,
                'descuento'   => (float) $f->descuento_item * (float) $f->cantidad,
                'subtotal'    => (float) $f->subtotal,
                'desc_global' => (float) $f->descuento_total,
            ];
            unset($d);
        }

        return array_values(array_map(fn ($d) => [
            'titulo'      => $d['titulo'],
            'lineas'      => $d['lineas'],
            'n_ventas'    => count($d['ventas']),
            'total'       => round($d['total'], 2),
            'desc_global' => round($d['desc_global'], 2),
        ], $dias));
    }

    /** Devoluciones de estas ventas (informativo: no se restan de las líneas). */
    private function devoluciones($ids): array
    {
        $r = DB::table('devoluciones')->whereIn('venta_id', $ids)
            ->whereIn('estado', ['aprobada', 'completada'])
            ->selectRaw('COUNT(*) as n, COALESCE(SUM(monto_devolucion), 0) as monto')->first();

        return ['n' => (int) $r->n, 'monto' => round((float) $r->monto, 2)];
    }

    private function logo(Empresa $empresa): ?string
    {
        try {
            $disk = \Illuminate\Support\Facades\Storage::disk('public');
            if ($empresa->logo && $disk->exists($empresa->logo)) {
                $ext  = strtolower(pathinfo($empresa->logo, PATHINFO_EXTENSION));
                $mime = match ($ext) { 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif', default => 'image/jpeg' };

                return 'data:' . $mime . ';base64,' . base64_encode($disk->get($empresa->logo));
            }
        } catch (\Throwable) {
        }

        return null;
    }
}
