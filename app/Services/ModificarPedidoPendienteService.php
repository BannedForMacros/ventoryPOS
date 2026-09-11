<?php

namespace App\Services;

use App\Models\ClienteAnticipo;
use App\Models\ClienteAnticipoAplicacion;
use App\Models\ClienteAnticipoItem;
use App\Models\Producto;
use App\Models\ProductoUnidad;
use App\Models\Stock;
use App\Models\Turno;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaAbono;
use App\Models\VentaItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Modifica el pedido PENDIENTE POR ENTREGAR de una venta días después de
 * hecha (el cliente vuelve y cambia productos/cantidades antes de llevárselos).
 *
 * No es una edición de la venta ni una devolución:
 *  - La venta conserva número, fecha, turno y sus pagos originales: la caja de
 *    ese día queda INTACTA.
 *  - Solo cambia lo que aún no se entregó. Lo llevado y lo ya entregado no se
 *    toca.
 *  - La diferencia de dinero se liquida HOY: cobro en la caja de hoy (abono),
 *    consumo de un anticipo de dinero, crédito, o — si sobra — saldo a favor
 *    del cliente (por defecto) o devolución en el acto.
 *
 * REGLA DE STOCK (la que hace confiable el "Recalcular"): por cada línea de la
 * venta, cantidad = llevado + entregado + pendiente. El recálculo resta la
 * línea completa y devuelve el pendiente vivo; por eso aquí toda variación del
 * pendiente se aplica a la VEZ en venta_items y en cliente_anticipo_items. El
 * stock en vivo no se mueve: lo pendiente nunca salió del almacén.
 *
 * PRECIOS: lo que se conserva mantiene su precio CONGELADO de la venta; lo
 * nuevo (o lo que aumenta) entra al precio indicado (por defecto el de hoy).
 */
class ModificarPedidoPendienteService
{
    private const EPS_CANT  = 0.00009;
    private const EPS_MONTO = 0.009;

    public function __construct(
        private TesoreriaService $tesoreria,
        private LocalScopeService $scope,
    ) {}

    /** Motivo por el que la venta NO admite modificar su pedido, o null si sí. */
    public function motivoBloqueo(Venta $venta): ?string
    {
        if ($venta->estado !== 'completada') {
            return 'Solo se puede modificar el pedido de una venta completada.';
        }
        if (strtoupper($venta->moneda ?? 'PEN') !== 'PEN') {
            return 'La modificación de pedido no está disponible para ventas en moneda extranjera.';
        }
        if (Schema::hasTable('venta_comprobantes')) {
            $ce = $venta->comprobanteElectronico()->first();
            if ($ce && $ce->esEmitido()) {
                return "La venta tiene el comprobante {$ce->numero} informado a SUNAT: para cambiarla corresponde una Nota de Crédito.";
            }
        }
        if (!$this->anticiposPendientes($venta)->contains(fn ($a) => $a->items->contains(fn ($i) => (float) $i->cantidad_pendiente > self::EPS_CANT))) {
            return 'Esta venta no tiene productos pendientes por entregar.';
        }

        return null;
    }

