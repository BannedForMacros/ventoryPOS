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
    public function __construct(private LocalScopeService $scope) {}

    /**
     * Registra una venta completa dentro de una transacción:
     * crea la cabecera, items, movimientos de stock, pagos y logs de descuento.
     */
    public function crear(array $data, User $user, Turno $turno): Venta
    {
        return DB::transaction(function () use ($data, $user, $turno) {
            $almacen = $this->scope->almacenParaVentas($user)
                ?? abort(422, 'No se encontró un almacén de ventas configurado.');

            // Si no se indicó cliente, usar el Cliente General (DNI 99999999) de la empresa
            $clienteId = $data['cliente_id']
                ?? Cliente::generalDeEmpresa($user->empresa_id)?->id
                ?? abort(422, 'No se encontró el Cliente General de la empresa.');

            // Cabecera de la venta
            $venta = Venta::create([
                'empresa_id'            => $user->empresa_id,
                'local_id'              => $turno->local_id,
                'turno_id'              => $turno->id,
                'caja_id'               => $turno->caja_id,
                'user_id'               => $user->id,
                'cliente_id'            => $clienteId,
                'numero'                => Venta::generarNumero($user->empresa_id),
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

                // Ajustar stock solo para productos físicos
                if ($producto->esProductoFisico()) {
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

            // Pagos
            foreach ($data['pagos'] as $pagoData) {
                $monto  = (float) $pagoData['monto'];
                $vuelto = isset($pagoData['es_efectivo']) && $pagoData['es_efectivo']
                    ? max(0, round($monto - (float) $venta->total, 2))
                    : 0;

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

            foreach ($venta->items as $item) {
                $producto = $item->producto;
                if ($producto && $producto->esProductoFisico()) {
                    // Restaurar stock: entrada positiva
                    Stock::ajustar($almacen->id, $producto->id, (float) $item->cantidad_base);
                }
            }

            $venta->update(['estado' => 'anulada']);
        });
    }
}
