<?php

namespace App\Services;

use App\Jobs\ReconstruirParKardex;
use App\Models\Local;
use App\Models\Producto;
use App\Models\Stock;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * MOTOR ÚNICO DE INVENTARIO.
 *
 * Antes había tres cálculos de stock y costo que no coincidían: el ajuste en
 * vivo (Stock::ajustar), la reconstrucción de la tabla stock (Stock::reconstruir)
 * y la del kardex (kardex:reconstruir). Cada "Recalcular" escribía un costo en la
 * tabla stock y otro distinto en el kardex, así que el inventario nunca terminaba
 * de cuadrar y había que apretar el botón una y otra vez.
 *
 * Ahora la fuente de verdad son los DOCUMENTOS (inventario inicial, entradas,
 * salidas, ajustes, transferencias, ventas, entregas de pendientes, devoluciones
 * y cierres), reproducidos en orden cronológico. De esa reproducción sale el
 * kardex, y la tabla `stock` es simplemente su última fila. Stock::ajustar agrega
 * filas en vivo con la MISMA fórmula, y cuando un movimiento rompe el orden este
 * motor rearma solo ese producto (programarReconstruccion).
 */
class KardexService
{
    /**
     * Movimientos en vivo que corrigen historia ya registrada. Dejan filas de
     * reverso/reaplicación que el kardex canónico no tiene, así que el producto
     * se rearma siempre desde sus documentos.
     */
    public const TIPOS_CORRECCION = [
        'entrada_reverso', 'entrada_edicion',
        'venta_anulacion',
        'devolucion_reverso',
        'transferencia_reverso', 'transferencia_reaplicacion',
        'ajuste_entrega_pendiente', 'anulacion_entrega_pendiente',
    ];

    /** Por debajo de esta diferencia dos cantidades o costos se consideran iguales. */
    private const EPS = 0.00005;

    /**
     * Rearma el kardex y la tabla stock de UN (almacén, producto).
     *
     * Solo escribe lo que cambió: si el kardex existente ya describe la misma
     * historia (mismo saldo, costo y última compra al cierre de cada día) no se
     * toca, y la tabla stock se actualiza solo si difiere de la última fila. Así
     * puede correr sobre toda la empresa cada noche sin reescribir lo que está bien.
     *
     * Con $simular = true no escribe nada: devuelve qué cambiaría.
     */
    public function reconstruirPar(int $almacenId, int $productoId, bool $simular = false): array
    {
        $resultado = [
            'almacen_id'       => $almacenId,
            'producto_id'      => $productoId,
            'kardex_cambia'    => false,
            'stock_cambia'     => false,
            'sin_respaldo'     => false,
            'cantidad_antes'   => 0.0,
            'cantidad_despues' => 0.0,
            'costo_antes'      => 0.0,
            'costo_despues'    => 0.0,
        ];

        $almacen = DB::table('almacenes')->where('id', $almacenId)->first(['id', 'empresa_id', 'local_id', 'tipo']);
        if (!$almacen) {
            return $resultado;
        }

        return DB::transaction(function () use ($almacen, $almacenId, $productoId, $simular, $resultado) {
            // Mismo cerrojo que Stock::ajustar: una venta del mismo producto espera a
            // que termine la reconstrucción, y viceversa. Sin carreras.
            if (!$simular) {
                DB::statement(
                    'INSERT INTO stock (almacen_id, producto_id, cantidad, costo_promedio, created_at, updated_at)
                     VALUES (?, ?, 0, 0, NOW(), NOW())
                     ON CONFLICT (almacen_id, producto_id) DO NOTHING',
                    [$almacenId, $productoId]
                );
            }

            $stock = Stock::where('almacen_id', $almacenId)
                ->where('producto_id', $productoId)
                ->when(!$simular, fn ($q) => $q->lockForUpdate())
                ->first();

            $movs   = $this->movimientos($almacen, $productoId);
            $filas  = $this->reproducir($almacen, $productoId, $movs);
            $ultima = empty($filas) ? null : $filas[count($filas) - 1];

            $resultado['cantidad_antes']   = (float) ($stock->cantidad ?? 0);
            $resultado['costo_antes']      = (float) ($stock->costo_promedio ?? 0);
            $resultado['cantidad_despues'] = $ultima ? (float) $ultima['saldo_cantidad'] : 0.0;
            $resultado['costo_despues']    = $ultima ? (float) $ultima['costo_promedio'] : 0.0;

            // Blindaje: un stock que NO tiene ningún documento que lo respalde no se
            // pone en cero — no hay forma de saber cuál es su valor real. Se informa
            // para que alguien lo revise, pero no se toca.
            if (empty($movs) && abs($resultado['cantidad_antes']) > self::EPS) {
                $resultado['sin_respaldo']     = true;
                $resultado['cantidad_despues'] = $resultado['cantidad_antes'];
                $resultado['costo_despues']    = $resultado['costo_antes'];

                return $resultado;
            }

            $existentes = DB::table('movimientos_inventario')
                ->where('almacen_id', $almacenId)
                ->where('producto_id', $productoId)
                ->orderBy('fecha')->orderBy('id')
                ->get(['fecha', 'tipo', 'costo_unitario', 'costo_promedio', 'saldo_cantidad'])
                ->map(fn ($r) => (array) $r)
                ->all();

            $resultado['kardex_cambia'] = self::firma($existentes) !== self::firma($filas);
            $resultado['stock_cambia']  = abs($resultado['cantidad_antes'] - $resultado['cantidad_despues']) > self::EPS
                || abs($resultado['costo_antes'] - $resultado['costo_despues']) > self::EPS;

            if ($simular) {
                return $resultado;
            }

            if ($resultado['kardex_cambia']) {
                DB::table('movimientos_inventario')
                    ->where('almacen_id', $almacenId)
                    ->where('producto_id', $productoId)
                    ->delete();
                foreach (array_chunk($filas, 500) as $chunk) {
                    DB::table('movimientos_inventario')->insert($chunk);
                }
            }

            if ($resultado['stock_cambia']) {
                $stock->update([
                    'cantidad'       => round($resultado['cantidad_despues'], 4),
                    'costo_promedio' => round($resultado['costo_despues'], 4),
                ]);
            }

            return $resultado;
        });
    }

