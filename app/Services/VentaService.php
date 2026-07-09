<?php

namespace App\Services;

use App\Models\Cliente;
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
            $almacen = $this->scope->almacenParaVentas($user)
                ?? abort(422, 'No se encontró un almacén de ventas configurado.');

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
                    : $this->tipoCambio->tasaPara(now()->toDateString(), $moneda);
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
                        'fecha_venta'           => now(),
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

            // Items
            foreach ($data['items'] as $itemData) {
                $unidad   = ProductoUnidad::findOrFail($itemData['producto_unidad_id']);
                $producto = Producto::findOrFail($itemData['producto_id']);

                $cantidad       = (float) $itemData['cantidad'];
                $cantidadBase   = round($cantidad * (float) $unidad->factor_conversion, 4);
                // Precios convertidos a soles al TC de la venta (factor=1 si PEN).
                $precioUnitario = round((float) $itemData['precio_unitario'] * $factor, 2);
                $descuentoItem  = round((float) ($itemData['descuento_item'] ?? 0) * $factor, 2);
                $subtotal       = round(($precioUnitario - $descuentoItem) * $cantidad, 2);

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
                ]);

                // Ajustar stock según configuración (producto → local → empresa)
                if ($this->config->deboDescontarStock($producto, $turno->local)) {
                    Stock::ajustar($almacen->id, $producto->id, -$cantidadBase);
                }

                // Log de descuento por item si corresponde
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

            // Log de descuento global si corresponde
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

            // Calcular totales antes de los pagos para saber el total real
            $venta->load('items');
            $venta->calcularTotales();
            $venta->refresh();

            // Pagos.
            // Sobrepago/vuelto: la fuente de verdad es `metodo.admite_vuelto` en BD,
            // no el flag del request. Calculamos el vuelto GLOBAL (suma de montos −
            // total de venta) y lo asignamos al primer pago cuyo metodo admita
            // vuelto. Asi soporta correctamente combinaciones como tarjeta exacta
            // + efectivo con vuelto (caso comun: el cliente paga tarjeta 80 + 30
            // efectivo para una venta de 100; el vuelto de 10 se persiste contra
            // la linea de efectivo).
            $pagos     = $data['pagos'] ?? []; // crédito puede venir sin pago inicial
            $metodoIds = collect($pagos)->pluck('metodo_pago_id')->unique()->all();
            $metodos   = \App\Models\MetodoPago::whereIn('id', $metodoIds)->get()->keyBy('id');

            // Montos convertidos a soles al TC de la venta (factor=1 si PEN).
            $totalPagado    = collect($pagos)->sum(fn($p) => round((float) $p['monto'] * $factor, 2));
            $vueltoGlobal   = max(0, round($totalPagado - (float) $venta->total, 2));
            $vueltoAsignado = false;

            foreach ($pagos as $pagoData) {
                $montoOrig    = (float) $pagoData['monto'];        // en moneda de venta
                $monto        = round($montoOrig * $factor, 2);    // soles
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

                // F7 — Tesorería: cada pago ingresa a su cuenta (neto de vuelto), en soles.
                $netoPen = round($monto - $vuelto, 2);
                $this->tesoreria->registrar(
                    $user->empresa_id,
                    $this->tesoreria->resolverCuenta($user->empresa_id, $pagoData['cuenta_metodo_pago_id'] ?? null, $pagoData['metodo_pago_id']),
                    $user,
                    now()->toDateString(),
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

            // F1 — Sincronizar cuenta por cobrar de la venta.
            // monto_pagado = dinero que realmente quedó en caja (sin el vuelto).
            $montoPagadoReal = round($totalPagado - $vueltoGlobal, 2);
            $venta->update([
                'monto_pagado'    => $esCredito ? $montoPagadoReal : (float) $venta->total,
                'saldo_pendiente' => $esCredito ? max(0, round((float) $venta->total - $montoPagadoReal, 2)) : 0,
                // Total original en moneda de venta (soles / TC); NULL para PEN.
                'monto_moneda'    => $moneda !== 'PEN' && $factor > 0 ? round((float) $venta->total / $factor, 2) : null,
            ]);

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
        DB::transaction(function () use ($venta, $user, $motivo) {
            if ($venta->estado === 'anulada') {
                throw new \RuntimeException('La venta ya está anulada.');
            }

            $almacen = $this->scope->almacenParaVentas($user)
                ?? abort(422, 'No se encontró un almacén de ventas configurado.');

            $venta->loadMissing('local');

            foreach ($venta->items as $item) {
                $producto = $item->producto;
                if ($producto && $this->config->deboDescontarStock($producto, $venta->local)) {
                    // Restaurar stock: entrada positiva
                    Stock::ajustar($almacen->id, $producto->id, (float) $item->cantidad_base);
                }
            }

            // F1 — Una venta anulada deja de ser cuenta por cobrar.
            $venta->update(['estado' => 'anulada', 'saldo_pendiente' => 0]);

            // F7 — Revertir los ingresos de tesorería de esta venta y sus abonos.
            $this->tesoreria->revertir('venta', $venta->id);
            foreach ($venta->abonos()->pluck('id') as $abonoId) {
                $this->tesoreria->revertir('venta_abono', (int) $abonoId);
            }

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
}
