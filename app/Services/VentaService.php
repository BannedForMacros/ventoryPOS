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
                        'descuento_total'       => $data['descuento_total'] ?? 0,
                        'descuento_concepto_id' => $data['descuento_concepto_id'] ?? null,
                        'igv'                   => 0,
                        'total'                 => 0,
                        'estado'                => 'completada',
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
                $precioUnitario = (float) $itemData['precio_unitario'];
                $descuentoItem  = (float) ($itemData['descuento_item'] ?? 0);
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
            $metodoIds = collect($data['pagos'])->pluck('metodo_pago_id')->unique()->all();
            $metodos   = \App\Models\MetodoPago::whereIn('id', $metodoIds)->get()->keyBy('id');

            $totalPagado    = collect($data['pagos'])->sum(fn($p) => (float) $p['monto']);
            $vueltoGlobal   = max(0, round($totalPagado - (float) $venta->total, 2));
            $vueltoAsignado = false;

            foreach ($data['pagos'] as $pagoData) {
                $monto        = (float) $pagoData['monto'];
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
                ]);
            }

            return $venta->fresh(['items', 'pagos', 'cliente']);
        });
    }

    /**
     * Anula una venta y restaura el stock de los productos físicos.
     */
    public function anular(Venta $venta, User $user): void
    {
        DB::transaction(function () use ($venta, $user) {
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

            $venta->update(['estado' => 'anulada']);

            \App\Services\AuditoriaService::log('venta.anulada', $venta, [
                'numero'           => $venta->numero,
                'total'            => (float) $venta->total,
                'tipo_comprobante' => $venta->tipo_comprobante,
                'turno_id'         => $venta->turno_id,
                'cliente_id'       => $venta->cliente_id,
            ], $user);
        });
    }
}