    /** Datos para el modal: pendientes con precio congelado y precio de hoy. */
    public function datos(Venta $venta, User $user): array
    {
        $venta->loadMissing(['items', 'cliente', 'empresa']);
        $anticipos = $this->anticiposPendientes($venta);

        $pendientes = [];
        foreach ($anticipos as $ant) {
            foreach ($ant->items as $it) {
                if ((float) $it->cantidad_pendiente <= self::EPS_CANT) continue;
                $unidad = ProductoUnidad::find($it->producto_unidad_id);
                $vi     = $it->venta_item_id ? $venta->items->firstWhere('id', $it->venta_item_id) : null;
                $pendientes[] = [
                    'id'                 => $it->id,
                    'anticipo_id'        => $ant->id,
                    'venta_item_id'      => $it->venta_item_id,
                    'producto_id'        => $it->producto_id,
                    'producto_unidad_id' => $it->producto_unidad_id,
                    'producto_nombre'    => $it->producto_nombre,
                    'unidad_nombre'      => $it->unidad_nombre,
                    'cantidad_pendiente' => (float) $it->cantidad_pendiente,
                    'entregado'          => max(0, round((float) $it->cantidad - (float) $it->cantidad_pendiente, 4)),
                    'precio_unitario'    => (float) $it->precio_unitario,
                    'precio_hoy'         => (float) ($unidad?->precio_venta ?? Producto::find($it->producto_id)?->precio_venta ?? $it->precio_unitario),
                    'incluye_igv'        => (bool) ($vi?->incluye_igv ?? false),
                    // Líneas de un "cambio de producto" antiguo no tienen línea de
                    // venta enlazada: no se pueden cuadrar con el stock aquí.
                    'modificable'        => $vi !== null,
                ];
            }
        }

        $cliente = $venta->cliente;
        $esGeneral = (bool) ($cliente?->es_cliente_general ?? false);

        return [
            'bloqueo' => $this->motivoBloqueo($venta),
            'venta'   => [
                'id'              => $venta->id,
                'numero'          => $venta->numero,
                'fecha_venta'     => $venta->fecha_venta,
                'es_credito'      => (bool) $venta->es_credito,
                'total'           => (float) $venta->total,
                'pagado'          => $this->pagadoDe($venta),
                'saldo_pendiente' => (float) $venta->saldo_pendiente,
                'descuento_total' => (float) $venta->descuento_total,
                'tasa_igv'        => (float) ($venta->empresa?->tasa_igv ?? 18),
                'cliente'         => $cliente ? [
                    'id'                 => $cliente->id,
                    'nombre'             => $cliente->razon_social ?: trim(($cliente->nombres ?? '') . ' ' . ($cliente->apellidos ?? '')),
                    'es_cliente_general' => $esGeneral,
                ] : null,
            ],
            // Todas las líneas de la venta: el front recalcula el total con la
            // misma fórmula que Venta::calcularTotales (IGV + descuento global).
            'venta_items' => $venta->items->map(fn ($i) => [
                'id'              => $i->id,
                'cantidad'        => (float) $i->cantidad,
                'precio_unitario' => (float) $i->precio_unitario,
                'descuento_item'  => (float) $i->descuento_item,
                'incluye_igv'     => (bool) $i->incluye_igv,
            ])->values(),
            'pendientes' => $pendientes,
            'anticipos_dinero' => $cliente && !$esGeneral
                ? ClienteAnticipo::where('empresa_id', $venta->empresa_id)
                    ->where('cliente_id', $cliente->id)
                    ->where('tipo_valorizacion', 'monto')
                    ->where('estado', 'activo')
                    ->where('saldo', '>', 0)
                    ->orderBy('fecha')
                    ->get(['id', 'fecha', 'saldo'])
                : [],
            'turno_activo_id' => Turno::turnoActivoDelUsuario($user->id)?->id,
        ];
    }