    /**
     * Rearma todos los productos de los almacenes indicados. Cada producto va en
     * su propia transacción: no bloquea el inventario completo mientras corre.
     */
    public function reconstruirAlmacenes(array $almacenIds, bool $simular = false, ?callable $alAvanzar = null, ?Collection $pares = null): array
    {
        $pares ??= $this->paresDeAlmacenes($almacenIds);

        $res = [
            'pares'             => $pares->count(),
            'kardex_corregidos' => 0,
            'stock_corregidos'  => 0,
            'sin_respaldo'      => 0,
            'unidades_antes'    => 0.0,
            'unidades_despues'  => 0.0,
            'valor_antes'       => 0.0,
            'valor_despues'     => 0.0,
            'cambios'           => [],
        ];

        foreach ($pares as $p) {
            $r = $this->reconstruirPar((int) $p['almacen_id'], (int) $p['producto_id'], $simular);

            $res['unidades_antes']   += $r['cantidad_antes'];
            $res['unidades_despues'] += $r['cantidad_despues'];
            $res['valor_antes']      += $r['cantidad_antes'] * $r['costo_antes'];
            $res['valor_despues']    += $r['cantidad_despues'] * $r['costo_despues'];

            if ($r['kardex_cambia']) $res['kardex_corregidos']++;
            if ($r['stock_cambia'])  $res['stock_corregidos']++;
            if ($r['sin_respaldo'])  $res['sin_respaldo']++;
            if ($r['kardex_cambia'] || $r['stock_cambia'] || $r['sin_respaldo']) {
                $res['cambios'][] = $r;
            }

            if ($alAvanzar) $alAvanzar();
        }

        foreach (['unidades_antes', 'unidades_despues', 'valor_antes', 'valor_despues'] as $k) {
            $res[$k] = round($res[$k], 2);
        }

        return $res;
    }

