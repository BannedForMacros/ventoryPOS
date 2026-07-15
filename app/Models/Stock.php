<?php

namespace App\Models;

use App\Exceptions\InsufficientStockException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Stock extends Model
{
    protected $table = 'stock';

    protected $fillable = [
        'almacen_id',
        'producto_id',
        'cantidad',
        'costo_promedio',
    ];

    protected function casts(): array
    {
        return [
            'cantidad'       => 'decimal:4',
            'costo_promedio' => 'decimal:4',
        ];
    }

    public function almacen(): BelongsTo
    {
        return $this->belongsTo(Almacen::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    /**
     * Stocks con saldo negativo: indican una inconsistencia que el admin debe
     * atender (venta sin transferencia previa, reversión de devolución que
     * comio inventario que ya no estaba, etc.). El frontend usa este scope
     * para listarlos y mostrar el banner de "alerta de stock".
     */
    public function scopeNegativo(Builder $q): Builder
    {
        return $q->where('cantidad', '<', 0);
    }

    // ── Logica central de stock ──────────────────────────────────────────────
    //
    // Este es el UNICO metodo que debe modificar el stock.
    // Nunca actualices cantidad/costo_promedio directamente desde un controller.
    //
    // Garantias de concurrencia:
    //   1. INSERT ... ON CONFLICT DO NOTHING asegura que el row exista sin race
    //      (depende del UNIQUE (almacen_id, producto_id) que ya existe en BD).
    //   2. SELECT ... FOR UPDATE bloquea la fila para todas las demas transacciones
    //      hasta que la nuestra haga commit. Dos ventas simultaneas del mismo producto
    //      se serializan: la segunda espera a que la primera termine y lee el valor
    //      actualizado.
    //   3. En salidas validamos disponibilidad ANTES de descontar y lanzamos
    //      InsufficientStockException si no alcanza, en vez de recortar a 0.
    //      La excepcion revierte la transaccion completa de la venta/transferencia.
    //
    // Parametros:
    //   $cantidadBase    positivo = entrada, negativo = salida
    //   $costoNuevo      solo se usa en entradas (cantidadBase > 0) para
    //                    recalcular el costo promedio ponderado. En salidas se ignora.
    //   $permitirNegativo  si true, permite stock < 0 (no recomendado; util para
    //                    operaciones administrativas como ajustes manuales).
    //                    Por defecto false: una salida que excede dispara excepcion.
    //
    // IMPORTANTE: este metodo abre su propia transaccion. Si lo llamas dentro
    // de una transaccion mayor (ej. VentaService) la transaccion exterior gana
    // (Laravel anida transacciones via savepoints).

    public static function ajustar(
        int   $almacenId,
        int   $productoId,
        float $cantidadBase,
        float $costoNuevo = 0,
        bool  $permitirNegativo = false,
        ?array $contexto = null,
    ): self {
        // Blindaje de almacén: NINGÚN movimiento de stock puede ocurrir sin un
        // almacén válido. Todo el inventario está siempre ligado a un almacén.
        if ($almacenId <= 0) {
            throw new \InvalidArgumentException(
                'Stock::ajustar() requiere un almacen_id válido (> 0). El stock siempre pertenece a un almacén.'
            );
        }

        $stock = DB::transaction(function () use ($almacenId, $productoId, $cantidadBase, $costoNuevo, $permitirNegativo) {
            // 1) Asegurar que la fila exista. Idempotente y atomico gracias al
            //    UNIQUE (almacen_id, producto_id). Si dos transacciones la crean
            //    simultaneamente, una gana y la otra recibe DO NOTHING (sin error).
            DB::statement(
                'INSERT INTO stock (almacen_id, producto_id, cantidad, costo_promedio, created_at, updated_at)
                 VALUES (?, ?, 0, 0, NOW(), NOW())
                 ON CONFLICT (almacen_id, producto_id) DO NOTHING',
                [$almacenId, $productoId]
            );

            // 2) Bloquear la fila. Cualquier otro ajustar() concurrente sobre
            //    el mismo (almacen, producto) espera aqui hasta que commiteemos.
            $stock = self::where('almacen_id', $almacenId)
                ->where('producto_id', $productoId)
                ->lockForUpdate()
                ->firstOrFail();

            $cantidadActual = (float) $stock->cantidad;

            if ($cantidadBase > 0) {
                $nuevaCantidad = $cantidadActual + $cantidadBase;

                // COSTEO CANÓNICO (promedio ponderado):
                // SOLO los ingresos con costo real recalculan el CPP — es decir
                // compras (entradas) y recepciones de transferencia, que llegan
                // con `costoNuevo > 0`. Los reingresos SIN costo propio
                // (devolución con restock, sobrante de cierre de inventario)
                // entran al COSTO VIGENTE y NO diluyen el promedio.
                // Así el costo unitario es consistente y auditable, y coincide
                // con Stock::reconstruir() y con `kardex:reconstruir`.
                if ($costoNuevo > 0) {
                    $costoActual = (float) $stock->costo_promedio;
                    $stock->costo_promedio = round(
                        $nuevaCantidad > 0
                            ? (($cantidadActual * $costoActual) + ($cantidadBase * $costoNuevo)) / $nuevaCantidad
                            : 0,
                        4
                    );
                }
                // costoNuevo == 0 → se conserva costo_promedio (reingreso al costo vigente).

                $stock->cantidad = $nuevaCantidad;
            } else {
                // Salida: validar disponibilidad antes de descontar
                $solicitado = abs($cantidadBase);

                if (!$permitirNegativo && $solicitado > $cantidadActual + 0.0001) {
                    $producto = Producto::find($productoId);
                    throw new InsufficientStockException(
                        almacenId:      $almacenId,
                        productoId:     $productoId,
                        disponible:     $cantidadActual,
                        solicitado:     $solicitado,
                        productoNombre: $producto?->nombre,
                    );
                }

                $stock->cantidad = $cantidadActual + $cantidadBase; // suma con cantidadBase negativo
            }

            $stock->save();

            return $stock;
        });

        // Traza el movimiento en el kardex. Se hace DESPUÉS del commit y a
        // prueba de fallos: si algo fallara al registrar el kardex, el
        // movimiento de stock YA está guardado y NO se revierte. El kardex
        // siempre puede regenerarse con `php artisan kardex:reconstruir`.
        self::trazarEnKardex($stock, $almacenId, $productoId, $cantidadBase, $costoNuevo, $contexto);

        return $stock;
    }

    /**
     * Registra una fila en el ledger de kardex (movimientos_inventario).
     * Post-commit y no bloqueante: jamás rompe la venta/entrada/etc.
     */
    private static array $empresaAlmacenCache = [];

    private static function trazarEnKardex(
        self $stock,
        int $almacenId,
        int $productoId,
        float $cantidadBase,
        float $costoNuevo,
        ?array $contexto,
    ): void {
        // Valores YA calculados por el ajuste; se capturan como escalares.
        $saldoCantidad = (float) $stock->cantidad;
        $cpp           = (float) $stock->costo_promedio;
        $costoUnitario = $cantidadBase > 0
            ? ($costoNuevo > 0 ? $costoNuevo : $cpp)  // entrada: costo del ingreso
            : $cpp;                                    // salida: sale al CPP vigente

        $ctx = $contexto ?? [];

        if (!array_key_exists($almacenId, self::$empresaAlmacenCache)) {
            self::$empresaAlmacenCache[$almacenId] =
                DB::table('almacenes')->where('id', $almacenId)->value('empresa_id');
        }

        $fila = [
            'empresa_id'       => $ctx['empresa_id'] ?? self::$empresaAlmacenCache[$almacenId],
            'almacen_id'       => $almacenId,
            'producto_id'      => $productoId,
            'fecha'            => $ctx['fecha'] ?? now(),
            'tipo'             => $ctx['tipo'] ?? 'ajuste',
            'referencia_tipo'  => $ctx['referencia_tipo'] ?? null,
            'referencia_id'    => $ctx['referencia_id'] ?? null,
            'documento'        => $ctx['documento'] ?? null,
            'cantidad'         => round($cantidadBase, 4),
            'costo_unitario'   => round($costoUnitario, 4),
            'costo_promedio'   => round($cpp, 4),
            'saldo_cantidad'   => round($saldoCantidad, 4),
            'saldo_valorizado' => round($saldoCantidad * $cpp, 4),
            'user_id'          => $ctx['user_id'] ?? optional(auth()->user())->id,
        ];

        DB::afterCommit(function () use ($fila) {
            try {
                MovimientoInventario::create($fila);
            } catch (\Throwable $e) {
                Log::error('No se pudo registrar el movimiento en el kardex.', [
                    'error'       => $e->getMessage(),
                    'almacen_id'  => $fila['almacen_id'],
                    'producto_id' => $fila['producto_id'],
                    'tipo'        => $fila['tipo'],
                ]);
            }
        });
    }

    /**
     * Reconstruye el stock (cantidad + costo promedio) de UN par (almacén, producto)
     * sumando todos los movimientos confirmados del sistema. Pensado para el botón
     * "Recalcular stock" del panel admin: corrige discrepancias sin perder información.
     *
     * Fuentes de movimientos consideradas:
     *   (+) Entradas en estado 'confirmado'.
     *   (-) Salidas en estado 'confirmado'.
     *   (-) Transferencias salientes (origen) en estado 'enviada' o 'recibida'.
     *   (+) Transferencias entrantes (destino) en estado 'recibida' (toma cantidad_base_recibida).
     *   (-) Ventas en estado 'completada' del local cuyo almacén tipo='local' coincide.
     *   (+) Devoluciones en estado 'completada' con restock=true sobre ese mismo almacén.
     *   (+/-) Cierres de inventario confirmados: aplica la diferencia (declarado - sistema).
     *
     * El costo promedio se reconstruye a partir de las entradas confirmadas en orden
     * cronológico (las salidas/ventas no afectan el CPP, ya está bien).
     */
    public static function reconstruir(int $almacenId, int $productoId): self
    {
        $stock = self::firstOrCreate(
            ['almacen_id' => $almacenId, 'producto_id' => $productoId],
            ['cantidad' => 0, 'costo_promedio' => 0]
        );

        // Inventario inicial (apertura): si existe, la reconstrucción ARRANCA de
        // ese saldo/costo y solo suma los movimientos POSTERIORES a la fecha de
        // corte. Sin apertura, arranca en 0 y cuenta TODO (comportamiento previo).
        $apertura = \DB::table('stock_iniciales')
            ->where('almacen_id', $almacenId)
            ->where('producto_id', $productoId)
            ->first();
        $corte = $apertura ? substr((string) $apertura->fecha, 0, 10) . ' 23:59:59' : null;
        // Aplica el filtro "solo movimientos posteriores al corte" a una consulta.
        $post = fn ($q, string $col) => $corte ? $q->where($col, '>', $corte) : $q;

        // ── Cantidad: arranca del inicial y suma/resta las fuentes ──────────
        $cantidad = $apertura ? (float) $apertura->cantidad : 0.0;

        // Entradas confirmadas (+)
        $cantidad += (float) $post(\DB::table('entradas_detalle as ed')
            ->join('entradas as e', 'e.id', '=', 'ed.entrada_id')
            ->where('e.almacen_id', $almacenId)
            ->where('e.estado', 'confirmado')
            ->where('ed.producto_id', $productoId), 'e.fecha')
            ->sum('ed.cantidad_base');

        // Salidas confirmadas (-)
        $cantidad -= (float) $post(\DB::table('salidas_detalle as sd')
            ->join('salidas as s', 's.id', '=', 'sd.salida_id')
            ->where('s.almacen_id', $almacenId)
            ->where('s.estado', 'confirmado')
            ->where('sd.producto_id', $productoId), 's.fecha')
            ->sum('sd.cantidad_base');

        // Transferencias salientes (-): cuando ya se envió, el stock origen bajó
        $cantidad -= (float) $post(\DB::table('transferencias_detalle as td')
            ->join('transferencias as t', 't.id', '=', 'td.transferencia_id')
            ->where('t.almacen_origen_id', $almacenId)
            ->whereIn('t.estado', ['enviada', 'recibida'])
            ->where('td.producto_id', $productoId), 't.fecha_envio')
            ->sum('td.cantidad_base_enviada');

        // Transferencias entrantes (+): solo cuando ya fue recibida
        $cantidad += (float) $post(\DB::table('transferencias_detalle as td')
            ->join('transferencias as t', 't.id', '=', 'td.transferencia_id')
            ->where('t.almacen_destino_id', $almacenId)
            ->where('t.estado', 'recibida')
            ->where('td.producto_id', $productoId), 't.fecha_recepcion')
            ->sum('td.cantidad_base_recibida');

        // Ventas completadas (-): se descuentan del almacén tipo='local' del local de la venta
        $cantidad -= (float) $post(\DB::table('venta_items as vi')
            ->join('ventas as v', 'v.id', '=', 'vi.venta_id')
            ->join('almacenes as a', function ($j) {
                $j->on('a.local_id', '=', 'v.local_id')
                  ->on('a.empresa_id', '=', 'v.empresa_id')
                  ->where('a.tipo', '=', 'local')
                  ->where('a.activo', '=', true);
            })
            ->where('a.id', $almacenId)
            ->where('v.estado', 'completada')
            ->where('vi.producto_id', $productoId), 'v.fecha_venta')
            ->sum('vi.cantidad_base');

        // Devoluciones completadas con restock=true (+) en almacén tipo='local' del local
        $cantidad += (float) $post(\DB::table('devoluciones_detalle as dd')
            ->join('devoluciones as d', 'd.id', '=', 'dd.devolucion_id')
            ->join('almacenes as a', function ($j) {
                $j->on('a.local_id', '=', 'd.local_id')
                  ->on('a.empresa_id', '=', 'd.empresa_id')
                  ->where('a.tipo', '=', 'local')
                  ->where('a.activo', '=', true);
            })
            ->where('a.id', $almacenId)
            ->where('d.estado', 'completada')
            ->where('dd.restock', true)
            ->where('dd.producto_id', $productoId), 'd.fecha')
            ->sum('dd.cantidad_base');

        // Cierres de inventario confirmados (+/-): aplican la diferencia declarada
        $cantidad += (float) $post(\DB::table('cierres_inventario_items as ci')
            ->join('cierres_inventario as c', 'c.id', '=', 'ci.cierre_id')
            ->where('c.almacen_id', $almacenId)
            ->where('c.estado', 'confirmado')
            ->where('ci.producto_id', $productoId), 'c.fecha')
            ->sum('ci.diferencia');

        // ── Costo promedio CANÓNICO ─────────────────────────────────────────
        // Se reconstruye desde los INGRESOS CON COSTO REAL en orden cronológico:
        //   (semilla) inventario inicial      → costo = stock_iniciales.costo
        //   (+) entradas confirmadas          → costo = precio_costo
        //   (+) recepciones de transferencia  → costo = costo_unitario
        // Las salidas, ventas, devoluciones y cierres NO alteran el CPP (mismo
        // criterio que Stock::ajustar() y `kardex:reconstruir`).
        $ingresos = collect();

        foreach ($post(\DB::table('entradas_detalle as ed')
            ->join('entradas as e', 'e.id', '=', 'ed.entrada_id')
            ->where('e.almacen_id', $almacenId)
            ->where('e.estado', 'confirmado')
            ->where('ed.producto_id', $productoId), 'e.fecha')
            ->get(['ed.cantidad_base', 'ed.precio_costo', 'e.fecha', 'e.id']) as $r) {
            $ingresos->push([
                'orden'    => (string) $r->fecha . '|' . str_pad((string) $r->id, 12, '0', STR_PAD_LEFT),
                'cantidad' => (float) $r->cantidad_base,
                'costo'    => (float) $r->precio_costo,
            ]);
        }

        foreach ($post(\DB::table('transferencias_detalle as td')
            ->join('transferencias as t', 't.id', '=', 'td.transferencia_id')
            ->where('t.almacen_destino_id', $almacenId)
            ->where('t.estado', 'recibida')
            ->where('td.producto_id', $productoId), 't.fecha_recepcion')
            ->get(['td.cantidad_base_recibida', 'td.costo_unitario', 't.fecha_recepcion', 't.id']) as $r) {
            $ingresos->push([
                'orden'    => (string) $r->fecha_recepcion . '|' . str_pad((string) $r->id, 12, '0', STR_PAD_LEFT),
                'cantidad' => (float) $r->cantidad_base_recibida,
                'costo'    => (float) $r->costo_unitario,
            ]);
        }

        // Semilla del CPP: el inventario inicial es el primer "ingreso con costo".
        $cantCpp = $apertura ? (float) $apertura->cantidad : 0.0;
        $costo   = $apertura ? (float) $apertura->costo : 0.0;
        foreach ($ingresos->sortBy('orden')->values() as $ing) {
            // Ingreso sin costo propio: no altera el promedio (entra al costo vigente).
            if ($ing['costo'] <= 0) { $cantCpp += $ing['cantidad']; continue; }
            $nuevaCantidad = $cantCpp + $ing['cantidad'];
            $costo = $nuevaCantidad > 0
                ? (($cantCpp * $costo) + ($ing['cantidad'] * $ing['costo'])) / $nuevaCantidad
                : 0;
            $cantCpp = $nuevaCantidad;
        }

        // Permitimos cantidades negativas: si los movimientos confirmados arrojan
        // saldo negativo significa que hubo una merma/error real que el admin
        // debe atender (caso típico: venta en local con stock que nunca recibió
        // transferencia). Truncar a 0 ocultaba la inconsistencia. El frontend
        // pinta los negativos en rojo y la lista los expone via scopeNegativo().
        $stock->cantidad       = round($cantidad, 4);
        $stock->costo_promedio = round($costo, 4);
        $stock->save();

        return $stock;
    }

    /**
     * Devuelve los pares (almacen_id, producto_id) que tienen al menos un movimiento
     * en cualquiera de las tablas de movimientos. Necesario para que "recalcular"
     * cubra inventario que llegó por transferencia o cierre, no solo por entrada.
     */
    public static function combinacionesConMovimientos(array $almacenIds): \Illuminate\Support\Collection
    {
        if (empty($almacenIds)) return collect();

        $pares = collect();

        // Entradas
        $pares = $pares->merge(\DB::table('entradas_detalle as ed')
            ->join('entradas as e', 'e.id', '=', 'ed.entrada_id')
            ->whereIn('e.almacen_id', $almacenIds)
            ->select('e.almacen_id', 'ed.producto_id')
            ->distinct()->get());

        // Salidas
        $pares = $pares->merge(\DB::table('salidas_detalle as sd')
            ->join('salidas as s', 's.id', '=', 'sd.salida_id')
            ->whereIn('s.almacen_id', $almacenIds)
            ->select('s.almacen_id', 'sd.producto_id')
            ->distinct()->get());

        // Transferencias (origen y destino)
        $pares = $pares->merge(\DB::table('transferencias_detalle as td')
            ->join('transferencias as t', 't.id', '=', 'td.transferencia_id')
            ->whereIn('t.almacen_origen_id', $almacenIds)
            ->select(\DB::raw('t.almacen_origen_id as almacen_id'), 'td.producto_id')
            ->distinct()->get());

        $pares = $pares->merge(\DB::table('transferencias_detalle as td')
            ->join('transferencias as t', 't.id', '=', 'td.transferencia_id')
            ->whereIn('t.almacen_destino_id', $almacenIds)
            ->select(\DB::raw('t.almacen_destino_id as almacen_id'), 'td.producto_id')
            ->distinct()->get());

        // Ventas: producto_id × almacén-de-ventas resuelto por local
        $pares = $pares->merge(\DB::table('venta_items as vi')
            ->join('ventas as v', 'v.id', '=', 'vi.venta_id')
            ->join('almacenes as a', function ($j) {
                $j->on('a.local_id', '=', 'v.local_id')
                  ->on('a.empresa_id', '=', 'v.empresa_id')
                  ->where('a.tipo', '=', 'local');
            })
            ->whereIn('a.id', $almacenIds)
            ->select(\DB::raw('a.id as almacen_id'), 'vi.producto_id')
            ->distinct()->get());

        // Cierres
        $pares = $pares->merge(\DB::table('cierres_inventario_items as ci')
            ->join('cierres_inventario as c', 'c.id', '=', 'ci.cierre_id')
            ->whereIn('c.almacen_id', $almacenIds)
            ->select('c.almacen_id', 'ci.producto_id')
            ->distinct()->get());

        // Inventario inicial (apertura): DEBE incluirse aunque el producto no tenga
        // movimientos posteriores, o "Recalcular stock" lo dejaría en 0.
        $pares = $pares->merge(\DB::table('stock_iniciales')
            ->whereIn('almacen_id', $almacenIds)
            ->select('almacen_id', 'producto_id')
            ->distinct()->get());

        return $pares
            ->map(fn ($r) => ['almacen_id' => $r->almacen_id, 'producto_id' => $r->producto_id])
            ->unique(fn ($r) => "{$r['almacen_id']}-{$r['producto_id']}")
            ->values();
    }
}