    /**
     * Aplica la modificación. $data (validado en el controlador):
     *  - items[]:  {id: cliente_anticipo_item_id, cantidad_pendiente: nuevo pendiente (≤ actual)}
     *  - nuevos[]: {producto_id, producto_unidad_id, cantidad, precio_unitario}
     *  - motivo
     *  - Si FALTA dinero: cobro_anticipo_id + cobro_monto_anticipo, cobro_monto_pago
     *    (+ metodo_pago_id, cuenta_id, referencia, turno_id), dejar_credito.
     *  - Si SOBRA dinero: excedente_destino 'saldo_favor' (default) | 'devolver'
     *    (+ metodo_pago_id, cuenta_id, turno_id).
     *
     * @return array resumen de lo aplicado (para el mensaje y la auditoría)
     */
    public function modificar(Venta $venta, array $data, User $user): array
    {
        if ($bloqueo = $this->motivoBloqueo($venta)) {
            abort(422, $bloqueo);
        }

        return DB::transaction(function () use ($venta, $data, $user) {
            $venta = Venta::whereKey($venta->id)->lockForUpdate()->firstOrFail();
            $venta->load(['items', 'cliente']);

            $anticipos = $this->anticiposPendientes($venta, lock: true);
            $itemsPorId = $anticipos->flatMap(fn ($a) => $a->items)->keyBy('id');

            $totalAntes  = (float) $venta->total;
            $pagadoAntes = $this->pagadoDe($venta);
            $antes = $this->snapshotPendiente($anticipos);
            $saldoAnticiposAntes = $anticipos->mapWithKeys(fn ($a) => [$a->id => (float) $a->saldo])->all();

            $cambios = 0;

            // ── 1) Reducir / quitar lo pendiente de líneas existentes ──────
            foreach ($data['items'] ?? [] as $idx => $linea) {
                /** @var ClienteAnticipoItem|null $item */
                $item = $itemsPorId->get((int) $linea['id']);
                if (!$item) {
                    throw ValidationException::withMessages(["items.{$idx}.id" => 'La línea no pertenece al pedido pendiente de esta venta.']);
                }

                $actual = (float) $item->cantidad_pendiente;
                $nuevo  = round(max(0, (float) $linea['cantidad_pendiente']), 4);

                if ($nuevo > $actual + self::EPS_CANT) {
                    throw ValidationException::withMessages([
                        "items.{$idx}.cantidad_pendiente" => "Para aumentar «{$item->producto_nombre}» agrégalo como producto nuevo (entra al precio de hoy).",
                    ]);
                }

                $reducir = round($actual - $nuevo, 4);
                $precioNuevo = isset($linea['precio_unitario']) && $linea['precio_unitario'] !== ''
                    ? round((float) $linea['precio_unitario'], 2)
                    : null;
                $repreciar = $precioNuevo !== null
                    && abs($precioNuevo - (float) $item->precio_unitario) > 0.005
                    && $nuevo > self::EPS_CANT;

                if ($reducir <= self::EPS_CANT && !$repreciar) continue;

                $ventaItem = $item->venta_item_id
                    ? VentaItem::where('id', $item->venta_item_id)->where('venta_id', $venta->id)->lockForUpdate()->first()
                    : null;
                if (!$ventaItem) {
                    throw ValidationException::withMessages([
                        "items.{$idx}.id" => "«{$item->producto_nombre}» viene de un cambio de producto antiguo y no se puede modificar aquí. Usa «Cancelar pendiente» en Anticipos.",
                    ]);
                }

                if ($reducir > self::EPS_CANT) {
                    $this->reducirLinea($ventaItem, $item, $reducir);
                    $cambios++;
                }

                if ($repreciar && $item->exists && (float) $item->cantidad_pendiente > self::EPS_CANT) {
                    $anticipoDeItem = $anticipos->firstWhere('id', $item->cliente_anticipo_id);
                    $this->repreciarLinea($venta, $anticipoDeItem, $ventaItem, $item, $precioNuevo);
                    $cambios++;
                }
            }

            // ── 2) Agregar productos (o aumentar cantidades) ───────────────
            if (!empty($data['nuevos'])) {
                $destino = $anticipos->sortByDesc('id')->first();
                $almacen = $this->scope->almacenVentasDeLocal($venta->empresa_id, $venta->local_id);

                foreach ($data['nuevos'] as $idx => $n) {
                    $producto = Producto::deEmpresa($venta->empresa_id)->activo()->find($n['producto_id']);
                    $unidad   = $producto
                        ? ProductoUnidad::where('id', $n['producto_unidad_id'])->where('producto_id', $producto->id)->first()
                        : null;
                    if (!$producto || !$unidad) {
                        throw ValidationException::withMessages(["nuevos.{$idx}.producto_id" => 'Producto o presentación no válidos.']);
                    }

                    $cantidad = round((float) $n['cantidad'], 4);
                    $precio   = round((float) $n['precio_unitario'], 2);
                    if ($cantidad <= self::EPS_CANT) continue;

                    $this->agregarLinea($venta, $destino, $producto, $unidad, $cantidad, $precio, $almacen?->id);
                    $cambios++;
                }
            }

            if ($cambios === 0) {
                throw ValidationException::withMessages(['items' => 'No hay cambios en el pedido: modifica alguna cantidad o agrega un producto.']);
            }

            // ── 3) Totales de la venta y saldo de los anticipos ────────────
            $venta->load('items');
            $venta->calcularTotales();
            $venta->refresh();

            foreach ($anticipos as $ant) {
                $this->recomputarAnticipo($ant, $saldoAnticiposAntes[$ant->id] ?? 0.0);
            }

            $totalNuevo = (float) $venta->total;
            $saldo      = round($totalNuevo - $pagadoAntes, 2);
            $liquidacion = $this->liquidar($venta, $data, $user, $pagadoAntes, $saldo);

            $resumen = [
                'motivo'       => $data['motivo'],
                'total_antes'  => round($totalAntes, 2),
                'total_nuevo'  => round($totalNuevo, 2),
                'diferencia'   => round($totalNuevo - $totalAntes, 2),
                'antes'        => $antes,
                'despues'      => $this->snapshotPendiente($this->anticiposPendientes($venta)),
                'liquidacion'  => $liquidacion,
            ];

            AuditoriaService::log('venta.pedido_modificado', $venta, $resumen, $user);

            return $resumen;
        });
    }

