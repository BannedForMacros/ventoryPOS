<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\ClienteAnticipo;
use App\Models\ClienteAnticipoAplicacion;
use App\Models\DescuentoLog;
use App\Services\LocalScopeService;
use App\Models\Producto;
use App\Models\ProductoUnidad;
use App\Models\Stock;
use App\Models\Turno;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaItem;
use App\Models\VentaPago;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VentaService
{
    public function __construct(
        private LocalScopeService $scope,
        private ConfiguracionOperacionService $config,
        private TesoreriaService $tesoreria,
        private TipoCambioService $tipoCambio,
    ) {}

    /**
     * Registra una venta completa dentro de una transacción:
     * crea la cabecera, items, movimientos de stock, pagos y logs de descuento.
     *
     * Idempotencia: si $data['idempotency_key'] viene y ya existe una venta con ese key
     * en la misma empresa, se devuelve la venta original sin crear nada nuevo.
     * Esto protege contra doble click en POS y reintentos por timeout de red.
     */
    public function crear(array $data, User $user, Turno $turno): Venta
    {
        // Check idempotente fuera de la transaccion principal: si ya existe, devolverla.
        $idempotencyKey = $data['idempotency_key'] ?? null;
        if ($idempotencyKey) {
            $existente = Venta::where('idempotency_key', $idempotencyKey)
                ->where('empresa_id', $user->empresa_id)
                ->first();
            if ($existente) {
                return $existente->load(['items', 'pagos', 'cliente']);
            }
        }

        return DB::transaction(function () use ($data, $user, $turno, $idempotencyKey) {
            // El stock se mueve en el almacén del local DEL TURNO (no el del
            // usuario): un admin puede registrar ventas en el turno reabierto
            // de una cajera de otro local sin desviar inventario.
            $almacen = $this->scope->almacenVentasDeLocal($user->empresa_id, $turno->local_id)
                ?? $this->scope->almacenParaVentas($user)
                ?? abort(422, 'No se encontró un almacén de ventas configurado.');

            // Backdate (solo lo inyecta el controlador para admins operando un
            // turno reabierto): la venta se asienta en la FECHA REAL en que
            // ocurrió — reportes, tesorería y balance de ese día la recogen.
            $fechaVenta = now();
            if (!empty($data['fecha_venta'])) {
                $f = \Illuminate\Support\Carbon::parse($data['fecha_venta']);
                $fechaVenta = $f->isToday() ? now() : $f->setTimeFromTimeString(now()->format('H:i:s'));
            }

            // Config de empresa: permitir que la venta deje stock negativo.
            // Si esta apagado, Stock::ajustar lanza InsufficientStockException
            // y toda la venta se revierte (comportamiento historico).
            $permitirStockNegativo = $this->config->permiteStockNegativo($user->empresa_id);

            // Si no se indico cliente, usar el Cliente General de la empresa.
            // A15: la identificacion del Cliente General es por la columna
            // `es_cliente_general` (no por DNI magico).
            $clienteId = $data['cliente_id']
                ?? Cliente::generalDeEmpresa($user->empresa_id)?->id
                ?? abort(422, 'No se encontró el Cliente General de la empresa.');

            // F1 — Venta a crédito: defensa en profundidad (el FormRequest ya
            // valida). El crédito exige cliente identificado.
            $esCredito = !empty($data['es_credito']);

            // Multimoneda: la venta puede cobrarse en USD. Todo se GUARDA en soles
            // (columnas existentes) al TC congelado del día; el original en moneda
            // extranjera queda en el trío moneda/tipo_cambio/monto_moneda.
            $moneda     = strtoupper($data['moneda'] ?? 'PEN') ?: 'PEN';
            $tipoCambio = null;
            $factor     = 1.0; // soles por 1 unidad de la moneda de venta
            if ($moneda !== 'PEN') {
                $factor = (!empty($data['tipo_cambio']) && (float) $data['tipo_cambio'] > 0)
                    ? (float) $data['tipo_cambio']                       // el TC que vio la cajera manda
                    : $this->tipoCambio->tasaPara($fechaVenta->toDateString(), $moneda);
                $tipoCambio = round($factor, 6);
            }
            if ($esCredito) {
                $cliente = Cliente::find($clienteId);
                if (!$cliente || $cliente->es_cliente_general) {
                    abort(422, 'Una venta a crédito requiere un cliente identificado.');
                }
            }

            // Cabecera de la venta — con retry contra dos races posibles:
            //   1) idempotency_key duplicado (mismo request dos veces)
            //   2) turno_id+numero duplicado (dos POS abriendo en el mismo turno
            //      calculan el mismo correlativo por casualidad)
            // El UNIQUE constraint `ventas_turno_id_numero_unique` garantiza
            // que solo uno gane; el otro reintenta con el siguiente número.
            $venta = null;
            $intentos = 0;
            $maxIntentos = 5;
            while ($venta === null) {
                $intentos++;
                try {
                    $venta = Venta::create([
                        'empresa_id'            => $user->empresa_id,
                        'local_id'              => $turno->local_id,
                        'turno_id'              => $turno->id,
                        'caja_id'               => $turno->caja_id,
                        'user_id'               => $user->id,
                        'cliente_id'            => $clienteId,
                        'numero'                => Venta::generarNumero($turno->id),
                        'idempotency_key'       => $idempotencyKey,
                        'tipo_comprobante'      => $data['tipo_comprobante'],
                        'numero_comprobante'    => $data['numero_comprobante'] ?? null,
                        'subtotal'              => 0,
                        'descuento_total'       => round((float) ($data['descuento_total'] ?? 0) * $factor, 2),
                        'descuento_concepto_id' => $data['descuento_concepto_id'] ?? null,
                        'igv'                   => 0,
                        'total'                 => 0,
                        'estado'                => 'completada',
                        'moneda'                => $moneda,
                        'tipo_cambio'           => $tipoCambio,
                        'es_credito'            => $esCredito,
                        'fecha_vencimiento'     => $esCredito ? ($data['fecha_vencimiento'] ?? null) : null,
                        'observacion'           => $data['observacion'] ?? null,
                        'fecha_venta'           => $fechaVenta,
                    ]);
                } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                    // Caso idempotency_key: si la venta original ya existe con el
                    // mismo key, devolverla en lugar de seguir intentando.
                    if ($idempotencyKey) {
                        $existente = Venta::where('idempotency_key', $idempotencyKey)
                            ->where('empresa_id', $user->empresa_id)
                            ->first();
                        if ($existente) {
                            return $existente->load(['items', 'pagos', 'cliente']);
                        }
                    }

                    // Caso turno_id+numero: otro request ganó el correlativo.
                    // Regeneramos en el siguiente loop (Venta::generarNumero
                    // ahora va a ver el numero recién insertado por el ganador).
                    if ($intentos >= $maxIntentos) {
                        throw $e; // no debería pasar bajo carga normal
                    }
                    // continúa el while con un nuevo número
                }
            }

            // Items + pagos + totales + tesorería. Lógica compartida con actualizar().
            $this->aplicarItemsPagos($venta, $data, $user, $turno, $almacen, $moneda, $tipoCambio, $factor, $esCredito, $clienteId, $permitirStockNegativo);

            return $venta->fresh(['items', 'pagos', 'cliente']);
        });
    }

    /**
     * Crea items, ajusta stock, recalcula totales y registra los pagos en
     * tesorería para una venta cuya cabecera YA existe. Lo comparten crear()
     * (venta nueva) y actualizar() (edición): en ambos casos la cabecera está
     * lista y aquí se puebla el detalle + los movimientos.
     *
     * Debe llamarse dentro de una transacción. En actualizar() los efectos
     * previos (stock, tesorería, items, pagos) ya fueron revertidos antes.
     */
    private function aplicarItemsPagos(
        Venta $venta,
        array $data,
        User $user,
        Turno $turno,
        \App\Models\Almacen $almacen,
        string $moneda,
        ?float $tipoCambio,
        float $factor,
        bool $esCredito,
        ?int $clienteId,
        bool $permitirStockNegativo,
    ): void {
        // Pendiente por entregar: el cliente paga todo pero se lleva solo parte.
        // Lo pendiente NO descuenta stock aquí (sale recién al entregarse) y se
        // acumula para crear el anticipo material vinculado a la venta.
        $esEntregaPendiente = !empty($data['entrega_pendiente']);
        $itemsPendientes    = [];

        // Items
        foreach ($data['items'] as $itemData) {
            $unidad   = ProductoUnidad::findOrFail($itemData['producto_unidad_id']);
            $producto = Producto::findOrFail($itemData['producto_id']);

            $cantidad       = (float) $itemData['cantidad'];
            $cantidadBase   = round($cantidad * (float) $unidad->factor_conversion, 4);
            $precioUnitario = round((float) $itemData['precio_unitario'] * $factor, 2);
            $descuentoItem  = round((float) ($itemData['descuento_item'] ?? 0) * $factor, 2);
            $subtotal       = round(($precioUnitario - $descuentoItem) * $cantidad, 2);

            $cantPendiente  = $esEntregaPendiente
                ? min(max(0, (float) ($itemData['cantidad_pendiente'] ?? 0)), $cantidad)
                : 0.0;

            // Costo CONGELADO por unidad base (criterio canónico del balance:
            // precio_costo del producto, o costo_promedio real si está en 0).
            // Fija el margen del Reporte de Utilidad al día de la venta.
            $costoBase = (float) ($producto->precio_costo ?? 0);
            if ($costoBase <= 0) {
                $costoBase = (float) (Stock::where('almacen_id', $almacen->id)
                    ->where('producto_id', $producto->id)
                    ->value('costo_promedio') ?? 0);
            }

            $item = VentaItem::create([
                'venta_id'             => $venta->id,
                'producto_id'          => $producto->id,
                'producto_unidad_id'   => $unidad->id,
                'producto_nombre'      => $producto->nombre,
                'unidad_nombre'        => $unidad->unidadMedida->nombre ?? '',
                'cantidad'             => $cantidad,
                'factor_conversion'    => $unidad->factor_conversion,
                'cantidad_base'        => $cantidadBase,
                'precio_unitario'      => $precioUnitario,
                'precio_original'      => $unidad->precio_venta ?? $producto->precio_venta,
                'descuento_item'       => $descuentoItem,
                'descuento_concepto_id'=> $itemData['descuento_concepto_id'] ?? null,
                'subtotal'             => $subtotal,
                'incluye_igv'          => $producto->incluye_igv,
                'costo_unitario_base'  => round($costoBase, 4),
            ]);

            if ($this->config->deboDescontarStock($producto, $turno->local)) {
                // Solo sale del almacén lo que el cliente SE LLEVA ahora; lo
                // pendiente se descuenta al registrar la entrega del anticipo.
                $baseEntregada = round(($cantidad - $cantPendiente) * (float) $unidad->factor_conversion, 4);
                if ($baseEntregada > 0.00009) {
                    Stock::ajustar($almacen->id, $producto->id, -$baseEntregada, 0, $permitirStockNegativo, contexto: [
                        'tipo'            => 'venta',
                        'referencia_tipo' => 'venta',
                        'referencia_id'   => $venta->id,
                        'fecha'           => $venta->fecha_venta ?? now(),
                        'user_id'         => $venta->user_id ?? optional(auth()->user())->id,
                        'empresa_id'      => $venta->empresa_id,
                    ]);
                }
            }

            if ($cantPendiente > 0.00009) {
                $itemsPendientes[] = [
                    'venta_item_id'      => $item->id,
                    'producto_id'        => $producto->id,
                    'producto_unidad_id' => $unidad->id,
                    'producto_nombre'    => $producto->nombre,
                    'unidad_nombre'      => $unidad->unidadMedida->nombre ?? '',
                    'cantidad'           => $cantPendiente,
                    'factor_conversion'  => (float) $unidad->factor_conversion,
                    'cantidad_pendiente' => $cantPendiente,
                    // Valor realmente pagado por unidad (S/, congelado).
                    'precio_unitario'    => round($precioUnitario - $descuentoItem, 2),
                ];
            }

            if ($descuentoItem > 0 && !empty($itemData['descuento_concepto_id'])) {
                DescuentoLog::create([
                    'empresa_id'            => $user->empresa_id,
                    'venta_id'              => $venta->id,
                    'venta_item_id'         => $item->id,
                    'descuento_concepto_id' => $itemData['descuento_concepto_id'],
                    'user_id'               => $user->id,
                    'cliente_id'            => $clienteId,
                    'monto_descuento'       => $descuentoItem * $cantidad,
                    'requeria_aprobacion'   => false,
                    'notificacion_enviada'  => false,
                ]);
            }
        }

        if (!empty($data['descuento_total']) && $data['descuento_total'] > 0 && !empty($data['descuento_concepto_id'])) {
            DescuentoLog::create([
                'empresa_id'            => $user->empresa_id,
                'venta_id'              => $venta->id,
                'venta_item_id'         => null,
                'descuento_concepto_id' => $data['descuento_concepto_id'],
                'user_id'               => $user->id,
                'cliente_id'            => $data['cliente_id'] ?? null,
                'monto_descuento'       => $data['descuento_total'],
                'requeria_aprobacion'   => false,
                'notificacion_enviada'  => false,
            ]);
        }

        // Totales antes de los pagos para conocer el total real
        $venta->load('items');
        $venta->calcularTotales();
        $venta->refresh();

        // Pagos (vuelto global asignado al primer método que admite vuelto).
        $pagos     = $data['pagos'] ?? [];
        $metodoIds = collect($pagos)->pluck('metodo_pago_id')->unique()->all();
        $metodos   = \App\Models\MetodoPago::whereIn('id', $metodoIds)->get()->keyBy('id');

        $totalPagado    = collect($pagos)->sum(fn($p) => round((float) $p['monto'] * $factor, 2));
        $vueltoGlobal   = max(0, round($totalPagado - (float) $venta->total, 2));
        $vueltoAsignado = false;

        foreach ($pagos as $pagoData) {
            $montoOrig    = (float) $pagoData['monto'];
            $monto        = round($montoOrig * $factor, 2);
            $metodo       = $metodos->get($pagoData['metodo_pago_id']);
            $admiteVuelto = (bool) ($metodo?->admite_vuelto);

            $vuelto = 0.0;
            if (!$vueltoAsignado && $admiteVuelto && $vueltoGlobal > 0) {
                $vuelto         = $vueltoGlobal;
                $vueltoAsignado = true;
            }

            VentaPago::create([
                'venta_id'              => $venta->id,
                'metodo_pago_id'        => $pagoData['metodo_pago_id'],
                'cuenta_metodo_pago_id' => $pagoData['cuenta_metodo_pago_id'] ?? null,
                'monto'                 => $monto,
                'referencia'            => $pagoData['referencia'] ?? null,
                'vuelto'                => $vuelto,
                'moneda'                => $moneda,
                'tipo_cambio'           => $tipoCambio,
                'monto_moneda'          => $moneda !== 'PEN' ? round($montoOrig, 2) : null,
            ]);

            $netoPen = round($monto - $vuelto, 2);
            // Fecha del asiento = fecha de la venta (respeta el backdate de un
            // turno reabierto: el dinero entró ese día, no hoy).
            $this->tesoreria->registrar(
                $user->empresa_id,
                $this->tesoreria->resolverCuenta($user->empresa_id, $pagoData['cuenta_metodo_pago_id'] ?? null, $pagoData['metodo_pago_id']),
                $user,
                $venta->fecha_venta?->toDateString() ?? now()->toDateString(),
                'ingreso',
                $netoPen,
                "Venta {$venta->numero} — " . ($metodo?->nombre ?? 'pago'),
                'venta',
                $venta->id,
                $moneda,
                $tipoCambio,
                $moneda !== 'PEN' && $factor > 0 ? round($netoPen / $factor, 2) : null,
            );
        }

        // Los ABONOS previos (cobros de crédito por CxC) SIGUEN vigentes al editar:
        // `actualizar()` revierte/recrea solo los pagos de la venta, no los abonos,
        // así que su dinero ya está en tesorería y debe contarse en lo pagado. Antes
        // se ignoraban → una venta a crédito con abonos, al editarla, mostraba
        // Pagado 0 / saldo completo y "reaparecía" como crédito, con la transferencia
        // del abono sobrando en caja. (En creación no hay abonos → suma 0, sin efecto.)
        $abonosPrevios   = round((float) $venta->abonos()->sum('monto'), 2);

        // ── Anticipo de efectivo aplicado a la venta ──────────────────────
        // El dinero ya entró a caja cuando se creó el anticipo; aquí solo se
        // descuenta el saldo y se vincula la aplicación a esta venta. No se
        // registra ingreso de tesorería nuevo.
        $montoAnticipo = 0.0;
        if (!empty($data['anticipo_id'])) {
            $montoAnticipo = $this->aplicarAnticipoAVenta($venta, $data['anticipo_id'], $user);
        }

        $montoPagadoReal = round($totalPagado - $vueltoGlobal + $abonosPrevios + $montoAnticipo, 2);
        $venta->update([
            'monto_pagado'    => $esCredito ? $montoPagadoReal : (float) $venta->total,
            'saldo_pendiente' => $esCredito ? max(0, round((float) $venta->total - $montoPagadoReal, 2)) : 0,
            'monto_moneda'    => $moneda !== 'PEN' && $factor > 0 ? round((float) $venta->total / $factor, 2) : null,
        ]);

        // ── Pendiente por entregar → anticipo material automático ────────
        // Toda la mercadería pagada y NO llevada queda como pasivo en
        // finanzas/anticipos (multi-producto, vinculado a la venta). El dinero
        // NO se vuelve a registrar en tesorería: ya entró con los pagos de la
        // venta. La entrega (total o parcial, con fecha) se registra luego en
        // Finanzas → Anticipos y recién ahí sale el stock.
        if (!empty($itemsPendientes)) {
            $montoPendiente = round(collect($itemsPendientes)
                ->sum(fn ($i) => $i['cantidad'] * $i['precio_unitario']), 2);

            $anticipo = \App\Models\ClienteAnticipo::create([
                'empresa_id'             => $user->empresa_id,
                'cliente_id'             => $venta->cliente_id,
                'user_id'                => $user->id,
                'venta_id'               => $venta->id,
                'fecha'                  => $venta->fecha_venta?->toDateString() ?? now()->toDateString(),
                'monto'                  => $montoPendiente,
                'saldo'                  => $montoPendiente,
                'tipo_valorizacion'      => 'material',
                'estado'                 => 'activo',
                'fecha_entrega_estimada' => $data['fecha_entrega_estimada'] ?? null,
                'observacion'            => "Pendiente por entregar — Venta {$venta->numero}",
            ]);

            $anticipo->items()->createMany($itemsPendientes);

            \App\Services\AuditoriaService::log('anticipo_cliente.creado', $anticipo, [
                'origen'     => 'pos_pendiente_entrega',
                'venta_id'   => $venta->id,
                'cliente_id' => $venta->cliente_id,
                'monto'      => $montoPendiente,
                'items'      => count($itemsPendientes),
            ], $user);
        }
    }

    /**
     * Aplica un anticipo de efectivo (tipo 'monto') a la venta: crea la aplicación,
     * descuenta el saldo del anticipo y devuelve el monto efectivamente usado.
     * No genera movimiento de tesorería porque el dinero ya entró al crear el anticipo.
     */
    private function aplicarAnticipoAVenta(Venta $venta, int $anticipoId, User $user): float
    {
        $anticipo = ClienteAnticipo::where('id', $anticipoId)
            ->where('empresa_id', $venta->empresa_id)
            ->where('cliente_id', $venta->cliente_id)
            ->where('tipo_valorizacion', 'monto')
            ->where('estado', 'activo')
            ->lockForUpdate()
            ->first();

        if (!$anticipo) {
            abort(422, 'El anticipo no está disponible para este cliente.');
        }

        $saldo = (float) $anticipo->saldo;
        if ($saldo <= 0.009) {
            abort(422, 'El anticipo seleccionado no tiene saldo disponible.');
        }

        $total = round((float) $venta->total, 2);
        $montoUsado = min($saldo, $total);

        $anticipo->aplicaciones()->create([
            'empresa_id'  => $venta->empresa_id,
            'numero'      => ClienteAnticipoAplicacion::generarNumero($venta->empresa_id),
            'venta_id'    => $venta->id,
            'user_id'     => $user->id,
            'fecha'       => $venta->fecha_venta?->toDateString() ?? now()->toDateString(),
            'monto'       => $montoUsado,
            'observacion' => "Aplicado a venta {$venta->numero}",
        ]);

        $nuevoSaldo = round($saldo - $montoUsado, 2);
        $anticipo->update([
            'saldo'  => max(0, $nuevoSaldo),
            'estado' => $nuevoSaldo <= 0.01 ? 'aplicado' : 'activo',
        ]);

        \App\Services\AuditoriaService::log('anticipo_cliente.aplicado', $anticipo, [
            'venta_id'    => $venta->id,
            'monto'       => $montoUsado,
            'saldo'       => (float) $anticipo->saldo,
            'origen'      => 'pos_pago_venta',
        ], $user);

        return $montoUsado;
    }

    /**
     * Edita una venta COMPLETA dentro de los 3 min de creada (el guard de tiempo
     * lo aplica el controlador). Revierte por completo la versión anterior
     * (stock, tesorería, items, pagos, logs) y vuelve a aplicar el detalle nuevo,
     * conservando número, turno, caja, usuario y fecha originales.
     *
     * Para acotar el alcance, la edición NO cambia la moneda ni el flag de
     * crédito: se conservan los de la venta original.
     */
    public function actualizar(Venta $venta, array $data, User $user): Venta
    {
        $this->bloquearSiTieneComprobanteEmitido($venta); // V14

        return DB::transaction(function () use ($venta, $data, $user) {
            if ($venta->estado === 'anulada') {
                abort(422, 'No se puede editar una venta anulada.');
            }

            // Revertir anticipos de efectito aplicados a esta venta antes de
            // recalcular: así el saldo queda disponible y se re-aplica según
            // el nuevo payload (si viene anticipo_id).
            $this->revertirAnticiposDeVenta($venta, $user);

            // Pendiente por entregar en edición:
            //  - Si ya hubo ENTREGAS registradas (aplicaciones del anticipo),
            //    la edición se bloquea: el histórico de despachos y el stock
            //    ya movido quedarían desalineados. Anular y rehacer.
            //  - Si aún NO hay entregas, el anticipo vinculado se ANULA y se
            //    vuelve a crear desde el detalle nuevo (aplicarItemsPagos),
            //    devolviendo/ajustando el stock según el pendiente nuevo.
            $tieneEntregas = $venta->anticipos()
                ->whereIn('estado', ['activo', 'aplicado'])
                ->whereHas('aplicaciones')
                ->exists();
            if ($tieneEntregas) {
                abort(422, 'Esta venta tiene entregas de mercadería pendiente ya registradas. Para corregirla, anúlala y regístrala de nuevo.');
            }

            // V15 — Una venta con devoluciones ya registradas no se puede editar
            // borrando sus ítems: devoluciones_detalle referencia venta_item_id.
            // El bloqueo es temprano para no dejar caer un error de FK.
            $tieneDevoluciones = DB::table('devoluciones_detalle')
                ->join('devoluciones', 'devoluciones.id', '=', 'devoluciones_detalle.devolucion_id')
                ->whereIn('devoluciones_detalle.venta_item_id', $venta->items->pluck('id'))
                ->exists();
            if ($tieneDevoluciones) {
                abort(422, 'Esta venta tiene devoluciones registradas. Para corregirla, anúlala y regístrala de nuevo.');
            }

            $anticiposPendientes = $venta->anticipos()->where('estado', 'activo')->with('items')->get();

            $venta->loadMissing('items', 'local', 'turno.local');
            $turno   = $venta->turno ?? abort(422, 'La venta no tiene turno asociado.');
            // El stock se mueve en el almacén del local DE LA VENTA (no el del
            // editor): así un admin puede editar la venta de una cajera de otro
            // local sin desviar el inventario a su propio almacén.
            $almacen = $this->scope->almacenVentasDeLocal($venta->empresa_id, $venta->local_id)
                ?? $this->scope->almacenParaVentas($user)
                ?? abort(422, 'No se encontró un almacén de ventas para el local de la venta.');
            $permitirStockNegativo = $this->config->permiteStockNegativo($user->empresa_id);

            // 1) Revertir efectos de la versión anterior.
            // El stock pendiente por entregar NUNCA salió del almacén, así que
            // solo se restaura lo efectivamente descontado (llevado en la venta).
            $pendienteBasePorItem = [];
            foreach ($anticiposPendientes as $ant) {
                foreach ($ant->items as $ai) {
                    if ($ai->venta_item_id) {
                        $pendienteBasePorItem[$ai->venta_item_id] = ($pendienteBasePorItem[$ai->venta_item_id] ?? 0)
                            + round((float) $ai->cantidad_pendiente * (float) $ai->factor_conversion, 4);
                    }
                }
            }

            foreach ($venta->items as $item) {
                $producto = Producto::find($item->producto_id);
                if ($producto && $this->config->deboDescontarStock($producto, $venta->local)) {
                    $restaurar = round((float) $item->cantidad_base - ($pendienteBasePorItem[$item->id] ?? 0), 4);
                    if ($restaurar > 0.00009) {
                        Stock::ajustar($almacen->id, $producto->id, $restaurar, contexto: [
                            'tipo'            => 'venta_anulacion',
                            'referencia_tipo' => 'venta',
                            'referencia_id'   => $venta->id,
                            'fecha'           => now(),
                            'user_id'         => optional(auth()->user())->id,
                            'empresa_id'      => $venta->empresa_id,
                        ]); // restaurar
                    }
                }
            }

            // El anticipo anterior se anula; aplicarItemsPagos creará el nuevo
            // según el pendiente indicado en la edición (si lo hay). Al anular se
            // deja su pendiente en 0: si no, esos items quedan "vivos" y el
            // recálculo de stock/kardex los revive como mercadería fantasma.
            foreach ($anticiposPendientes as $ant) {
                $ant->items()->update(['cantidad_pendiente' => 0]);
                $ant->update(['estado' => 'anulado']);
                \App\Services\AuditoriaService::log('anticipo_cliente.anulado', $ant, [
                    'motivo' => "Edición de la venta {$venta->numero}: el pendiente se reemplaza por el detalle nuevo",
                ], $user);
            }

            $this->tesoreria->revertir('venta', $venta->id);
            $venta->items()->delete();
            $venta->pagos()->delete();
            DescuentoLog::where('venta_id', $venta->id)->delete();

            // 2) Cabecera editable (conservando moneda/crédito originales)
            $clienteId = $data['cliente_id']
                ?? Cliente::generalDeEmpresa($user->empresa_id)?->id
                ?? abort(422, 'No se encontró el Cliente General de la empresa.');

            $moneda     = strtoupper($venta->moneda ?? 'PEN') ?: 'PEN';
            $tipoCambio = $venta->tipo_cambio ? (float) $venta->tipo_cambio : null;
            $factor     = ($moneda !== 'PEN' && $tipoCambio) ? $tipoCambio : 1.0;
            $esCredito  = (bool) $venta->es_credito;

            $venta->update([
                'cliente_id'            => $clienteId,
                'tipo_comprobante'      => $data['tipo_comprobante'] ?? $venta->tipo_comprobante,
                'numero_comprobante'    => $data['numero_comprobante'] ?? $venta->numero_comprobante,
                'descuento_total'       => round((float) ($data['descuento_total'] ?? 0) * $factor, 2),
                'descuento_concepto_id' => $data['descuento_concepto_id'] ?? null,
                'observacion'           => $data['observacion'] ?? null,
                'subtotal'              => 0,
                'igv'                   => 0,
                'total'                 => 0,
            ]);

            // 3) Re-aplicar detalle nuevo
            $this->aplicarItemsPagos($venta, $data, $user, $turno, $almacen, $moneda, $tipoCambio, $factor, $esCredito, (int) $clienteId, $permitirStockNegativo);

            \App\Services\AuditoriaService::log('venta.editada', $venta, [
                'numero' => $venta->numero,
                'total'  => (float) $venta->fresh()->total,
            ], $user);

            return $venta->fresh(['items', 'pagos', 'cliente']);
        });
    }

    /**
     * Anula una venta y restaura el stock de los productos físicos.
     *
     * M21: $motivo es la justificación auditable de la anulación. El controller
     * la exige via AnularVentaRequest (mín 10 chars). Se acepta null solo en
     * llamadas internas/legacy; en ese caso el contexto de auditoría lo refleja.
     */
    public function anular(Venta $venta, User $user, ?string $motivo = null): void
    {
        $this->bloquearSiTieneComprobanteEmitido($venta); // V14

        DB::transaction(function () use ($venta, $user, $motivo) {
            if ($venta->estado === 'anulada') {
                throw new \RuntimeException('La venta ya está anulada.');
            }

            $venta->loadMissing('local');

            // Restaurar el stock en el almacén del local de la venta (no el del
            // usuario que anula: un admin puede anular ventas de otros locales).
            $almacen = $this->scope->almacenVentasDeLocal($venta->empresa_id, $venta->local_id)
                ?? $this->scope->almacenParaVentas($user)
                ?? abort(422, 'No se encontró un almacén de ventas para el local de la venta.');

            // Pendiente por entregar: lo aún NO entregado nunca salió del
            // almacén, así que NO se restaura. Mapear venta_item_id → cantidad
            // pendiente en unidades BASE de los anticipos activos vinculados.
            $pendienteBasePorItem = [];
            $anticiposPendientes  = $venta->anticipos()->where('estado', 'activo')->with('items')->get();
            foreach ($anticiposPendientes as $ant) {
                foreach ($ant->items as $ai) {
                    if ($ai->venta_item_id) {
                        $pendienteBasePorItem[$ai->venta_item_id] = ($pendienteBasePorItem[$ai->venta_item_id] ?? 0)
                            + round((float) $ai->cantidad_pendiente * (float) $ai->factor_conversion, 4);
                    }
                }
            }

            foreach ($venta->items as $item) {
                $producto = $item->producto;
                if ($producto && $this->config->deboDescontarStock($producto, $venta->local)) {
                    // Restaurar stock: entrada positiva (solo lo efectivamente entregado)
                    $restaurar = round((float) $item->cantidad_base - ($pendienteBasePorItem[$item->id] ?? 0), 4);
                    if ($restaurar > 0.00009) {
                        Stock::ajustar($almacen->id, $producto->id, $restaurar, contexto: [
                            'tipo'            => 'venta_anulacion',
                            'referencia_tipo' => 'venta',
                            'referencia_id'   => $venta->id,
                            'fecha'           => now(),
                            'user_id'         => optional(auth()->user())->id,
                            'empresa_id'      => $venta->empresa_id,
                        ]);
                    }
                }
            }

            // El pendiente muere con la venta: se anula el anticipo vinculado y
            // se deja su pendiente en 0 (si no, esos items quedan "vivos" y el
            // recálculo de stock/kardex los revive como mercadería fantasma).
            // Sin movimiento de tesorería propio (el dinero se revierte con los
            // pagos de la venta, abajo).
            foreach ($anticiposPendientes as $ant) {
                $ant->items()->update(['cantidad_pendiente' => 0]);
                $ant->update(['estado' => 'anulado']);
                \App\Services\AuditoriaService::log('anticipo_cliente.anulado', $ant, [
                    'motivo' => "Anulación de la venta {$venta->numero}",
                ], $user);
            }

            // F1 — Una venta anulada deja de ser cuenta por cobrar.
            $venta->update(['estado' => 'anulada', 'saldo_pendiente' => 0]);

            // F7 — Revertir los ingresos de tesorería de esta venta y sus abonos.
            $this->tesoreria->revertir('venta', $venta->id);
            foreach ($venta->abonos()->pluck('id') as $abonoId) {
                $this->tesoreria->revertir('venta_abono', (int) $abonoId);
            }

            // Revertir anticipos de efectivo aplicados a esta venta: se borra la
            // aplicación y se restaura el saldo del anticipo para que quede
            // disponible nuevamente.
            $this->revertirAnticiposDeVenta($venta, $user);

            \App\Services\AuditoriaService::log('venta.anulada', $venta, [
                'numero'           => $venta->numero,
                'total'            => (float) $venta->total,
                'tipo_comprobante' => $venta->tipo_comprobante,
                'turno_id'         => $venta->turno_id,
                'cliente_id'       => $venta->cliente_id,
                'motivo'           => $motivo,
            ], $user);
        });
    }

    /**
     * Al anular una venta, las aplicaciones de anticipo de efectito vinculadas a ella
     * se eliminan y el saldo del anticipo se restaura, quedando disponible de nuevo.
     */
    private function revertirAnticiposDeVenta(Venta $venta, User $user): void
    {
        $aplicaciones = ClienteAnticipoAplicacion::where('venta_id', $venta->id)
            ->whereHas('anticipo', fn ($q) => $q->where('tipo_valorizacion', 'monto'))
            ->with('anticipo')
            ->get();

        foreach ($aplicaciones as $aplicacion) {
            $anticipo = $aplicacion->anticipo;
            if (!$anticipo) continue;

            $monto = (float) $aplicacion->monto;
            $nuevoSaldo = round((float) $anticipo->saldo + $monto, 2);
            $anticipo->update([
                'saldo'  => $nuevoSaldo,
                'estado' => 'activo',
            ]);

            \App\Services\AuditoriaService::log('anticipo_cliente.reactivado', $anticipo, [
                'motivo'      => "Anulación de la venta {$venta->numero}",
                'monto'       => $monto,
                'saldo'       => $nuevoSaldo,
                'venta_id'    => $venta->id,
                'aplicacion_id' => $aplicacion->id,
            ], $user);

            $aplicacion->delete();
        }
    }

    /**
     * V14 — Guarda de facturación electrónica para anular() y actualizar().
     *
     * POR QUÉ: un comprobante que ya viajó a SUNAT (aceptado, en envío o en el
     * resumen diario del día) no se puede deshacer desde el POS. Borrar la
     * venta localmente dejaría declarada una operación que la empresa dice que
     * no existió: la contabilidad y la declaración mensual quedan fuera de
     * cuadre y el correlativo, huérfano. El único instrumento legal para
     * revertirlo es la Nota de Crédito.
     *
     * Es la ÚNICA conducta que cambia respecto de hoy, y cambia solo para las
     * ventas que efectivamente se emitieron: las `ticket` (hoy, el 100 % del
     * flujo) y las que tengan el comprobante en error/rechazado se comportan
     * exactamente igual que antes.
     */
    private function bloquearSiTieneComprobanteEmitido(Venta $venta): void
    {
        // La integración es opcional y se despliega por partes. La comprobación
        // anterior (`method_exists`) era código muerto: el método SIEMPRE existe
        // porque está definido en el modelo. Con la tabla sin crear —código
        // desplegado antes que la migración— esto reventaba con un 42P01 y la
        // cajera no podía anular una venta mal cobrada, con el cliente delante.
        //
        // `Schema::hasTable()` consulta el catálogo de la base, así que se cachea
        // por proceso: esto corre en cada anulación y edición.
        // OJO: aquí NO se consulta `facturamac.enabled`, a diferencia del resto de
        // guardas del módulo. Si una empresa emitió comprobantes y luego apaga la
        // integración, esas ventas SIGUEN informadas a SUNAT: dejar que se anulen
        // porque el interruptor está en off descuadraría la declaración. Lo único
        // que puede eximir de comprobar es que la tabla no exista, porque entonces
        // no se emitió nunca nada.
        static $tablaDisponible = null;

        $tablaDisponible ??= Schema::hasTable('venta_comprobantes');

        if (! $tablaDisponible) {
            return;
        }

        $ce = $venta->comprobanteElectronico()->first();
        if ($ce && $ce->esEmitido()) {
            abort(422, "La venta tiene el comprobante {$ce->numero} informado a SUNAT. "
                . 'Para revertirla emite una Nota de Crédito.');
        }
    }
}
