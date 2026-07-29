<?php

namespace App\Services;

use App\Models\Devolucion;
use App\Models\DevolucionMotivo;
use App\Models\Producto;
use App\Models\Turno;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class DevolucionService
{
    public function __construct(private ConfiguracionOperacionService $config) {}

    /**
     * Crea una devolución con sus detalles y pagos.
     *
     * @param array $data {
     *   venta_id, motivo_id, forma_reembolso, observacion?,
     *   items: [{venta_item_id, cantidad, estado_producto, restock?, motivo_id?, observacion?}],
     *   pagos: [{metodo_pago_id, cuenta_metodo_pago_id?, monto, referencia?}]
     * }
     */
    public function crear(array $data, User $user, ?Turno $turno = null): Devolucion
    {
        // La venta se resuelve fuera para poder pasarla al hook de la nota de
        // crédito una vez CONFIRMADA la transacción (ver más abajo).
        $venta = Venta::with('items.producto', 'local')->findOrFail($data['venta_id']);

        abort_if($venta->empresa_id !== $user->empresa_id, 403, 'La venta no pertenece a tu empresa.');

        $devolucion = DB::transaction(function () use ($data, $user, $turno, $venta) {

            $local  = $venta->local;
            abort_unless($this->config->permiteDevoluciones($local), 422,
                'Las devoluciones están deshabilitadas para este local.');

            // Validar plazo
            if (!$this->config->estaDentroDelPlazo($local, $venta->fecha_venta)) {
                throw ValidationException::withMessages([
                    'venta_id' => 'La venta excede el plazo máximo permitido para devoluciones.',
                ]);
            }

            // Validar cantidades disponibles por línea
            $this->validarCantidadesDevolverpiles($venta, $data['items']);

            $motivo = DevolucionMotivo::where('id', $data['motivo_id'])
                ->where('empresa_id', $user->empresa_id)
                ->firstOrFail();

            $requiereAprobacion = $this->config->requiereAprobacionDevolucion($local);

            $devolucion = Devolucion::create([
                'empresa_id'          => $user->empresa_id,
                'local_id'            => $venta->local_id,
                'turno_id'            => $turno?->id,
                'caja_id'             => $turno?->caja_id,
                'venta_id'            => $venta->id,
                'user_id'             => $user->id,
                'numero'              => $turno ? Devolucion::generarNumero($turno->id) : null,
                'fecha'               => now(),
                'motivo_id'           => $motivo->id,
                'forma_reembolso'     => $data['forma_reembolso'],
                'requiere_aprobacion' => $requiereAprobacion,
                'fue_aprobada'        => !$requiereAprobacion, // si no requiere, queda aprobada implícitamente
                'estado'              => $requiereAprobacion ? 'pendiente' : 'aprobada',
                'observacion'         => $data['observacion'] ?? null,
                'monto_devolucion'    => 0,
                'monto_reembolso'     => 0,
            ]);

            $totalDevolucion = 0.0;

            foreach ($data['items'] as $i) {
                $item = VentaItem::with('producto', 'productoUnidad')->findOrFail($i['venta_item_id']);
                abort_if($item->venta_id !== $venta->id, 422, 'El ítem no pertenece a esta venta.');

                $cantidad      = (float) $i['cantidad'];
                $factor        = (float) $item->factor_conversion;
                $cantidadBase  = round($cantidad * $factor, 4);
                $precioUnit    = (float) $item->precio_unitario;
                $subtotal      = round(($precioUnit - (float) $item->descuento_item) * $cantidad, 2);

                // Restock: respetar valor explícito si vino, si no usar default empresarial
                // y aplicar reglas del motivo (impide / obliga_merma fuerza false)
                $restock = $i['restock'] ?? $this->config->restockDefault($local);
                if ($motivo->afecta_restock_default !== 'permite') {
                    $restock = false;
                }
                // Producto no retornable: nunca restockea
                if (!$this->config->esRetornable($item->producto)) {
                    $restock = false;
                }

                $devolucion->detalles()->create([
                    'venta_item_id'      => $item->id,
                    'producto_id'        => $item->producto_id,
                    'producto_unidad_id' => $item->producto_unidad_id,
                    'cantidad'           => $cantidad,
                    'cantidad_base'      => $cantidadBase,
                    'precio_unitario'    => $precioUnit,
                    'subtotal'           => $subtotal,
                    'estado_producto'    => $i['estado_producto'] ?? 'bueno',
                    'restock'            => $restock,
                    'motivo_id'          => $i['motivo_id'] ?? $motivo->id,
                    'observacion'        => $i['observacion'] ?? null,
                ]);

                $totalDevolucion += $subtotal;
            }

            // Pagos del reembolso
            $totalReembolso = 0.0;
            if ($data['forma_reembolso'] !== 'sin_reembolso' && !empty($data['pagos'])) {
                foreach ($data['pagos'] as $p) {
                    $monto = (float) $p['monto'];
                    $devolucion->pagos()->create([
                        'metodo_pago_id'        => $p['metodo_pago_id'],
                        'cuenta_metodo_pago_id' => $p['cuenta_metodo_pago_id'] ?? null,
                        'monto'                 => $monto,
                        'referencia'            => $p['referencia'] ?? null,
                    ]);
                    $totalReembolso += $monto;
                }
            }

            $devolucion->update([
                'monto_devolucion' => round($totalDevolucion, 2),
                'monto_reembolso'  => round($totalReembolso, 2),
            ]);

            // Si no requiere aprobación, completar de una vez (aplica restock)
            if (!$requiereAprobacion) {
                $devolucion->refresh()->completar();
            }

            return $devolucion->fresh(['detalles', 'pagos', 'motivo', 'venta']);
        });

        // V15 — FUERA de la transacción, a propósito.
        //
        // Estaba dentro y su try/catch parecía suficiente, pero en PostgreSQL una
        // consulta fallida deja la transacción en estado abortado: el `fresh()` de
        // arriba reventaba con 25P02 y el rollback se llevaba la devolución ENTERA.
        // El stock no volvía y el reembolso no se registraba, después de haberle
        // devuelto el dinero al cliente.
        //
        // Aquí la devolución ya está confirmada y es intocable, que es la regla de
        // oro: un fallo al emitir la nota de crédito no puede deshacer una
        // operación de caja que ya ocurrió.
        $this->encolarNotaCredito($devolucion, $venta, $user);

        return $devolucion;
    }

    /**
     * V15 — Si la venta tiene un comprobante informado a SUNAT, la devolución
     * exige una Nota de Crédito: lo devuelto no puede seguir declarado.
     *
     * CRÍTICO — esto es un HOOK, no un paso de la devolución:
     *  · Se encola `afterCommit`, así que la NC nunca corre dentro de la
     *    transacción ni puede hacerla fallar.
     *  · Si la NC falla, la devolución NO se revierte. El stock ya volvió y el
     *    dinero ya salió de la caja; deshacer eso porque SUNAT no respondió
     *    dejaría al cliente sin su plata en el mostrador. El job registra el
     *    error y lo audita para que el admin lo resuelva.
     *  · Incluso el propio dispatch va en try/catch: ni un fallo de la cola
     *    puede tumbar una operación de caja.
     */
    private function encolarNotaCredito(Devolucion $devolucion, Venta $venta, User $user): void
    {
        try {
            // La integración es opcional y se despliega por partes.
            if (!method_exists($venta, 'comprobanteElectronico')) {
                return;
            }

            $ce = $venta->comprobanteElectronico()->first();
            if (!$ce || !$ce->esEmitido()) {
                return; // ticket o comprobante nunca emitido: nada que acreditar
            }

            \App\Jobs\EmitirNotaCreditoElectronica::dispatch($devolucion->id, $user->id)
                ->afterCommit();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('No se pudo encolar la nota de crédito de la devolución', [
                'devolucion_id' => $devolucion->id,
                'venta_id'      => $venta->id,
                'error'         => $e->getMessage(),
            ]);
        }
    }

    protected function validarCantidadesDevolverpiles(Venta $venta, array $items): void
    {
        $devueltoPorItem = DB::table('devoluciones_detalle')
            ->join('devoluciones', 'devoluciones.id', '=', 'devoluciones_detalle.devolucion_id')
            ->where('devoluciones.venta_id', $venta->id)
            ->whereIn('devoluciones.estado', ['pendiente', 'aprobada', 'completada'])
            ->select('devoluciones_detalle.venta_item_id', DB::raw('SUM(devoluciones_detalle.cantidad) as total'))
            ->groupBy('devoluciones_detalle.venta_item_id')
            ->pluck('total', 'venta_item_id');

        foreach ($items as $i) {
            $vi = $venta->items->firstWhere('id', $i['venta_item_id']);
            if (!$vi) {
                throw ValidationException::withMessages([
                    'items' => "El ítem {$i['venta_item_id']} no pertenece a esta venta.",
                ]);
            }

            $yaDevuelto = (float) ($devueltoPorItem[$vi->id] ?? 0);
            $disponible = (float) $vi->cantidad - $yaDevuelto;
            $solicitado = (float) $i['cantidad'];

            if ($solicitado > $disponible + 0.0001) {
                $nombre = $vi->producto_nombre;
                throw ValidationException::withMessages([
                    'items' => "Cantidad excedida para \"{$nombre}\". Disponible para devolver: {$disponible}, solicitado: {$solicitado}.",
                ]);
            }
        }
    }
}
