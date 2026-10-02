<?php

namespace App\Services;

use App\Models\Empresa;
use Carbon\Carbon;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Reporte de ventas para el DUEÑO (PDF): qué vendió, cuánto ganó, cómo le
 * pagaron, cuándo vende más, qué y a quién vende, quién vende y cuánto
 * descuenta. Sin el detalle venta por venta (eso vive en la pantalla).
 *
 * Recibe la consulta ya filtrada (local, cajero, método, tipo, comprobante)
 * como un closure fn(desde, hasta): Builder<Venta>, para que el PDF diga
 * exactamente lo mismo que la pantalla con los mismos filtros. Utilidad con
 * el costo congelado al vender (CostoVentaService::sql), igual que el
 * reporte de utilidad.
 */
class ReporteVentasPdfService
{
    private const DIAS = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];

    public const COMPROBANTES = [
        'ticket' => 'Nota de venta', 'boleta' => 'Boleta', 'factura' => 'Factura',
        'boleta_externa' => 'Boleta externa', 'factura_externa' => 'Factura externa',
    ];

    /**
     * @param  Closure(string, string): \Illuminate\Database\Eloquent\Builder  $consulta
     * @param  array{filtros?: string[], alcance?: ?string}  $contexto
     */
    public function armar(Empresa $empresa, string $desde, string $hasta, Closure $consulta, array $contexto = []): array
    {
        $d1   = Carbon::parse($desde);
        $d2   = Carbon::parse($hasta);
        $dias = $d1->diffInDays($d2) + 1;
        // Un mes calendario completo se compara con el mes anterior completo;
        // cualquier otro rango, con los mismos días inmediatamente anteriores.
        if ($d1->day === 1 && $d2->isSameDay($d1->copy()->endOfMonth()->startOfDay())) {
            $prevDesde = $d1->copy()->subMonthNoOverflow()->startOfMonth()->toDateString();
            $prevHasta = $d1->copy()->subMonthNoOverflow()->endOfMonth()->toDateString();
        } else {
            $prevDesde = $d1->copy()->subDays($dias)->toDateString();
            $prevHasta = $d1->copy()->subDay()->toDateString();
        }

        $actual   = $this->metricas($consulta, $desde, $hasta);
        $anterior = $this->metricas($consulta, $prevDesde, $prevHasta);

        $ok  = $consulta($desde, $hasta)->where('estado', 'completada');
        $ids = (clone $ok)->select('id');

        $productos = $this->productos($ids);
        $k = $actual + [
            'igv'          => round((float) (clone $ok)->sum('igv'), 2),
            'contado'      => round((float) (clone $ok)->where('es_credito', false)->sum('total'), 2),
            'credito'      => round((float) (clone $ok)->where('es_credito', true)->sum('total'), 2),
            'por_cobrar'   => round((float) (clone $ok)->sum('saldo_pendiente'), 2),
            'clientes'     => (int) (clone $ok)->whereHas('cliente', fn ($c) => $c->where('es_cliente_general', false))
                ->distinct('cliente_id')->count('cliente_id'),
            'anuladas_n'   => (int) $consulta($desde, $hasta)->where('estado', 'anulada')->count(),
            'anuladas'     => round((float) $consulta($desde, $hasta)->where('estado', 'anulada')->sum('total'), 2),
            'con_desc_n'   => (int) (clone $ok)->where(fn ($q) => $q->where('descuento_total', '>', 0)
                ->orWhereHas('items', fn ($i) => $i->where('descuento_item', '>', 0)))->count(),
        ];
        $k['base']       = round($k['total'] - $k['igv'], 2);
        $k['bruto']      = round($k['total'] + $k['descuentos'], 2);
        $k['desc_pct']   = $k['bruto'] > 0 ? round($k['descuentos'] / $k['bruto'] * 100, 1) : 0.0;
        $k['sin_costo_pct'] = $k['total'] > 0 ? round($k['sin_costo'] / $k['total'] * 100, 1) : 0.0;

        $comparar = fn (string $c) => $anterior[$c] != 0
            ? round(($actual[$c] - $anterior[$c]) / abs($anterior[$c]) * 100, 1)
            : null;
        $var = [
            'total'    => $comparar('total'),
            'n'        => $comparar('n'),
            'ticket'   => $comparar('ticket'),
            'utilidad' => $comparar('utilidad'),
        ];

        $metodos   = $this->metodos($ids);
        $serie     = $this->serie($ok, $d1, $d2, $dias);
        $semana    = $this->porDiaSemana($ok, $d1, $d2);
        $horas     = $this->porHora($ok);
        $mapa      = $this->mapaCalor($ok);
        $cajeros   = $this->cajeros($ok, $ids);
        $clientes  = $this->clientes($ok, $k['total']);
        $categorias = $this->categorias($productos, $k['total']);
        $descConceptos = $this->descuentosPorConcepto($ok, $ids);
        $comprobantes = (clone $ok)->selectRaw('tipo_comprobante, COUNT(*) as n, SUM(total) as total')
            ->groupBy('tipo_comprobante')->orderByDesc('total')->get()
            ->map(fn ($r) => [
                'nombre' => self::COMPROBANTES[$r->tipo_comprobante] ?? ucfirst((string) $r->tipo_comprobante),
                'n'      => (int) $r->n,
                'total'  => round((float) $r->total, 2),
                'pct'    => $k['total'] > 0 ? round($r->total / $k['total'] * 100, 1) : 0,
            ])->all();

        $top = collect($productos)->sortByDesc('total')->take(10)->values()->map(fn ($p) => $p + [
            'pct' => $k['total'] > 0 ? round($p['total'] / $k['total'] * 100, 1) : 0,
        ])->all();

        return [
            'empresa'   => $empresa,
            'logo'      => $this->logo($empresa),
            'periodo'   => $this->frasePeriodo($d1, $d2),
            'desde'     => $d1->format('d/m/Y'),
            'hasta'     => $d2->format('d/m/Y'),
            'anterior'  => [
                'desde'    => Carbon::parse($prevDesde)->format('d/m/Y'),
                'hasta'    => Carbon::parse($prevHasta)->format('d/m/Y'),
                'etiqueta' => $this->etiquetaAnterior(Carbon::parse($prevDesde), Carbon::parse($prevHasta)),
            ] + $anterior,
            'filtros'   => $contexto['filtros'] ?? [],
            'alcance'   => $contexto['alcance'] ?? null,
            'generado'  => now()->format('d/m/Y H:i'),
            'k'         => $k,
            'var'       => $var,
            'metodos'   => $metodos,
            'serie'     => $serie,
            'semana'    => $semana,
            'horas'     => $horas,
            'mapa'      => $mapa,
            'cajeros'   => $cajeros,
            'clientes'  => $clientes,
            'top'       => $top,
            'categorias'=> $categorias,
            'desc_conceptos' => $descConceptos,
            'comprobantes'   => $comprobantes,
            'claves'    => $this->claves($k, $var, $anterior, $serie, $semana, $horas, $top, $metodos, $cajeros),
        ];
    }

    /** Lo comparable entre dos períodos: total, ventas, ticket, utilidad, descuentos. */
    private function metricas(Closure $consulta, string $desde, string $hasta): array
    {
        $ok    = $consulta($desde, $hasta)->where('estado', 'completada');
        $total = (float) (clone $ok)->sum('total');
        $n     = (int) (clone $ok)->count();

        $lineas = DB::table('venta_items as vi')
            ->join('ventas as v', 'v.id', '=', 'vi.venta_id')
            ->join('productos as p', 'p.id', '=', 'vi.producto_id')
            ->whereIn('vi.venta_id', (clone $ok)->select('id'))
            ->selectRaw('COALESCE(SUM(vi.cantidad_base * (' . CostoVentaService::sql('vi', 'p') . ')), 0) as costo,
                         COALESCE(SUM(CASE WHEN (' . CostoVentaService::sql('vi', 'p') . ') > 0 THEN 0 ELSE vi.subtotal END), 0) as sin_costo,
                         COALESCE(SUM(vi.descuento_item * vi.cantidad), 0) as desc_items')
            ->first();

        $descuentos = (float) (clone $ok)->sum('descuento_total') + (float) $lineas->desc_items;
        $utilidad   = round($total - (float) $lineas->costo, 2);

        return [
            'total'      => round($total, 2),
            'n'          => $n,
            'ticket'     => $n > 0 ? round($total / $n, 2) : 0.0,
            'costo'      => round((float) $lineas->costo, 2),
            'utilidad'   => $utilidad,
            'margen'     => $total > 0 ? round($utilidad / $total * 100, 1) : null,
            'descuentos' => round($descuentos, 2),
            'sin_costo'  => round((float) $lineas->sin_costo, 2),
        ];
    }

    /** Una fila por producto: cantidad, monto, costo y utilidad. */
    private function productos($ids): array
    {
        return DB::table('venta_items as vi')
            ->join('ventas as v', 'v.id', '=', 'vi.venta_id')
            ->join('productos as p', 'p.id', '=', 'vi.producto_id')
            ->leftJoin('categorias as c', 'c.id', '=', 'p.categoria_id')
            ->whereIn('vi.venta_id', $ids)
            ->groupBy('vi.producto_id', 'c.nombre')
            ->selectRaw('vi.producto_id, MIN(vi.producto_nombre) as nombre, c.nombre as categoria,
                         MIN(vi.unidad_nombre) as u_min, MAX(vi.unidad_nombre) as u_max,
                         SUM(vi.cantidad) as cantidad, SUM(vi.subtotal) as total,
                         SUM(vi.cantidad_base * (' . CostoVentaService::sql('vi', 'p') . ')) as costo')
            ->get()
            ->map(fn ($r) => [
                'nombre'    => $r->nombre,
                'categoria' => $r->categoria ?: 'Sin categoría',
                'cantidad'  => (float) $r->cantidad,
                'unidad'    => $r->u_min === $r->u_max ? (string) $r->u_min : 'varias unid.',
                'total'     => round((float) $r->total, 2),
                'utilidad'  => round((float) $r->total - (float) $r->costo, 2),
                'margen'    => $r->total > 0 && $r->costo > 0 ? round(($r->total - $r->costo) / $r->total * 100, 1) : null,
            ])->all();
    }

    /**
     * Cómo le pagaron: lo cobrado al vender (sin vuelto), los abonos posteriores
     * a esas ventas y los anticipos del cliente aplicados. El saldo sin cobrar
     * va aparte (k.por_cobrar).
     */
    private function metodos($ids): array
    {
        $filas = DB::table('venta_pagos as vp')
            ->join('metodos_pago as m', 'm.id', '=', 'vp.metodo_pago_id')
            ->whereIn('vp.venta_id', $ids)
            ->groupBy('m.nombre')
            ->selectRaw('m.nombre, SUM(vp.monto - COALESCE(vp.vuelto, 0)) as total, COUNT(DISTINCT vp.venta_id) as n')
            ->get()
            ->concat(DB::table('venta_abonos as a')
                ->leftJoin('metodos_pago as m', 'm.id', '=', 'a.metodo_pago_id')
                ->whereIn('a.venta_id', $ids)
                ->groupBy('m.nombre')
                ->selectRaw("COALESCE(m.nombre, 'Anticipo del cliente') as nombre, SUM(a.monto) as total, COUNT(DISTINCT a.venta_id) as n")
                ->get());

        $total = (float) $filas->sum('total');

        return $filas->groupBy('nombre')
            ->map(fn ($g, $nombre) => ['nombre' => (string) $nombre, 'total' => round((float) $g->sum('total'), 2), 'n' => (int) $g->sum('n')])
            ->filter(fn ($m) => $m['total'] > 0.004)
            ->sortByDesc('total')->values()
            ->map(fn ($m) => $m + ['pct' => $total > 0 ? round($m['total'] / $total * 100, 1) : 0])
            ->all();
    }

    /** Serie para el gráfico: por día hasta 31 días, por semana hasta 120, por mes después. */
    private function serie($ok, Carbon $d1, Carbon $d2, int $dias): array
    {
        $grupo = $dias <= 31 ? 'dia' : ($dias <= 120 ? 'semana' : 'mes');
        $expr  = match ($grupo) {
            'dia'    => "DATE(fecha_venta)",
            'semana' => "DATE(date_trunc('week', fecha_venta))",
            'mes'    => "DATE(date_trunc('month', fecha_venta))",
        };
        $datos = (clone $ok)->selectRaw("{$expr} as k, SUM(total) as total, COUNT(*) as n")
            ->groupByRaw($expr)->get()
            ->mapWithKeys(fn ($r) => [substr((string) $r->k, 0, 10) => ['total' => (float) $r->total, 'n' => (int) $r->n]]);

        // Todos los tramos del rango, también los que no vendieron (barra vacía).
        $puntos = [];
        $c = match ($grupo) {
            'dia'    => $d1->copy(),
            'semana' => $d1->copy()->startOfWeek(),
            'mes'    => $d1->copy()->startOfMonth(),
        };
        while ($c->lte($d2)) {
            $clave = $c->toDateString();
            $puntos[] = [
                'etiqueta' => match ($grupo) {
                    'dia'    => $c->format('d'),
                    'semana' => $c->format('d/m'),
                    'mes'    => ucfirst($c->locale('es')->translatedFormat('M y')),
                },
                'sub'   => $grupo === 'dia' ? mb_substr(self::DIAS[$c->dayOfWeekIso], 0, 2) : '',
                'finde' => $grupo === 'dia' && $c->dayOfWeekIso >= 6,
                'fecha' => $c->copy(),
                'total' => round($datos[$clave]['total'] ?? 0, 2),
                'n'     => $datos[$clave]['n'] ?? 0,
            ];
            match ($grupo) {
                'dia'    => $c->addDay(),
                'semana' => $c->addWeek(),
                'mes'    => $c->addMonth(),
            };
        }
        $max = max(array_column($puntos, 'total') ?: [0]);

        return ['grupo' => $grupo, 'max' => $max, 'puntos' => $puntos];
    }

    /** Por día de la semana: total y PROMEDIO por día (un mes no tiene igual cantidad de lunes que de domingos). */
    private function porDiaSemana($ok, Carbon $d1, Carbon $d2): array
    {
        $datos = (clone $ok)->selectRaw('EXTRACT(ISODOW FROM fecha_venta)::int as d, SUM(total) as total, COUNT(*) as n')
            ->groupByRaw('EXTRACT(ISODOW FROM fecha_venta)')->get()->keyBy('d');

        $ocurrencias = array_fill(1, 7, 0);
        for ($c = $d1->copy(); $c->lte($d2); $c->addDay()) {
            $ocurrencias[$c->dayOfWeekIso]++;
        }

        $filas = [];
        foreach (self::DIAS as $i => $nombre) {
            $total = (float) ($datos[$i]->total ?? 0);
            $filas[] = [
                'nombre'   => $nombre,
                'total'    => round($total, 2),
                'n'        => (int) ($datos[$i]->n ?? 0),
                'promedio' => $ocurrencias[$i] > 0 ? round($total / $ocurrencias[$i], 2) : 0.0,
            ];
        }
        $max = max(array_column($filas, 'promedio') ?: [0]);

        return ['max' => $max, 'filas' => $filas];
    }

    /** Por hora del día (solo el tramo en que hubo ventas). */
    private function porHora($ok): array
    {
        $datos = (clone $ok)->selectRaw('EXTRACT(HOUR FROM fecha_venta)::int as h, SUM(total) as total, COUNT(*) as n')
            ->groupByRaw('EXTRACT(HOUR FROM fecha_venta)')->get()->keyBy('h');
        if ($datos->isEmpty()) return ['max' => 0, 'filas' => []];

        $filas = [];
        for ($h = (int) $datos->keys()->min(); $h <= (int) $datos->keys()->max(); $h++) {
            $filas[] = ['h' => $h, 'etiqueta' => self::hora($h), 'total' => round((float) ($datos[$h]->total ?? 0), 2), 'n' => (int) ($datos[$h]->n ?? 0)];
        }

        return ['max' => max(array_column($filas, 'total')), 'filas' => $filas];
    }

    /** Mapa de calor día de la semana × hora: dónde se concentra la venta. */
    private function mapaCalor($ok): array
    {
        $datos = (clone $ok)->selectRaw('EXTRACT(ISODOW FROM fecha_venta)::int as d, EXTRACT(HOUR FROM fecha_venta)::int as h, SUM(total) as total')
            ->groupByRaw('EXTRACT(ISODOW FROM fecha_venta), EXTRACT(HOUR FROM fecha_venta)')->get();
        if ($datos->isEmpty()) return ['horas' => [], 'filas' => []];

        $hMin = (int) $datos->min('h');
        $hMax = (int) $datos->max('h');
        $max  = (float) $datos->max('total');
        $celda = [];
        foreach ($datos as $r) $celda[$r->d][$r->h] = (float) $r->total;

        $filas = [];
        foreach (self::DIAS as $i => $nombre) {
            $cols = [];
            for ($h = $hMin; $h <= $hMax; $h++) {
                $v = $celda[$i][$h] ?? 0.0;
                $cols[] = ['total' => $v, 'color' => self::intensidad($max > 0 ? $v / $max : 0)];
            }
            $filas[] = ['nombre' => mb_substr($nombre, 0, 3), 'cols' => $cols];
        }

        return ['horas' => range($hMin, $hMax), 'filas' => $filas];
    }

    /** Quién vendió: monto, ventas, ticket y descuentos que otorgó. */
    private function cajeros($ok, $ids): array
    {
        $descItems = DB::table('venta_items as vi')->join('ventas as v', 'v.id', '=', 'vi.venta_id')
            ->whereIn('vi.venta_id', $ids)->groupBy('v.user_id')
            ->selectRaw('v.user_id, SUM(vi.descuento_item * vi.cantidad) as d')->pluck('d', 'user_id');

        $filas = DB::table('ventas as v')->leftJoin('users as u', 'u.id', '=', 'v.user_id')
            ->whereIn('v.id', $ids)
            ->groupBy('v.user_id', 'u.name')
            ->selectRaw('v.user_id, u.name, SUM(v.total) as total, COUNT(*) as n, SUM(v.descuento_total) as d')
            ->orderByDesc('total')->get();
        $total = (float) $filas->sum('total');

        return $filas->map(fn ($r) => [
            'nombre'     => $r->name ?? '—',
            'total'      => round((float) $r->total, 2),
            'n'          => (int) $r->n,
            'ticket'     => $r->n > 0 ? round($r->total / $r->n, 2) : 0,
            'descuentos' => round((float) $r->d + (float) ($descItems[$r->user_id] ?? 0), 2),
            'pct'        => $total > 0 ? round($r->total / $total * 100, 1) : 0,
        ])->all();
    }

    /** Mejores clientes identificados; "Clientes varios" va como una sola línea aparte. */
    private function clientes($ok, float $totalPeriodo): array
    {
        $filas = DB::table('ventas as v')->join('clientes as c', 'c.id', '=', 'v.cliente_id')
            ->whereIn('v.id', (clone $ok)->select('id'))
            ->groupBy('c.id', 'c.razon_social', 'c.nombres', 'c.apellidos', 'c.es_cliente_general')
            ->selectRaw('c.id, c.razon_social, c.nombres, c.apellidos, c.es_cliente_general, SUM(v.total) as total, COUNT(*) as n')
            ->orderByDesc('total')->get();

        $varios = $filas->where('es_cliente_general', true);
        $top = $filas->where('es_cliente_general', false)->take(10)->values()->map(fn ($r) => [
            'nombre' => $r->razon_social ?: trim(($r->nombres ?? '') . ' ' . ($r->apellidos ?? '')) ?: '—',
            'total'  => round((float) $r->total, 2),
            'n'      => (int) $r->n,
            'pct'    => $totalPeriodo > 0 ? round($r->total / $totalPeriodo * 100, 1) : 0,
        ])->all();

        $variosTotal = (float) $varios->sum('total');

        return [
            'top'          => $top,
            'varios_total' => round($variosTotal, 2),
            'varios_n'     => (int) $varios->sum('n'),
            'varios_pct'   => $totalPeriodo > 0 ? round($variosTotal / $totalPeriodo * 100, 1) : 0,
        ];
    }

    private function categorias(array $productos, float $totalPeriodo): array
    {
        $filas = collect($productos)->groupBy('categoria')
            ->map(fn ($g, $c) => [
                'nombre'   => (string) $c,
                'total'    => round($g->sum('total'), 2),
                'utilidad' => round($g->sum('utilidad'), 2),
            ])
            ->sortByDesc('total')->values();

        // Más de 8 categorías: las chicas se juntan en "Otras".
        if ($filas->count() > 8) {
            $resto  = $filas->slice(7);
            $filas  = $filas->take(7)->push([
                'nombre' => 'Otras (' . $resto->count() . ')', 'total' => round($resto->sum('total'), 2), 'utilidad' => round($resto->sum('utilidad'), 2),
            ]);
        }

        return $filas->map(fn ($c) => $c + [
            'pct'    => $totalPeriodo > 0 ? round($c['total'] / $totalPeriodo * 100, 1) : 0,
            'margen' => $c['total'] > 0 ? round($c['utilidad'] / $c['total'] * 100, 1) : null,
        ])->all();
    }

    /** Descuentos por motivo (concepto elegido al descontar). */
    private function descuentosPorConcepto($ok, $ids): array
    {
        $porLinea = DB::table('venta_items as vi')
            ->leftJoin('descuento_conceptos as dc', 'dc.id', '=', 'vi.descuento_concepto_id')
            ->whereIn('vi.venta_id', $ids)->where('vi.descuento_item', '>', 0)
            ->groupBy('dc.nombre')
            ->selectRaw("COALESCE(dc.nombre, 'Sin motivo indicado') as nombre, SUM(vi.descuento_item * vi.cantidad) as total, COUNT(DISTINCT vi.venta_id) as n")
            ->get();
        $global = DB::table('ventas as v')->leftJoin('descuento_conceptos as dc', 'dc.id', '=', 'v.descuento_concepto_id')
            ->whereIn('v.id', $ids)->where('v.descuento_total', '>', 0)
            ->groupBy('dc.nombre')
            ->selectRaw("COALESCE(dc.nombre, 'Sin motivo indicado') as nombre, SUM(v.descuento_total) as total, COUNT(*) as n")
            ->get();

        return $porLinea->concat($global)->groupBy('nombre')
            ->map(fn ($g, $nombre) => ['nombre' => (string) $nombre, 'total' => round((float) $g->sum('total'), 2), 'n' => (int) $g->sum('n')])
            ->sortByDesc('total')->values()->take(6)->all();
    }

    /** Frases clave en lenguaje del dueño, armadas con los datos del período. */
    private function claves(array $k, array $var, array $ant, array $serie, array $semana, array $horas, array $top, array $metodos, array $cajeros): array
    {
        $s = fn ($v) => 'S/ ' . number_format((float) $v, 2, '.', ',');
        $f = [];
        if ($k['n'] === 0) return ['No hubo ventas en este período.'];

        if ($var['total'] !== null) {
            $f[] = ['tono' => $var['total'] >= 0 ? 'bien' : 'alerta',
                'texto' => 'Vendiste ' . $s($k['total']) . ', ' . ($var['total'] >= 0 ? 'un ' . abs($var['total']) . '% más' : 'un ' . abs($var['total']) . '% menos')
                    . ' que en el período anterior (' . $s($ant['total']) . ').'];
        }
        if ($k['margen'] !== null && $k['utilidad'] != 0) {
            $f[] = ['tono' => 'info', 'texto' => 'Ganaste ' . $s($k['utilidad']) . ' sobre el costo de lo vendido: '
                . $k['margen'] . ' de cada 100 soles vendidos quedaron como utilidad bruta.'];
        }
        $mejor = collect($serie['puntos'])->sortByDesc('total')->first();
        if ($mejor && $mejor['total'] > 0 && $serie['grupo'] === 'dia') {
            $f[] = ['tono' => 'info', 'texto' => 'Tu mejor día fue el ' . mb_strtolower(self::DIAS[$mejor['fecha']->dayOfWeekIso]) . ' '
                . $mejor['fecha']->format('d/m') . ' con ' . $s($mejor['total']) . ' en ' . $mejor['n'] . ' ventas.'];
        }
        $diaTop = collect($semana['filas'])->sortByDesc('promedio')->first();
        $diaLow = collect($semana['filas'])->filter(fn ($d) => $d['promedio'] > 0)->sortBy('promedio')->first();
        if ($diaTop && $diaTop['promedio'] > 0) {
            $f[] = ['tono' => 'info', 'texto' => 'Los ' . mb_strtolower($diaTop['nombre']) . ' son tu día más fuerte (promedio ' . $s($diaTop['promedio']) . ' por día)'
                . ($diaLow && $diaLow['nombre'] !== $diaTop['nombre'] ? '; los ' . mb_strtolower($diaLow['nombre']) . ' los más flojos (' . $s($diaLow['promedio']) . ').' : '.')];
        }
        $horaTop = collect($horas['filas'])->sortByDesc('total')->first();
        if ($horaTop && $k['total'] > 0) {
            $f[] = ['tono' => 'info', 'texto' => 'La hora pico es de ' . self::hora($horaTop['h']) . ' a ' . self::hora($horaTop['h'] + 1)
                . ': ahí entra el ' . round($horaTop['total'] / $k['total'] * 100, 1) . '% de lo vendido.'];
        }
        if (!empty($top)) {
            $f[] = ['tono' => 'info', 'texto' => 'Producto estrella: ' . $top[0]['nombre'] . ', con el ' . $top[0]['pct'] . '% de las ventas (' . $s($top[0]['total']) . ').'];
        }
        if (!empty($metodos)) {
            $f[] = ['tono' => 'info', 'texto' => 'El ' . $metodos[0]['pct'] . '% de lo cobrado entró por ' . $metodos[0]['nombre'] . '.'];
        }
        if ($k['descuentos'] > 0) {
            $f[] = ['tono' => $k['desc_pct'] >= 5 ? 'alerta' : 'info', 'texto' => 'Se dieron ' . $s($k['descuentos']) . ' en descuentos ('
                . $k['desc_pct'] . '% del precio de lista) en ' . $k['con_desc_n'] . ' ventas.'];
        }
        if ($k['por_cobrar'] > 0) {
            $f[] = ['tono' => 'alerta', 'texto' => 'Quedan ' . $s($k['por_cobrar']) . ' por cobrar de ventas a crédito de este período.'];
        }
        if ($k['anuladas_n'] > 0) {
            $f[] = ['tono' => 'alerta', 'texto' => $k['anuladas_n'] . ($k['anuladas_n'] === 1 ? ' venta anulada' : ' ventas anuladas') . ' por ' . $s($k['anuladas']) . ' (no suman al total).'];
        }

        return $f;
    }

    private function frasePeriodo(Carbon $d1, Carbon $d2): string
    {
        if ($d1->isSameDay($d2)) return ucfirst($d1->locale('es')->translatedFormat('l j \d\e F \d\e Y'));
        if ($d1->day === 1 && $d2->isSameDay($d1->copy()->endOfMonth()->startOfDay())) {
            return ucfirst($d1->locale('es')->translatedFormat('F \d\e Y'));
        }

        return 'Del ' . $d1->format('d/m/Y') . ' al ' . $d2->format('d/m/Y');
    }

    /** "agosto de 2026" para un mes completo; "el 02/08/2026 – 31/08/2026" para otro rango. */
    private function etiquetaAnterior(Carbon $d1, Carbon $d2): string
    {
        if ($d1->day === 1 && $d2->isSameDay($d1->copy()->endOfMonth()->startOfDay())) {
            return $d1->locale('es')->translatedFormat('F \d\e Y');
        }

        return $d1->isSameDay($d2) ? 'el ' . $d1->format('d/m/Y') : 'el ' . $d1->format('d/m/Y') . ' – ' . $d2->format('d/m/Y');
    }

    public static function hora(int $h): string
    {
        $h = $h % 24;
        $sufijo = $h < 12 ? 'a. m.' : 'p. m.';
        $h12 = $h % 12 === 0 ? 12 : $h % 12;

        return "{$h12} {$sufijo}";
    }

    /** Del blanco al azul de la marca según la intensidad 0..1. */
    private static function intensidad(float $t): string
    {
        if ($t <= 0) return '#f4f7fb';
        $t = 0.12 + 0.88 * min(1, $t);
        $de = [232, 241, 251]; $a = [15, 76, 129];
        $c = array_map(fn ($x, $y) => (int) round($x + ($y - $x) * $t), $de, $a);

        return sprintf('#%02x%02x%02x', ...$c);
    }

    private function logo(Empresa $empresa): ?string
    {
        try {
            if ($empresa->logo && Storage::disk('public')->exists($empresa->logo)) {
                $ext  = strtolower(pathinfo($empresa->logo, PATHINFO_EXTENSION));
                $mime = match ($ext) { 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif', default => 'image/jpeg' };

                return 'data:' . $mime . ';base64,' . base64_encode(Storage::disk('public')->get($empresa->logo));
            }
        } catch (\Throwable) {
        }

        return null;
    }
}