    /**
     * Pares (almacén, producto) a revisar: los que tienen documentos, más los que
     * ya tienen fila de stock o de kardex (para limpiar restos sin documento).
     */
    public function paresDeAlmacenes(array $almacenIds): Collection
    {
        if (empty($almacenIds)) {
            return collect();
        }

        $conFila = DB::table('stock')->whereIn('almacen_id', $almacenIds)
            ->select('almacen_id', 'producto_id')->get()
            ->merge(DB::table('movimientos_inventario')->whereIn('almacen_id', $almacenIds)
                ->select('almacen_id', 'producto_id')->distinct()->get())
            ->map(fn ($r) => ['almacen_id' => (int) $r->almacen_id, 'producto_id' => (int) $r->producto_id]);

        return Stock::combinacionesConMovimientos($almacenIds)
            ->map(fn ($p) => ['almacen_id' => (int) $p['almacen_id'], 'producto_id' => (int) $p['producto_id']])
            ->merge($conFila)
            ->unique(fn ($p) => $p['almacen_id'] . '-' . $p['producto_id'])
            ->values();
    }

    /**
     * Agenda la reconstrucción de un producto en la cola, DESPUÉS de que se
     * confirme la operación que la pidió (si se revierte, no se agenda nada).
     */
    public function programarReconstruccion(int $almacenId, int $productoId, string $motivo): void
    {
        if (!config('inventario.autocorreccion_kardex', true)) {
            return;
        }

        DB::afterCommit(fn () => ReconstruirParKardex::dispatch($almacenId, $productoId, $motivo));
    }

    /**
     * Resumen comparable de un kardex: saldo, costo promedio y última compra al
     * cierre de CADA día. Es lo que leen el balance y los reportes, así que dos
     * kardex con la misma firma valen lo mismo para el negocio aunque difieran en
     * la granularidad de sus filas (p. ej. dos ítems del mismo producto en una venta).
     */
    public static function firma(array $filas): string
    {
        $porDia       = [];
        $ultimaCompra = 0.0;

        foreach ($filas as $f) {
            if (in_array($f['tipo'], ['inventario_inicial', 'entrada', 'transferencia_recepcion'], true)
                && (float) $f['costo_unitario'] > 0) {
                $ultimaCompra = (float) $f['costo_unitario'];
            }

            $porDia[substr((string) $f['fecha'], 0, 10)] = sprintf(
                '%.4f|%.4f|%.4f',
                (float) $f['saldo_cantidad'],
                (float) $f['costo_promedio'],
                $ultimaCompra,
            );
        }

        return md5(json_encode($porDia));
    }

