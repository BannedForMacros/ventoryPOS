<?php

namespace App\Services;

use App\Models\ClienteAnticipo;
use App\Models\ClienteAnticipoAplicacion;
use App\Models\ClienteAnticipoItem;
use App\Models\Stock;
use App\Models\User;
use App\Services\AuditoriaService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Registra entregas (totales o parciales) de mercadería que quedó pendiente
 * al vender (anticipo material vinculado a una venta). El stock no salió al
 * crear la venta; sale recién aquí, en el almacén del local de la venta.
 *
 * Este service es usado tanto por Finanzas → Anticipos como por la bandeja de
 * Despachos del almacenero.
 */
class EntregaPendienteService
{
    public function __construct(
        private LocalScopeService $scope,
        private ConfiguracionOperacionService $config,
    ) {}

    /**
     * Aplica una entrega parcial o total de un anticipo material multi-producto.
     *
     * @param ClienteAnticipo $anticipo Debe tener items cargados.
     * @param array $data Payload validado con: fecha, items.*.id, items.*.cantidad, observacion
     * @param User $user Usuario que confirma el despacho.
     * @throws ValidationException
     */
    public function aplicarEntregaMaterial(ClienteAnticipo $anticipo, array $data, User $user): void
    {
        $anticipo->loadMissing('items.producto', 'venta.local');
        $itemsAnticipo = $anticipo->items->keyBy('id');

        // ── Validar entregas contra lo pendiente de cada ítem ───────────
        $entregas = []; // [item => ClienteAnticipoItem, cantidad => float]
        foreach ($data['items'] as $idx => $linea) {
            $item = $itemsAnticipo->get((int) $linea['id']);
            if (!$item) {
                throw ValidationException::withMessages([
                    "items.{$idx}.id" => 'El ítem no pertenece a este despacho.',
                ]);
            }

            $cantidad  = round((float) $linea['cantidad'], 4);
            $pendiente = (float) $item->cantidad_pendiente;
            if ($cantidad <= 0.00009) {
                continue; // "lo demás lo dejamos"
            }

            if ($cantidad > $pendiente + 0.00009) {
                throw ValidationException::withMessages([
                    "items.{$idx}.cantidad" => "De «{$item->producto_nombre}» solo quedan pendientes " . rtrim(rtrim(number_format($pendiente, 4, '.', ''), '0'), '.') . ' por entregar.',
                ]);
            }

            $entregas[] = ['item' => $item, 'cantidad' => $cantidad];
        }

        if (empty($entregas)) {
            throw ValidationException::withMessages([
                'items' => 'Indica la cantidad a entregar de al menos un producto.',
            ]);
        }

        // El saldo (dinero) baja al valor PAGADO de lo entregado (precio
        // congelado de la venta), capado al saldo restante.
        $montoAplicado = min(
            round(collect($entregas)->sum(fn ($e) => $e['cantidad'] * (float) $e['item']->precio_unitario), 2),
            (float) $anticipo->saldo,
        );
        $totalUnidades = round(collect($entregas)->sum(fn ($e) => $e['cantidad']), 4);

        DB::transaction(function () use ($anticipo, $user, $data, $entregas, $montoAplicado, $totalUnidades) {
            $aplicacion = $anticipo->aplicaciones()->create([
                'empresa_id'  => $anticipo->empresa_id,
                'numero'      => ClienteAnticipoAplicacion::generarNumero($anticipo->empresa_id),
                'user_id'     => $user->id,
                'fecha'       => $data['fecha'],
                'monto'       => $montoAplicado,
                'cantidad'    => $totalUnidades,
                'venta_id'    => $anticipo->venta_id,
                'observacion' => $data['observacion'] ?? null,
            ]);

            // ── Stock: lo pendiente nunca salió del almacén; sale AHORA ──
            // (solo anticipos nacidos de una venta POS). Se usa el almacén
            // del local de la venta, igual que anular/editar venta.
            $almacen = null;
            $permitirNegativo = $this->config->permiteStockNegativo($user->empresa_id);
            if ($anticipo->venta_id && $anticipo->venta) {
                $almacen = $this->scope->almacenVentasDeLocal($anticipo->empresa_id, $anticipo->venta->local_id)
                    ?? abort(422, 'No se encontró un almacén de ventas para el local de la venta original.');
            }

            foreach ($entregas as $e) {
                /** @var ClienteAnticipoItem $item */
                $item = $e['item'];

                $aplicacion->items()->create([
                    'cliente_anticipo_item_id' => $item->id,
                    'cantidad'                 => $e['cantidad'],
                ]);

                $item->update([
                    'cantidad_pendiente' => max(0, round((float) $item->cantidad_pendiente - $e['cantidad'], 4)),
                ]);

                if ($almacen && $item->producto
                    && $this->config->deboDescontarStock($item->producto, $anticipo->venta->local)) {
                    $base = round($e['cantidad'] * (float) $item->factor_conversion, 4);
                    if ($base > 0.00009) {
                        Stock::ajustar($almacen->id, $item->producto_id, -$base, 0, $permitirNegativo, contexto: [
                            'tipo'            => 'entrega_pendiente',
                            'referencia_tipo' => 'venta',
                            'referencia_id'   => $anticipo->venta_id ?? optional($anticipo->venta)->id,
                            'fecha'           => now(),
                            'user_id'         => $user->id,
                            'empresa_id'      => $almacen->empresa_id,
                        ]);
                    }
                }
            }

            // Estado: aplicado cuando ya no queda nada por entregar.
            $anticipo->load('items');
            $agotado = $anticipo->items->every(fn ($i) => (float) $i->cantidad_pendiente <= 0.0001);

            $anticipo->update([
                'saldo'  => max(0, round((float) $anticipo->saldo - $montoAplicado, 2)),
                'estado' => $agotado ? 'aplicado' : 'activo',
            ]);

            AuditoriaService::log('anticipo_cliente.aplicado', $anticipo, [
                'monto'    => $montoAplicado,
                'cantidad' => $totalUnidades,
                'items'    => collect($entregas)->map(fn ($e) => [
                    'producto' => $e['item']->producto_nombre,
                    'cantidad' => $e['cantidad'],
                ])->all(),
                'fecha'    => $data['fecha'],
                'saldo'    => (float) $anticipo->saldo,
            ], $user);
        });
    }
}
