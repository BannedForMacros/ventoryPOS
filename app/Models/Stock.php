<?php

namespace App\Models;

use App\Exceptions\InsufficientStockException;
use App\Services\KardexService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
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

        return DB::transaction(function () use ($almacenId, $productoId, $cantidadBase, $costoNuevo, $permitirNegativo, $contexto) {
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
                //
                // Un saldo previo NEGATIVO no participa del promedio: no es inventario
                // real, es mercadería vendida antes de registrar la compra. Sin este
                // resguardo, vender 2,290 y luego comprar 2,291 dividía el total de la
                // compra entre 1 unidad (el ladrillo a S/ 2,268 del 10/09). Es la misma
                // fórmula que KardexService::reproducir.
                if ($costoNuevo > 0) {
                    $costoActual = (float) $stock->costo_promedio;
                    $base        = max($cantidadActual, 0.0);
                    $divisor     = $base + $cantidadBase;
                    $stock->costo_promedio = round(
                        $divisor > 0
                            ? (($base * $costoActual) + ($cantidadBase * $costoNuevo)) / $divisor
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

            // El kardex se registra en la MISMA transacción: stock y kardex se
            // guardan juntos o no se guarda ninguno.
            self::trazarEnKardex($stock, $almacenId, $productoId, $cantidadBase, $costoNuevo, $contexto);

            return $stock;
        });
    }

    /**
     * Registra una fila en el kardex (movimientos_inventario) dentro de la
     * transacción del movimiento, y agenda la reconstrucción del producto cuando
     * la fila deja la cadena de saldos desordenada.
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

        $kardex = app(KardexService::class);

        // El insert va en su propio savepoint: si el kardex fallara, la venta o
        // compra NO se cae — se revierte solo el savepoint y se agenda la
        // reconstrucción del producto, que lo deja al día desde los documentos.
        // Antes el error se tragaba en un log y el kardex quedaba desfasado hasta
        // que alguien apretaba "Recalcular".
        try {
            $fueraDeOrden = DB::transaction(function () use ($fila, $almacenId, $productoId) {
                $ultimaFecha = DB::table('movimientos_inventario')
                    ->where('almacen_id', $almacenId)
                    ->where('producto_id', $productoId)
                    ->max('fecha');

                MovimientoInventario::create($fila);

                return $ultimaFecha !== null
                    && Carbon::parse($fila['fecha'])->lt(Carbon::parse($ultimaFecha));
            });
        } catch (\Throwable $e) {
            Log::error('No se pudo registrar el movimiento en el kardex; se reconstruirá el producto.', [
                'error'       => $e->getMessage(),
                'almacen_id'  => $fila['almacen_id'],
                'producto_id' => $fila['producto_id'],
                'tipo'        => $fila['tipo'],
            ]);
            $kardex->programarReconstruccion($almacenId, $productoId, 'kardex_no_registrado');

            return;
        }

        // Una fila con fecha anterior a la última del producto (compra cargada tarde,
        // documento retrofechado) o una que corrige historia (reverso, edición,
        // anulación) desordena los saldos: se rearma el producto en segundo plano.
        if ($fueraDeOrden || in_array($fila['tipo'], KardexService::TIPOS_CORRECCION, true)) {
            $kardex->programarReconstruccion($almacenId, $productoId, $fueraDeOrden ? 'fuera_de_orden' : $fila['tipo']);
        }
    }

    /**
     * Reconstruye el stock y el kardex de UN par (almacén, producto) desde sus
     * documentos. Delega en el MOTOR ÚNICO (KardexService): la tabla stock queda
     * igual a la última fila del kardex, con la misma fórmula de costo que usa
     * Stock::ajustar en vivo.
     *
     * Antes este método tenía su propio cálculo, distinto del kardex: promediaba
     * las compras sin descontar las ventas y no restaba las entregas de pedidos
     * cuya venta no tenía ítems. Cada "Recalcular" dejaba así un costo en la tabla
     * stock y otro en el kardex para el mismo producto.
     *
     * Las cantidades negativas se conservan: un saldo negativo es una
     * inconsistencia real que el admin debe ver (scopeNegativo), no se trunca.
     */
    public static function reconstruir(int $almacenId, int $productoId): self
    {
        app(KardexService::class)->reconstruirPar($almacenId, $productoId);

        return self::firstOrCreate(
            ['almacen_id' => $almacenId, 'producto_id' => $productoId],
            ['cantidad' => 0, 'costo_promedio' => 0]
        );
    }

    /**
     * ¿El movimiento de un documento está ABSORBIDO por el inventario inicial?
     *
     * Si existe apertura (stock_iniciales) para el par (almacén, producto) con
     * fecha de corte >= la fecha del documento, ese documento quedó DENTRO del
     * conteo físico: su stock ya vive en la apertura y NO debe aplicarse ni
     * revertirse en vivo (Stock::reconstruir lo excluye por el corte). Editar
     * una entrada del día del corte (o anterior) es solo corrección documental
     * (precios/cantidades): el inventario no se toca.
     */
    public static function absorbidoPorApertura(int $almacenId, int $productoId, $fecha): bool
    {
        $f = substr((string) $fecha, 0, 10);
        if ($f === '') return false;

        return DB::table('stock_iniciales')
            ->where('almacen_id', $almacenId)
            ->where('producto_id', $productoId)
            ->whereDate('fecha', '>=', $f)
            ->exists();
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

        // Ajustes de inventario
        $pares = $pares->merge(\DB::table('ajustes_inventario as ai')
            ->whereIn('ai.almacen_id', $almacenIds)
            ->select('ai.almacen_id', 'ai.producto_id')
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

        // Anticipos/pendientes por entregar (items y entregas): producto ×
        // almacén-de-ventas del local. Cubre tanto el pendiente aún en almacén
        // como las entregas ya realizadas.
        $pares = $pares->merge(\DB::table('cliente_anticipo_items as ci')
            ->join('cliente_anticipos as an', 'an.id', '=', 'ci.cliente_anticipo_id')
            ->join('ventas as v', 'v.id', '=', 'an.venta_id')
            ->join('almacenes as a', function ($j) {
                $j->on('a.local_id', '=', 'v.local_id')
                  ->on('a.empresa_id', '=', 'v.empresa_id')
                  ->where('a.tipo', '=', 'local');
            })
            ->whereIn('a.id', $almacenIds)
            ->select(\DB::raw('a.id as almacen_id'), 'ci.producto_id')
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
