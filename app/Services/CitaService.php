<?php

namespace App\Services;

use App\Models\Cita;
use App\Models\CitaItem;
use App\Models\Cliente;
use App\Models\Producto;
use App\Models\ProductoUnidad;
use App\Models\Turno;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Orquesta el ciclo de vida de una Cita. Mantiene la maquina de estados
 * simple y registra cada transicion en auditoria.
 *
 * Estados validos y transiciones permitidas:
 *
 *   programada  ──▶ confirmada / en_atencion / cancelada / no_asistio
 *   confirmada  ──▶ en_atencion / cancelada / no_asistio
 *   en_atencion ──▶ completada (vinculando una Venta) / cancelada
 *   completada  ──▶ (final, no transiciona)
 *   cancelada   ──▶ (final)
 *   no_asistio  ──▶ (final)
 */
class CitaService
{
    public function __construct(
        private VentaService $ventaService,
    ) {}

    // ── CREAR ─────────────────────────────────────────────────────────────

    /**
     * Crea una cita con sus items. Snapshot de precio al momento de agendar.
     *
     * @param array $data {
     *   local_id, cliente_id, fecha_hora, profesional_id?, observaciones?,
     *   sujeto_nombre?, sujeto_descripcion?,
     *   items: [{producto_id, producto_unidad_id, cantidad, duracion_min?, observaciones?}]
     * }
     */
    public function crear(array $data, User $user): Cita
    {
        return DB::transaction(function () use ($data, $user) {
            // Validar cliente pertenece a la empresa del user (multi-tenant guard)
            $cliente = Cliente::where('id', $data['cliente_id'])
                ->where('empresa_id', $user->empresa_id)
                ->firstOrFail();

            $cita = Cita::create([
                'empresa_id'          => $user->empresa_id,
                'local_id'            => $data['local_id'],
                'cliente_id'          => $cliente->id,
                'profesional_id'      => $data['profesional_id'] ?? null,
                'created_by'          => $user->id,
                'numero'              => Cita::generarNumero($user->empresa_id),
                'fecha_hora'          => $data['fecha_hora'],
                'duracion_min'        => 0, // se recalcula al final con la suma de items
                'estado'              => Cita::ESTADO_PROGRAMADA,
                'observaciones'       => $data['observaciones'] ?? null,
                'sujeto_nombre'       => $data['sujeto_nombre'] ?? null,
                'sujeto_descripcion'  => $data['sujeto_descripcion'] ?? null,
            ]);

            $duracionTotal = 0;
            foreach ($data['items'] as $i => $itemData) {
                $unidad = ProductoUnidad::with('producto')
                    ->where('id', $itemData['producto_unidad_id'])
                    ->firstOrFail();

                // Multi-tenant guard sobre el producto
                if ($unidad->producto->empresa_id !== $user->empresa_id) {
                    abort(403, 'Producto no pertenece a la empresa.');
                }

                $duracion = (int) ($itemData['duracion_min'] ?? $this->duracionDefaultPorProducto($unidad->producto));
                $duracionTotal += $duracion * (int) ($itemData['cantidad'] ?? 1);

                CitaItem::create([
                    'cita_id'            => $cita->id,
                    'producto_id'        => $unidad->producto_id,
                    'producto_unidad_id' => $unidad->id,
                    'cantidad'           => $itemData['cantidad'] ?? 1,
                    'duracion_min'       => $duracion,
                    'precio_estimado'    => (float) $unidad->precio_venta, // snapshot
                    'observaciones'      => $itemData['observaciones'] ?? null,
                    'orden'              => $i,
                ]);
            }

            $cita->update(['duracion_min' => $duracionTotal ?: 30]);
            $cita->refresh()->load('items');

            AuditoriaService::log('cita.creada', $cita, [
                'numero'        => $cita->numero,
                'cliente_id'    => $cita->cliente_id,
                'fecha_hora'    => $cita->fecha_hora->toDateTimeString(),
                'duracion_min'  => $cita->duracion_min,
                'items'         => count($data['items']),
                'sujeto'        => $cita->sujeto_nombre,
            ], $user);

            return $cita;
        });
    }

    /** Heuristica para sugerir una duracion por defecto (configurable a futuro). */
    private function duracionDefaultPorProducto(Producto $producto): int
    {
        // Por ahora todos los servicios y productos asumen 30 min.
        // En el futuro se puede agregar productos.duracion_default_min.
        return 30;
    }

    // ── TRANSICIONES DE ESTADO ────────────────────────────────────────────

    public function confirmar(Cita $cita, User $user): void
    {
        $this->guardEstado($cita, [Cita::ESTADO_PROGRAMADA], 'confirmar');

        $cita->update([
            'estado'        => Cita::ESTADO_CONFIRMADA,
            'confirmada_at' => now(),
        ]);

        AuditoriaService::log('cita.confirmada', $cita, [
            'numero'     => $cita->numero,
            'fecha_hora' => $cita->fecha_hora->toDateTimeString(),
        ], $user);
    }

    public function iniciar(Cita $cita, User $user): void
    {
        $this->guardEstado($cita, [
            Cita::ESTADO_PROGRAMADA, Cita::ESTADO_CONFIRMADA,
        ], 'iniciar atencion');

        $cita->update([
            'estado'      => Cita::ESTADO_EN_ATENCION,
            'iniciada_at' => now(),
        ]);

        AuditoriaService::log('cita.iniciada', $cita, [
            'numero' => $cita->numero,
        ], $user);
    }