    /**
     * Todos los movimientos de un (almacén, producto) desde sus documentos, en
     * orden cronológico estable.
     */
    public function movimientos(object $almacen, int $productoId): array
    {
        $almacenId = (int) $almacen->id;
        $movs = [];

        // Inventario inicial (apertura): primera línea del kardex; solo cuentan los
        // movimientos POSTERIORES a su fecha de corte. Sin apertura, cuenta todo.
        $apertura = DB::table('stock_iniciales')
            ->where('almacen_id', $almacenId)->where('producto_id', $productoId)->first();
        $corte = $apertura ? substr((string) $apertura->fecha, 0, 10) . ' 23:59:59' : null;
        $post  = fn ($q, string|Expression $col) => $corte ? $q->where($col, '>', $corte) : $q;

        if ($apertura) {
            $movs[] = $this->mov(substr((string) $apertura->fecha, 0, 10), 0,
                'inventario_inicial', 'stock_inicial', (int) $apertura->id,
                (float) $apertura->cantidad, (float) $apertura->costo, true, null, null);
        }

        // (+) Entradas confirmadas: recalculan el costo promedio con su precio.
        foreach ($post(DB::table('entradas_detalle as ed')
            ->join('entradas as e', 'e.id', '=', 'ed.entrada_id')
            ->where('e.almacen_id', $almacenId)
            ->where('e.estado', 'confirmado')
            ->where('ed.producto_id', $productoId), 'e.fecha')
            ->get(['ed.cantidad_base', 'ed.precio_costo', 'e.id', 'e.fecha', 'e.numero_documento', 'e.user_id']) as $r) {
            $movs[] = $this->mov($r->fecha, (int) $r->id, 'entrada', 'entrada', (int) $r->id,
                (float) $r->cantidad_base, (float) $r->precio_costo, true, $r->numero_documento, $r->user_id);
        }

        // (-) Salidas confirmadas.
        foreach ($post(DB::table('salidas_detalle as sd')
            ->join('salidas as s', 's.id', '=', 'sd.salida_id')
            ->where('s.almacen_id', $almacenId)
            ->where('s.estado', 'confirmado')
            ->where('sd.producto_id', $productoId), 's.fecha')
            ->get(['sd.cantidad_base', 's.id', 's.fecha', 's.numero_documento', 's.user_id']) as $r) {
            $movs[] = $this->mov($r->fecha, (int) $r->id, 'salida', 'salida', (int) $r->id,
                -1 * (float) $r->cantidad_base, 0, false, $r->numero_documento, $r->user_id);
        }

        // (±) Ajustes de inventario confirmados: sin costo, entran/salen al vigente.
        foreach ($post(DB::table('ajustes_inventario as ai')
            ->where('ai.almacen_id', $almacenId)
            ->where('ai.estado', 'confirmado')
            ->where('ai.producto_id', $productoId), 'ai.fecha')
            ->get(['ai.id', 'ai.fecha', 'ai.tipo', 'ai.cantidad_base', 'ai.numero', 'ai.user_id']) as $r) {
            $esIngreso = $r->tipo === 'ingreso';
            $movs[] = $this->mov($r->fecha, (int) $r->id,
                $esIngreso ? 'ajuste_ingreso' : 'ajuste_salida', 'ajuste', (int) $r->id,
                ($esIngreso ? 1 : -1) * (float) $r->cantidad_base, 0, false, $r->numero, $r->user_id);
        }

        // (-) Transferencias salientes (enviada o recibida).
        foreach ($post(DB::table('transferencias_detalle as td')
            ->join('transferencias as t', 't.id', '=', 'td.transferencia_id')
            ->where('t.almacen_origen_id', $almacenId)
            ->whereIn('t.estado', ['enviada', 'recibida'])
            ->where('td.producto_id', $productoId), 't.fecha_envio')
            ->get(['td.cantidad_base_enviada', 't.id', 't.fecha_envio', 't.user_envio_id']) as $r) {
            $movs[] = $this->mov($r->fecha_envio, (int) $r->id, 'transferencia_envio', 'transferencia', (int) $r->id,
                -1 * (float) $r->cantidad_base_enviada, 0, false, null, $r->user_envio_id);
        }

        // (+) Transferencias entrantes recibidas: recalculan costo con su costo_unitario.
        foreach ($post(DB::table('transferencias_detalle as td')
            ->join('transferencias as t', 't.id', '=', 'td.transferencia_id')
            ->where('t.almacen_destino_id', $almacenId)
            ->where('t.estado', 'recibida')
            ->where('td.producto_id', $productoId), 't.fecha_recepcion')
            ->get(['td.cantidad_base_recibida', 'td.costo_unitario', 't.id', 't.fecha_recepcion', 't.user_recepcion_id']) as $r) {
            $movs[] = $this->mov($r->fecha_recepcion, (int) $r->id, 'transferencia_recepcion', 'transferencia', (int) $r->id,
                (float) $r->cantidad_base_recibida, (float) $r->costo_unitario, true, null, $r->user_recepcion_id);
        }

        // (-) Ventas y entregas: solo en el almacén tipo 'local' del local de la venta,
        // y solo si el producto descuenta stock al vender (servicios o
        // controla_stock=false nunca salieron del inventario).
        $productoModel  = Producto::find($productoId);
        $localModel     = $almacen->local_id ? Local::find($almacen->local_id) : null;
        $ventaDescuenta = $productoModel === null
            || app(ConfiguracionOperacionService::class)->deboDescontarStock($productoModel, $localModel);

        if ($almacen->tipo === 'local' && $almacen->local_id && $ventaDescuenta) {
            // Modelo FÍSICO: la venta saca lo que el cliente se llevó en el momento; lo
            // que quedó pendiente sale en cada ENTREGA. Por eso un pedido migrado cuya
            // venta no tiene ítems igual descuenta sus entregas (el motor viejo de la
            // tabla stock las ignoraba y dejaba unidades fantasma).
            $pendientePorVenta = DB::table('cliente_anticipo_items as ci')
                ->join('cliente_anticipos as an', 'an.id', '=', 'ci.cliente_anticipo_id')
                ->whereNotNull('an.venta_id')
                // Un anticipo ANULADO conserva la cantidad de sus ítems: contarlo
                // subestimaría lo que salió al vender y dejaría stock fantasma.
                ->where('an.estado', '<>', 'anulado')
                ->where('ci.producto_id', $productoId)
                ->selectRaw('an.venta_id, SUM(ci.cantidad * ci.factor_conversion) as t')
                ->groupBy('an.venta_id')
                ->pluck('t', 'venta_id');

            foreach ($post(DB::table('venta_items as vi')
                ->join('ventas as v', 'v.id', '=', 'vi.venta_id')
                ->where('v.local_id', $almacen->local_id)
                ->where('v.empresa_id', $almacen->empresa_id)
                ->where('v.estado', 'completada')
                ->where('vi.producto_id', $productoId), 'v.fecha_venta')
                ->selectRaw('v.id, v.fecha_venta, v.user_id, SUM(vi.cantidad_base) as base')
                ->groupBy('v.id', 'v.fecha_venta', 'v.user_id')
                ->get() as $r) {
                $salioAlVender = (float) $r->base - (float) ($pendientePorVenta[$r->id] ?? 0);
                if (abs($salioAlVender) < 0.0001) continue; // todo quedó pendiente
                $movs[] = $this->mov($r->fecha_venta, (int) $r->id, 'venta', 'venta', (int) $r->id,
                    -1 * $salioAlVender, 0, false, null, $r->user_id);
            }

            // (-) Entregas de pendientes: el stock sale al ENTREGAR. Las entregas
            // anuladas se borran (AnticipoClienteController), así que no hace falta
            // filtrarlas aquí.
            foreach ($post(DB::table('cliente_anticipo_aplicacion_items as cai')
                ->join('cliente_anticipo_aplicaciones as ca', 'ca.id', '=', 'cai.cliente_anticipo_aplicacion_id')
                ->join('cliente_anticipo_items as ci', 'ci.id', '=', 'cai.cliente_anticipo_item_id')
                ->join('cliente_anticipos as an', 'an.id', '=', 'ca.cliente_anticipo_id')
                ->join('ventas as v', 'v.id', '=', 'an.venta_id')
                ->where('v.local_id', $almacen->local_id)
                ->where('v.empresa_id', $almacen->empresa_id)
                ->where('an.estado', '<>', 'anulado')
                ->where('ci.producto_id', $productoId), 'ca.fecha')
                ->get(['cai.cantidad', 'ci.factor_conversion', 'ca.id as aplicacion_id',
                       'ca.fecha', 'ca.numero', 'ca.user_id', 'an.venta_id']) as $r) {
                $movs[] = $this->mov($r->fecha, (int) $r->aplicacion_id, 'entrega_pendiente', 'venta', (int) $r->venta_id,
                    -1 * (float) $r->cantidad * (float) $r->factor_conversion, 0, false, $r->numero, $r->user_id);
            }
        }

        // (+) Devoluciones completadas con restock: fechadas por la fecha del
        // DOCUMENTO (antes el kardex usaba created_at y la tabla stock usaba fecha,
        // así una misma devolución podía caer en días distintos según el motor).
        if ($almacen->tipo === 'local' && $almacen->local_id) {
            $fechaDevolucion = DB::raw('COALESCE(d.fecha, d.created_at)');
            foreach ($post(DB::table('devoluciones_detalle as dd')
                ->join('devoluciones as d', 'd.id', '=', 'dd.devolucion_id')
                ->where('d.local_id', $almacen->local_id)
                ->where('d.empresa_id', $almacen->empresa_id)
                ->where('d.estado', 'completada')
                ->where('dd.restock', true)
                ->where('dd.producto_id', $productoId), $fechaDevolucion)
                ->selectRaw('dd.cantidad_base, d.id, COALESCE(d.fecha, d.created_at) as fecha')
                ->get() as $r) {
                $movs[] = $this->mov($r->fecha, (int) $r->id, 'devolucion', 'devolucion', (int) $r->id,
                    (float) $r->cantidad_base, 0, false, null, null);
            }
        }

        // (±) Cierres de inventario confirmados: aplican la diferencia declarada.
        foreach ($post(DB::table('cierres_inventario_items as ci')
            ->join('cierres_inventario as c', 'c.id', '=', 'ci.cierre_id')
            ->where('c.almacen_id', $almacenId)
            ->where('c.estado', 'confirmado')
            ->where('ci.producto_id', $productoId), 'c.fecha')
            ->get(['ci.diferencia', 'c.id', 'c.fecha', 'c.user_id']) as $r) {
            $movs[] = $this->mov($r->fecha, (int) $r->id, 'cierre', 'cierre', (int) $r->id,
                (float) $r->diferencia, 0, false, null, $r->user_id);
        }

        usort($movs, function ($a, $b) {
            $fa = $a['fecha'] ?? '0000-00-00';
            $fb = $b['fecha'] ?? '0000-00-00';

            return $fa <=> $fb ?: ($a['orden'] <=> $b['orden']);
        });

        return $movs;
    }

