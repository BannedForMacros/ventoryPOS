<?php

namespace App\Services;

use App\Models\Auditoria;
use App\Models\ClienteAnticipo;
use App\Models\Stock;
use App\Models\Turno;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Support\Facades\DB;

/**
 * Restablecer una venta anulada por error (solo el administrador).
 *
 * Deshace EXACTAMENTE lo que hizo la anulación (VentaService::anular): vuelve a
 * descontar el stock, vuelve a registrar el dinero de sus pagos con la fecha de
 * la venta y, si era a crédito, vuelve la deuda del cliente. La venta queda
 * como el día que se cobró.
 *
 * Lo que la anulación desarmó y no se puede rearmar sin adivinar (anticipos
 * usados, pendientes por entregar, cobros de crédito, comprobante electrónico)
 * no se restablece: se explica por qué y se pide registrar la venta de nuevo.
 */
class RestablecerVenta
{
    public function __construct(
        private LocalScopeService $scope,
        private ConfiguracionOperacionService $config,
        private TesoreriaService $tesoreria,
    ) {}

    /** Por qué esta venta NO se puede restablecer, o null si sí se puede. */
    public function motivoBloqueo(Venta $venta): ?string
    {
        if ($venta->estado !== 'anulada') {
            return 'La venta no está anulada.';
        }
        $nueva = ' Si hace falta, regístrala de nuevo en el POS.';

        // Con comprobante NO se restablece (regla del dueño): una boleta o factura
        // —electrónica o emitida por fuera— anulada ya no vale ante SUNAT, y
        // revivir la venta dejaría ingresos sin documento. Solo tickets / notas de venta.
        $tieneComprobante = $venta->tipo_comprobante !== 'ticket'
            || (\Illuminate\Support\Facades\Schema::hasTable('venta_comprobantes') && $venta->comprobanteElectronico()->exists());
        if ($tieneComprobante) {
            return 'Esta venta tiene comprobante (boleta o factura): una venta con comprobante no se puede restablecer.' . $nueva;
        }
        if ($venta->abonos()->exists()) {
            return 'Tenía cobros de crédito: esos cobros se revirtieron al anularla.' . $nueva;
        }
        if (ClienteAnticipo::where('venta_id', $venta->id)->exists()
            || ClienteAnticipo::where('venta_origen_id', $venta->id)->exists()) {
            return 'Tenía productos pendientes por entregar o un saldo a favor: la anulación los cerró.' . $nueva;
        }
        $usoAnticipo = Auditoria::where('accion', 'anticipo_cliente.reactivado')
            ->where('empresa_id', $venta->empresa_id)
            ->where('contexto->venta_id', $venta->id)
            ->exists();
        if ($usoAnticipo) {
            return 'Se pagó con un anticipo del cliente: al anularla el anticipo volvió a quedar disponible.' . $nueva;
        }

        // Si se anuló ANTES de cerrar el turno, esa caja se cuadró sin esta venta:
        // devolverle el dinero ahora la descuadraría.
        $turno = $venta->turno_id ? Turno::find($venta->turno_id, ['id', 'estado', 'fecha_cierre']) : null;
        if ($turno && $turno->estado === 'cerrado') {
            $anulada = Auditoria::where('accion', 'venta.anulada')
                ->where('modelo_tipo', Venta::class)->where('modelo_id', $venta->id)
                ->latest('id')->value('created_at');
            if (! $anulada || ($turno->fecha_cierre && \Illuminate\Support\Carbon::parse($anulada)->lt($turno->fecha_cierre))) {
                return 'Se anuló antes de cerrar su turno: esa caja ya se cuadró sin esta venta.' . $nueva;
            }
        }

        return null;
    }

    /** @throws \RuntimeException */
    public function ejecutar(Venta $venta, User $user, string $motivo): void
    {
        abort_unless((bool) $user->rol?->es_admin, 403, 'Solo un administrador puede restablecer una venta anulada.');

        DB::transaction(function () use ($venta, $user, $motivo) {
            // Releer bloqueada: dos restablecimientos a la vez descontarían dos veces.
            $fresca = Venta::whereKey($venta->id)->lockForUpdate()->first();
            $venta->setRawAttributes($fresca->getAttributes(), true);
            if ($bloqueo = $this->motivoBloqueo($venta)) {
                throw new \RuntimeException($bloqueo);
            }
            $venta->loadMissing('local', 'items.producto', 'pagos.metodoPago');

            // 1) Stock: sale otra vez, con la fecha de la venta (igual que al cobrarla).
            $almacen = $this->scope->almacenVentasDeLocal($venta->empresa_id, $venta->local_id)
                ?? $this->scope->almacenParaVentas($user)
                ?? abort(422, 'No se encontró un almacén de ventas para el local de la venta.');
            $permitirNegativo = $this->config->permiteStockNegativo($venta->empresa_id);
            foreach ($venta->items as $item) {
                if ($item->producto && $this->config->deboDescontarStock($item->producto, $venta->local)
                    && (float) $item->cantidad_base > 0.00009) {
                    Stock::ajustar($almacen->id, $item->producto_id, -(float) $item->cantidad_base, 0, $permitirNegativo, contexto: [
                        'tipo'            => 'venta',
                        'referencia_tipo' => 'venta',
                        'referencia_id'   => $venta->id,
                        'fecha'           => $venta->fecha_venta ?? now(),
                        'user_id'         => $user->id,
                        'empresa_id'      => $venta->empresa_id,
                    ]);
                }
            }

            // 2) Dinero: si la anulación dejó un contra-asiento (turno ya cerrado),
            //    se quita; si borró los ingresos, se vuelven a registrar tal cual.
            if ($this->tesoreria->revertir('venta_anulacion', $venta->id) === 0) {
                foreach ($venta->pagos as $p) {
                    $neto = round((float) $p->monto - (float) $p->vuelto, 2);
                    if ($neto <= 0.009) continue;
                    $this->tesoreria->registrar(
                        $venta->empresa_id,
                        $this->tesoreria->resolverCuenta($venta->empresa_id, $p->cuenta_metodo_pago_id, $p->metodo_pago_id),
                        $user,
                        $venta->fecha_venta?->toDateString() ?? now()->toDateString(),
                        'ingreso',
                        $neto,
                        "Venta {$venta->numero} — " . ($p->metodoPago?->nombre ?? 'pago') . ' (restablecida)',
                        'venta',
                        $venta->id,
                        $p->moneda ?? 'PEN',
                        $p->tipo_cambio !== null ? (float) $p->tipo_cambio : null,
                        $p->moneda && $p->moneda !== 'PEN' && $p->monto_moneda !== null ? (float) $p->monto_moneda : null,
                    );
                }
            }

            // 3) La venta vuelve a estar viva; a crédito, vuelve la deuda (sin cobros).
            $venta->update([
                'estado'          => 'completada',
                'saldo_pendiente' => $venta->es_credito ? round(max(0, (float) $venta->total - (float) $venta->monto_pagado), 2) : 0,
            ]);

            AuditoriaService::log('venta.restablecida', $venta, [
                'numero' => $venta->numero,
                'total'  => (float) $venta->total,
                'motivo' => $motivo,
            ], $user);
        });
    }
}