    public function cancelar(Cita $cita, string $motivo, User $user): void
    {
        $this->guardEstado($cita, [
            Cita::ESTADO_PROGRAMADA, Cita::ESTADO_CONFIRMADA, Cita::ESTADO_EN_ATENCION,
        ], 'cancelar');

        $cita->update([
            'estado'             => Cita::ESTADO_CANCELADA,
            'cancelada_at'       => now(),
            'motivo_cancelacion' => $motivo,
        ]);

        AuditoriaService::log('cita.cancelada', $cita, [
            'numero'         => $cita->numero,
            'motivo'         => $motivo,
            'estado_previo'  => $cita->getOriginal('estado'),
        ], $user);
    }

    public function marcarNoAsistio(Cita $cita, User $user): void
    {
        $this->guardEstado($cita, [
            Cita::ESTADO_PROGRAMADA, Cita::ESTADO_CONFIRMADA,
        ], 'marcar como no asistio');

        $cita->update([
            'estado'       => Cita::ESTADO_NO_ASISTIO,
            'cancelada_at' => now(), // reusamos la columna como "fecha de cierre del flujo"
        ]);

        AuditoriaService::log('cita.no_asistio', $cita, [
            'numero' => $cita->numero,
        ], $user);
    }

    // ── COMPLETAR Y COBRAR ───────────────────────────────────────────────

    /**
     * Completa la cita generando una Venta a partir de los datos del POS.
     * El payload de venta puede tener items distintos (ajustes en el momento):
     * la cita y sus items quedan intactos, la venta lleva lo realmente cobrado.
     *
     * @param array $ventaPayload Igual estructura que VentaService::crear acepta
     *                            (items, pagos, descuento_total, etc.).
     */
    public function completarYCobrar(Cita $cita, array $ventaPayload, User $user, Turno $turno): Venta
    {
        $this->guardEstado($cita, [
            Cita::ESTADO_PROGRAMADA, Cita::ESTADO_CONFIRMADA, Cita::ESTADO_EN_ATENCION,
        ], 'completar y cobrar');

        if ($cita->venta_id) {
            throw new LogicException('Esta cita ya tiene una venta asociada.');
        }

        return DB::transaction(function () use ($cita, $ventaPayload, $user, $turno) {
            // 1) Forzamos cliente de la cita (no permitimos cambiar el dueno al cobrar)
            $ventaPayload['cliente_id'] = $cita->cliente_id;

            // 2) Inyectamos contexto del sujeto en la observacion de la venta
            //    si la venta no trae observacion propia.
            if (empty($ventaPayload['observacion']) && $cita->sujeto_nombre) {
                $ventaPayload['observacion'] = "Cita #{$cita->numero} — {$cita->sujeto_nombre}";
            }

            // 3) Crear la venta (delega en VentaService: stock, pagos, descuentos, etc.)
            $venta = $this->ventaService->crear($ventaPayload, $user, $turno);

            // 4) Marcar la cita como completada y vincular
            $cita->update([
                'estado'        => Cita::ESTADO_COMPLETADA,
                'completada_at' => now(),
                'venta_id'      => $venta->id,
            ]);

            AuditoriaService::log('cita.completada', $cita, [
                'numero'        => $cita->numero,
                'venta_id'      => $venta->id,
                'venta_numero'  => $venta->numero,
                'venta_total'   => (float) $venta->total,
                'items_reservados' => $cita->items()->count(),
                'items_vendidos'   => $venta->items()->count(),
            ], $user);

            return $venta;
        });
    }

    /**
     * Vincula una cita activa con una venta ya creada (flujo "completar y cobrar"
     * desde el POS prellenado). Marca la cita como completada.
     *
     * Diferente a completarYCobrar(): aqui la venta YA EXISTE (creada por el POS),
     * solo asociamos. completarYCobrar crea la venta internamente.
     */
    public function vincularVenta(Cita $cita, Venta $venta, User $user): void
    {
        if ($cita->venta_id) {
            throw new LogicException('Esta cita ya tiene una venta asociada.');
        }
        $this->guardEstado($cita, [
            Cita::ESTADO_PROGRAMADA, Cita::ESTADO_CONFIRMADA, Cita::ESTADO_EN_ATENCION,
        ], 'vincular venta');

        if ($venta->empresa_id !== $cita->empresa_id) {
            throw new LogicException('La venta y la cita pertenecen a empresas distintas.');
        }

        DB::transaction(function () use ($cita, $venta, $user) {
            $cita->update([
                'estado'        => Cita::ESTADO_COMPLETADA,
                'completada_at' => now(),
                'venta_id'      => $venta->id,
            ]);

            AuditoriaService::log('cita.completada', $cita, [
                'numero'           => $cita->numero,
                'venta_id'         => $venta->id,
                'venta_numero'     => $venta->numero,
                'venta_total'      => (float) $venta->total,
                'items_reservados' => $cita->items()->count(),
                'items_vendidos'   => $venta->items()->count(),
                'via'              => 'pos_directo',
            ], $user);
        });
    }

    // ── HELPERS ──────────────────────────────────────────────────────────

    /**
     * Aborta con LogicException si la cita no esta en uno de los estados permitidos.
     */
    private function guardEstado(Cita $cita, array $estadosPermitidos, string $accionDescripcion): void
    {
        if (!in_array($cita->estado, $estadosPermitidos, true)) {
            throw new LogicException(sprintf(
                'No se puede %s una cita en estado "%s". Estados permitidos: %s.',
                $accionDescripcion,
                $cita->estado_label,
                implode(', ', $estadosPermitidos),
            ));
        }
    }
}