    /**
     * Reproduce los movimientos en orden y devuelve las filas del kardex.
     *
     * FÓRMULA ÚNICA del costo promedio ponderado (la misma de Stock::ajustar):
     *  - Solo los ingresos con costo real (inventario inicial, compra, recepción
     *    de transferencia) recalculan el promedio. Salidas, ventas, devoluciones,
     *    ajustes y cierres entran/salen al costo vigente.
     *  - Un saldo previo NEGATIVO no participa del promedio: no es inventario real.
     *    Sin esto, vender 2,290 antes de registrar una compra de 2,291 dividía el
     *    total de la compra entre 1 unidad (así nació el ladrillo a S/ 2,268).
     *  - Costo y saldo se redondean a 4 decimales en CADA paso, igual que la tabla
     *    stock, para que el cálculo en vivo y el reconstruido den idéntico.
     */
    public function reproducir(object $almacen, int $productoId, array $movs): array
    {
        $cant  = 0.0;
        $cpp   = 0.0;
        $filas = [];
        $ahora = now();

        foreach ($movs as $m) {
            $q     = (float) $m['cantidad'];
            $costo = (float) $m['costo'];

            if ($m['recalc'] && $q > 0 && $costo > 0) {
                $base  = max($cant, 0.0);
                $nueva = $base + $q;
                $cpp   = round($nueva > 0 ? (($base * $cpp) + ($q * $costo)) / $nueva : 0.0, 4);
                $costoUnitario = $costo;
            } else {
                $costoUnitario = $cpp;
            }
            $cant = round($cant + $q, 4);

            $filas[] = [
                'empresa_id'       => $almacen->empresa_id,
                'almacen_id'       => $almacen->id,
                'producto_id'      => $productoId,
                'fecha'            => $m['fecha'] ?? $ahora,
                'tipo'             => $m['tipo'],
                'referencia_tipo'  => $m['ref_tipo'],
                'referencia_id'    => $m['ref_id'],
                'documento'        => $m['doc'],
                'cantidad'         => round($q, 4),
                'costo_unitario'   => round($costoUnitario, 4),
                'costo_promedio'   => $cpp,
                'saldo_cantidad'   => $cant,
                'saldo_valorizado' => round($cant * $cpp, 4),
                'user_id'          => $m['user_id'],
                'created_at'       => $ahora,
                'updated_at'       => $ahora,
            ];
        }

        return $filas;
    }

    private function mov($fecha, int $orden, string $tipo, string $refTipo, int $refId,
                         float $cantidad, float $costo, bool $recalc, ?string $doc, $userId): array
    {
        return [
            'fecha'    => $fecha ? (string) $fecha : null,
            'orden'    => $orden,
            'tipo'     => $tipo,
            'ref_tipo' => $refTipo,
            'ref_id'   => $refId,
            'cantidad' => $cantidad,
            'costo'    => $costo,
            'recalc'   => $recalc,
            'doc'      => $doc,
            'user_id'  => $userId,
        ];
    }
}