    /**
     * "Cambiar producto" de una línea pendiente AL MISMO PRECIO congelado (flujo
     * de Finanzas → Anticipos). Mueve la cantidad a una línea de venta del
     * producto nuevo para respetar la regla de stock; antes solo tocaba el
     * anticipo y el "Recalcular" fabricaba stock fantasma en ambos productos.
     * Llamar dentro de una transacción.
     */
    public function cambiarProductoLinea(ClienteAnticipo $anticipo, ClienteAnticipoItem $item, float $cantidad, Producto $producto, ProductoUnidad $unidad): void
    {
        $venta = Venta::whereKey($anticipo->venta_id)->lockForUpdate()->firstOrFail();
        $vi    = VentaItem::where('id', $item->venta_item_id)->where('venta_id', $venta->id)->lockForUpdate()->firstOrFail();
        $precio = (float) $item->precio_unitario;
        $saldoAntes = (float) $anticipo->saldo;

        $this->reducirLinea($vi, $item, $cantidad);
        $almacen = $this->scope->almacenVentasDeLocal($venta->empresa_id, $venta->local_id);
        $anticipo->unsetRelation('items');
        $this->agregarLinea($venta, $anticipo, $producto, $unidad, $cantidad, $precio, $almacen?->id);

        $venta->load('items');
        $venta->calcularTotales();
        $venta->refresh();
        // Mismo precio y cantidad: el total no cambia salvo redondeo de IGV entre
        // un producto gravado y uno exonerado; lo pagado sigue al total.
        $venta->update($venta->es_credito
            ? ['saldo_pendiente' => max(0, round((float) $venta->total - (float) $venta->monto_pagado, 2))]
            : ['monto_pagado' => (float) $venta->total, 'saldo_pendiente' => 0]);

        $this->recomputarAnticipo($anticipo, $saldoAntes);
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Líneas
    // ─────────────────────────────────────────────────────────────────────

    /** Baja lo pendiente de una línea en venta_items y en el anticipo a la vez. */
    private function reducirLinea(VentaItem $vi, ClienteAnticipoItem $item, float $cantidad): void
    {
        $vi->cantidad      = max(0, round((float) $vi->cantidad - $cantidad, 4));
        $vi->cantidad_base = max(0, round((float) $vi->cantidad_base - $cantidad * (float) $vi->factor_conversion, 4));
        $vi->subtotal      = round(((float) $vi->precio_unitario - (float) $vi->descuento_item) * (float) $vi->cantidad, 2);
        $vi->save();

        $item->update([
            'cantidad'           => max(0, round((float) $item->cantidad - $cantidad, 4)),
            'cantidad_pendiente' => max(0, round((float) $item->cantidad_pendiente - $cantidad, 4)),
        ]);

        // Línea que quedó vacía y sin historia (nada llevado, entregado ni
        // cancelado): se elimina para que la nota de venta no muestre "0".
        $sinHistoria = (float) $item->cantidad <= self::EPS_CANT
            && !DB::table('cliente_anticipo_aplicacion_items')->where('cliente_anticipo_item_id', $item->id)->exists()
            && !$item->cancelaciones()->exists();
        if (!$sinHistoria) return;

        $item->delete();

        if ((float) $vi->cantidad <= self::EPS_CANT
            && !DB::table('devoluciones_detalle')->where('venta_item_id', $vi->id)->exists()) {
            $vi->delete();
        }
    }

    /**
     * Cambia el precio de lo que sigue PENDIENTE. Lo ya llevado o entregado
     * conserva el precio al que se vendió: si esa parte existe, la línea se
     * separa en dos (lo entregado con su precio viejo, lo pendiente con el
     * nuevo). Si toda la línea está pendiente, simplemente se reprecia.
     */
    private function repreciarLinea(Venta $venta, ?ClienteAnticipo $anticipo, VentaItem $vi, ClienteAnticipoItem $item, float $precio): void
    {
        $pendiente   = (float) $item->cantidad_pendiente;
        $noPendiente = round((float) $vi->cantidad - $pendiente, 4);

        if ($noPendiente <= self::EPS_CANT) {
            // El descuento por línea se absorbe en el precio nuevo (es el neto).
            $vi->update([
                'precio_unitario' => $precio,
                'descuento_item'  => 0,
                'subtotal'        => round($precio * (float) $vi->cantidad, 2),
            ]);
            $item->update(['precio_unitario' => $precio]);

            return;
        }

        // Separar: la línea vieja se queda con lo llevado/entregado y su precio.
        $vi->cantidad      = $noPendiente;
        $vi->cantidad_base = round($noPendiente * (float) $vi->factor_conversion, 4);
        $vi->subtotal      = round(((float) $vi->precio_unitario - (float) $vi->descuento_item) * $noPendiente, 2);
        $vi->save();

        $entregado = round((float) $item->cantidad - $pendiente, 4);
        $conHistoria = DB::table('cliente_anticipo_aplicacion_items')->where('cliente_anticipo_item_id', $item->id)->exists()
            || $item->cancelaciones()->exists();
        if ($entregado > self::EPS_CANT || $conHistoria) {
            $item->update(['cantidad' => max(0, $entregado), 'cantidad_pendiente' => 0]);
        } else {
            $item->delete();
        }

        $producto = Producto::find($item->producto_id);
        $unidad   = ProductoUnidad::find($item->producto_unidad_id);
        $almacen  = $this->scope->almacenVentasDeLocal($venta->empresa_id, $venta->local_id);
        if ($anticipo && $producto && $unidad) {
            $anticipo->unsetRelation('items');
            $this->agregarLinea($venta, $anticipo, $producto, $unidad, $pendiente, $precio, $almacen?->id);
        }
    }

    /**
     * Agrega cantidad pendiente de un producto. Si ya hay una línea del mismo
     * producto/presentación AL MISMO PRECIO y sin descuento, se suma ahí; si el
     * precio difiere (lo nuevo va al precio de hoy), se crea una línea aparte.
     */
    private function agregarLinea(Venta $venta, ClienteAnticipo $destino, Producto $producto, ProductoUnidad $unidad, float $cantidad, float $precio, ?int $almacenId): void
    {
        $destino->loadMissing('items');
        foreach ($destino->items as $it) {
            // Una línea recién vaciada y eliminada en este mismo pedido no se reutiliza.
            if (!$it->exists || !$it->venta_item_id || (int) $it->producto_unidad_id !== (int) $unidad->id) continue;
            if (abs((float) $it->precio_unitario - $precio) > 0.005) continue;

            $vi = VentaItem::where('id', $it->venta_item_id)->where('venta_id', $venta->id)->lockForUpdate()->first();
            if (!$vi || (float) $vi->descuento_item > 0.005 || abs((float) $vi->precio_unitario - $precio) > 0.005) continue;

            $vi->cantidad      = round((float) $vi->cantidad + $cantidad, 4);
            $vi->cantidad_base = round((float) $vi->cantidad_base + $cantidad * (float) $vi->factor_conversion, 4);
            $vi->subtotal      = round($precio * (float) $vi->cantidad, 2);
            $vi->save();

            $it->update([
                'cantidad'           => round((float) $it->cantidad + $cantidad, 4),
                'cantidad_pendiente' => round((float) $it->cantidad_pendiente + $cantidad, 4),
            ]);

            return;
        }

        // Costo CONGELADO de hoy (mismo criterio que el POS) para la utilidad.
        $costoBase = (float) ($producto->precio_costo ?? 0);
        if ($costoBase <= 0 && $almacenId) {
            $costoBase = (float) (Stock::where('almacen_id', $almacenId)->where('producto_id', $producto->id)->value('costo_promedio') ?? 0);
        }
        $unidadNombre = $unidad->unidadMedida->nombre ?? '';

        $vi = VentaItem::create([
            'venta_id'            => $venta->id,
            'producto_id'         => $producto->id,
            'producto_unidad_id'  => $unidad->id,
            'producto_nombre'     => $producto->nombre,
            'unidad_nombre'       => $unidadNombre,
            'cantidad'            => $cantidad,
            'factor_conversion'   => $unidad->factor_conversion,
            'cantidad_base'       => round($cantidad * (float) $unidad->factor_conversion, 4),
            'precio_unitario'     => $precio,
            'precio_original'     => $unidad->precio_venta ?? $producto->precio_venta,
            'descuento_item'      => 0,
            'subtotal'            => round($precio * $cantidad, 2),
            'incluye_igv'         => $producto->incluye_igv,
            'costo_unitario_base' => round($costoBase, 4),
        ]);

        $destino->items()->create([
            'venta_item_id'      => $vi->id,
            'producto_id'        => $producto->id,
            'producto_unidad_id' => $unidad->id,
            'producto_nombre'    => $producto->nombre,
            'unidad_nombre'      => $unidadNombre ?: 'und',
            'cantidad'           => $cantidad,
            'factor_conversion'  => (float) $unidad->factor_conversion,
            'cantidad_pendiente' => $cantidad,
            'precio_unitario'    => $precio,
        ]);
        $destino->unsetRelation('items');
    }

    /** Saldo/cantidad/estado del anticipo a partir de sus líneas vivas. */
    private function recomputarAnticipo(ClienteAnticipo $anticipo, float $saldoAntes): void
    {
        $anticipo->load('items');
        $saldo    = round($anticipo->items->sum(fn ($i) => (float) $i->cantidad_pendiente * (float) $i->precio_unitario), 2);
        $cantidad = round($anticipo->items->sum(fn ($i) => (float) $i->cantidad_pendiente), 4);

        $anticipo->update([
            'saldo'              => $saldo,
            'cantidad_pendiente' => $cantidad,
            // "Recibido" sigue a lo que el cliente dejó pagado por el pedido.
            'monto'              => max(0, round((float) $anticipo->monto + ($saldo - $saldoAntes), 2)),
            'estado'             => $saldo <= 0.01 && $cantidad <= self::EPS_CANT ? 'aplicado' : 'activo',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Dinero
    // ─────────────────────────────────────────────────────────────────────

    private function liquidar(Venta $venta, array $data, User $user, float $pagadoAntes, float $saldo): array
    {
        $nombreCliente = $venta->cliente?->razon_social
            ?: trim(($venta->cliente?->nombres ?? '') . ' ' . ($venta->cliente?->apellidos ?? ''));
        $esGeneral = (bool) ($venta->cliente?->es_cliente_general ?? true);
        $hoy = now()->toDateString();

        // ── Falta dinero ───────────────────────────────────────────────
        if ($saldo > self::EPS_MONTO) {
            $montoAnticipo = round((float) ($data['cobro_monto_anticipo'] ?? 0), 2);
            $montoPago     = round((float) ($data['cobro_monto_pago'] ?? 0), 2);

            if ($montoAnticipo + $montoPago > $saldo + self::EPS_MONTO) {
                throw ValidationException::withMessages([
                    'cobro_monto_pago' => 'Lo cobrado (S/ ' . number_format($montoAnticipo + $montoPago, 2)
                        . ') supera lo que falta pagar (S/ ' . number_format($saldo, 2) . ').',
                ]);
            }

            if ($montoAnticipo > self::EPS_MONTO) {
                $anticipo = ClienteAnticipo::where('id', $data['cobro_anticipo_id'] ?? 0)
                    ->where('empresa_id', $venta->empresa_id)
                    ->where('cliente_id', $venta->cliente_id)
                    ->where('tipo_valorizacion', 'monto')
                    ->where('estado', 'activo')
                    ->lockForUpdate()
                    ->first();
                if (!$anticipo) {
                    throw ValidationException::withMessages(['cobro_anticipo_id' => 'El anticipo no está disponible para este cliente.']);
                }
                if ($montoAnticipo > (float) $anticipo->saldo + self::EPS_MONTO) {
                    throw ValidationException::withMessages([
                        'cobro_monto_anticipo' => 'El anticipo solo tiene S/ ' . number_format((float) $anticipo->saldo, 2) . ' de saldo.',
                    ]);
                }

                $abono = VentaAbono::create([
                    'venta_id'            => $venta->id,
                    'user_id'             => $user->id,
                    'fecha'               => $hoy,
                    'monto'               => $montoAnticipo,
                    'observacion'         => "Diferencia por modificación de pedido — cobrado del anticipo #{$anticipo->id}",
                    'cliente_anticipo_id' => $anticipo->id,
                ]);
                $anticipo->aplicaciones()->create([
                    'empresa_id'     => $venta->empresa_id,
                    'numero'         => ClienteAnticipoAplicacion::generarNumero($venta->empresa_id),
                    'venta_id'       => $venta->id,
                    'venta_abono_id' => $abono->id,
                    'user_id'        => $user->id,
                    'fecha'          => $hoy,
                    'monto'          => $montoAnticipo,
                    'observacion'    => "Modificación de pedido — venta {$venta->numero}",
                ]);
                $nuevoSaldo = round((float) $anticipo->saldo - $montoAnticipo, 2);
                $anticipo->update([
                    'saldo'  => max(0, $nuevoSaldo),
                    'estado' => $nuevoSaldo <= 0.01 ? 'aplicado' : 'activo',
                ]);
            }

            if ($montoPago > self::EPS_MONTO) {
                if (empty($data['metodo_pago_id'])) {
                    throw ValidationException::withMessages(['metodo_pago_id' => 'Elige con qué método paga el cliente la diferencia.']);
                }
                $abono = VentaAbono::create([
                    'venta_id'       => $venta->id,
                    'user_id'        => $user->id,
                    'turno_id'       => $this->turnoHoy($data, $user),
                    'metodo_pago_id' => $data['metodo_pago_id'],
                    'cuenta_id'      => $data['cuenta_id'] ?? null,
                    'fecha'          => $hoy,
                    'monto'          => $montoPago,
                    'referencia'     => $data['referencia'] ?? null,
                    'observacion'    => 'Diferencia por modificación de pedido',
                ]);
                $this->tesoreria->registrar(
                    $venta->empresa_id,
                    $data['cuenta_id'] ?? $this->tesoreria->resolverCuenta($venta->empresa_id, null, $data['metodo_pago_id']),
                    $user,
                    $hoy,
                    'ingreso',
                    $montoPago,
                    "Diferencia por modificación de pedido — venta {$venta->numero} — {$nombreCliente}",
                    'venta_abono',
                    $abono->id,
                );
            }

            $resto = round($saldo - $montoAnticipo - $montoPago, 2);
            if ($resto > self::EPS_MONTO) {
                if ($esGeneral) {
                    throw ValidationException::withMessages([
                        'dejar_credito' => 'Faltan S/ ' . number_format($resto, 2) . ' por cobrar. Una venta a «Cliente General» no puede quedar al crédito: cobra la diferencia completa.',
                    ]);
                }
                if (!$venta->es_credito && empty($data['dejar_credito'])) {
                    throw ValidationException::withMessages([
                        'dejar_credito' => 'Faltan S/ ' . number_format($resto, 2) . ' por cobrar: cóbralos ahora o marca «Dejar el resto al crédito».',
                    ]);
                }
            }

            $pagado = round($pagadoAntes + $montoAnticipo + $montoPago, 2);
            $venta->update([
                'monto_pagado'    => $pagado,
                'saldo_pendiente' => max(0, round((float) $venta->total - $pagado, 2)),
                'es_credito'      => $venta->es_credito || $resto > self::EPS_MONTO,
            ]);

            return [
                'tipo'            => 'cobro',
                'falta'           => $saldo,
                'del_anticipo'    => $montoAnticipo,
                'pago'            => $montoPago,
                'al_credito'      => max(0, $resto),
            ];
        }

        // ── Sobra dinero ───────────────────────────────────────────────
        if ($saldo < -self::EPS_MONTO) {
            $excedente = round(-$saldo, 2);
            $destino   = ($data['excedente_destino'] ?? 'saldo_favor') === 'devolver' ? 'devolver' : 'saldo_favor';

            if ($destino === 'saldo_favor' && $esGeneral) {
                throw ValidationException::withMessages([
                    'excedente_destino' => 'La venta es de «Cliente General»: el saldo a favor no quedaría a nombre de nadie. Devuelve el dinero o asigna el cliente a la venta.',
                ]);
            }
            if ($destino === 'devolver' && empty($data['metodo_pago_id'])) {
                throw ValidationException::withMessages(['metodo_pago_id' => 'Elige por dónde se devuelve el dinero al cliente.']);
            }

            // El excedente se registra como anticipo de DINERO sin tesorería (la
            // plata ya entró con la venta). Si se devuelve en el acto, ese mismo
            // anticipo sale "devuelto" con su egreso de hoy: así la caja de hoy
            // lo descuenta con la regla que ya existe para devoluciones.
            $anticipo = ClienteAnticipo::create([
                'empresa_id'        => $venta->empresa_id,
                'cliente_id'        => $venta->cliente_id,
                'user_id'           => $user->id,
                'venta_origen_id'   => $venta->id,
                'fecha'             => $hoy,
                'monto'             => $excedente,
                'saldo'             => $excedente,
                'tipo_valorizacion' => 'monto',
                'estado'            => 'activo',
                'observacion'       => "Saldo a favor por modificación del pedido de la venta {$venta->numero}",
            ]);

            if ($destino === 'devolver') {
                $turnoId = $this->turnoHoy($data, $user);
                $this->tesoreria->registrar(
                    $venta->empresa_id,
                    $data['cuenta_id'] ?? $this->tesoreria->resolverCuenta($venta->empresa_id, null, $data['metodo_pago_id']),
                    $user,
                    $hoy,
                    'egreso',
                    $excedente,
                    "Devolución por modificación de pedido — venta {$venta->numero} — {$nombreCliente}",
                    'cliente_anticipo_devolucion',
                    $anticipo->id,
                );
                $anticipo->update([
                    'estado'              => 'devuelto',
                    'turno_devolucion_id' => $turnoId,
                    'observacion'         => "Devuelto en el acto — modificación del pedido de la venta {$venta->numero}",
                ]);
            }

            $venta->update([
                'monto_pagado'    => round((float) $venta->total, 2),
                'saldo_pendiente' => 0,
            ]);

            return [
                'tipo'        => $destino,
                'excedente'   => $excedente,
                'anticipo_id' => $anticipo->id,
            ];
        }

        // ── Cuadra exacto ──────────────────────────────────────────────
        $venta->update(['saldo_pendiente' => max(0, round((float) $venta->total - $pagadoAntes, 2))]);

        return ['tipo' => 'sin_diferencia'];
    }

    // ─────────────────────────────────────────────────────────────────────
    //  Helpers
    // ─────────────────────────────────────────────────────────────────────

    /** Anticipos materiales vivos de la venta (el pedido pendiente). */
    private function anticiposPendientes(Venta $venta, bool $lock = false)
    {
        $q = ClienteAnticipo::where('venta_id', $venta->id)
            ->where('tipo_valorizacion', 'material')
            ->where('estado', 'activo')
            ->orderBy('id');
        if ($lock) $q->lockForUpdate();

        return $q->get()->each(fn ($a) => $a->load(['items' => fn ($i) => $i->orderBy('id')]));
    }

    /**
     * Lo que el cliente ya pagó de la venta. Una venta de contado está pagada
     * por completo por definición (algunas antiguas no guardaban monto_pagado).
     */
    private function pagadoDe(Venta $venta): float
    {
        return $venta->es_credito
            ? round((float) $venta->monto_pagado, 2)
            : round(max((float) $venta->monto_pagado, (float) $venta->total), 2);
    }

    /** Turno cuya caja recibe/entrega el dinero de HOY. */
    private function turnoHoy(array $data, User $user): ?int
    {
        if (!empty($data['turno_id'])) {
            $turno = Turno::where('id', $data['turno_id'])
                ->where('empresa_id', $user->empresa_id)
                ->where('estado', 'abierto')
                ->first();
            if (!$turno) {
                throw ValidationException::withMessages(['turno_id' => 'El turno elegido no está abierto.']);
            }

            return $turno->id;
        }

        return Turno::turnoActivoDelUsuario($user->id)?->id;
    }

    private function snapshotPendiente($anticipos): array
    {
        return $anticipos->flatMap(fn ($a) => $a->items)
            ->filter(fn ($i) => (float) $i->cantidad_pendiente > self::EPS_CANT)
            ->map(fn ($i) => [
                'producto' => $i->producto_nombre,
                'unidad'   => $i->unidad_nombre,
                'cantidad' => (float) $i->cantidad_pendiente,
                'precio'   => (float) $i->precio_unitario,
                'importe'  => round((float) $i->cantidad_pendiente * (float) $i->precio_unitario, 2),
            ])->values()->all();
    }
}
