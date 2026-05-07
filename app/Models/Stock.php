<?php

namespace App\Models;

use App\Exceptions\InsufficientStockException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

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
    ): self {
        return DB::transaction(function () use ($almacenId, $productoId, $cantidadBase, $costoNuevo, $permitirNegativo) {
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
                // Entrada: recalcular costo promedio ponderado
                // CPP = (stock_actual * costo_actual + cantidad_nueva * costo_nuevo)
                //       / (stock_actual + cantidad_nueva)
                $costoActual    = (float) $stock->costo_promedio;
                $nuevaCantidad  = $cantidadActual + $cantidadBase;
                $nuevoCosto     = $nuevaCantidad > 0
                    ? (($cantidadActual * $costoActual) + ($cantidadBase * $costoNuevo)) / $nuevaCantidad
                    : 0;

                $stock->cantidad       = $nuevaCantidad;
                $stock->costo_promedio = round($nuevoCosto, 4);
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

        // ── Cantidad: sumar/restar todas las fuentes ────────────────────────
        $cantidad = 0.0;

        // Entradas confirmadas (+)
        $cantidad += (float) \DB::table('entradas_detalle as ed')
            ->join('entradas as e', 'e.id', '=', 'ed.entrada_id')
            ->where('e.almacen_id', $almacenId)
            ->where('e.estado', 'confirmado')
            ->where('ed.producto_id', $productoId)
            ->sum('ed.cantidad_base');

        // Salidas confirmadas (-)
        $cantidad -= (float) \DB::table('salidas_detalle as sd')
            ->join('salidas as s', 's.id', '=', 'sd.salida_id')
            ->where('s.almacen_id', $almacenId)
            ->where('s.estado', 'confirmado')
            ->where('sd.producto_id', $productoId)
            ->sum('sd.cantidad_base');

        // Transferencias salientes (-): cuando ya se envió, el stock origen bajó
        $cantidad -= (float) \DB::table('transferencias_detalle as td')
            ->join('transferencias as t', 't.id', '=', 'td.transferencia_id')
            ->where('t.almacen_origen_id', $almacenId)
            ->whereIn('t.estado', ['enviada', 'recibida'])
            ->where('td.producto_id', $productoId)
            ->sum('td.cantidad_base_enviada');

        // Transferencias entrantes (+): solo cuando ya fue recibida
        $cantidad += (float) \DB::table('transferencias_detalle as td')
            ->join('transferencias as t', 't.id', '=', 'td.transferencia_id')
            ->where('t.almacen_destino_id', $almacenId)
            ->where('t.estado', 'recibida')
            ->where('td.producto_id', $productoId)
            ->sum('td.cantidad_base_recibida');

        // Ventas completadas (-): se descuentan del almacén tipo='local' del local de la venta
        $cantidad -= (float) \DB::table('venta_items as vi')
            ->join('ventas as v', 'v.id', '=', 'vi.venta_id')
            ->join('almacenes as a', function ($j) {
                $j->on('a.local_id', '=', 'v.local_id')
                  ->on('a.empresa_id', '=', 'v.empresa_id')
                  ->where('a.tipo', '=', 'local')
                  ->where('a.activo', '=', true);
            })
            ->where('a.id', $almacenId)
            ->where('v.estado', 'completada')
            ->where('vi.producto_id', $productoId)
            ->sum('vi.cantidad_base');

        // Devoluciones completadas con restock=true (+) en almacén tipo='local' del local
        $cantidad += (float) \DB::table('devoluciones_detalle as dd')
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
            ->where('dd.producto_id', $productoId)
            ->sum('dd.cantidad_base');

        // Cierres de inventario confirmados (+/-): aplican la diferencia declarada
        $cantidad += (float) \DB::table('cierres_inventario_items as ci')
            ->join('cierres_inventario as c', 'c.id', '=', 'ci.cierre_id')
            ->where('c.almacen_id', $almacenId)
            ->where('c.estado', 'confirmado')
            ->where('ci.producto_id', $productoId)
            ->sum('ci.diferencia');

        // ── Costo promedio: reconstruir desde entradas confirmadas en orden ─
        $detalles = EntradaDetalle::query()
            ->join('entradas', 'entradas_detalle.entrada_id', '=', 'entradas.id')
            ->where('entradas.almacen_id', $almacenId)
            ->where('entradas.estado', 'confirmado')
            ->where('entradas_detalle.producto_id', $productoId)
            ->orderBy('entradas.fecha')
            ->orderBy('entradas.id')
            ->select('entradas_detalle.*')
            ->get();

        $cantCpp = 0.0;
        $costo   = 0.0;
        foreach ($detalles as $d) {
            $nuevaCantidad = $cantCpp + (float) $d->cantidad_base;
            $costo = $nuevaCantidad > 0
                ? (($cantCpp * $costo) + ((float) $d->cantidad_base * (float) $d->precio_costo)) / $nuevaCantidad
                : 0;
            $cantCpp = $nuevaCantidad;
        }

        $stock->cantidad       = max(0, round($cantidad, 4));
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

        return $pares
            ->map(fn ($r) => ['almacen_id' => $r->almacen_id, 'producto_id' => $r->producto_id])
            ->unique(fn ($r) => "{$r['almacen_id']}-{$r['producto_id']}")
            ->values();
    }
}
