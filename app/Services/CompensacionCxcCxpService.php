<?php

namespace App\Services;

use App\Models\Entrada;
use App\Models\EntradaPago;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaAbono;
use Illuminate\Support\Str;

/**
 * Compensación entre Cuentas por Cobrar (ventas al crédito) y Cuentas por
 * Pagar (compras): cuando el mismo tercero es cliente y proveedor, lo que
 * nos debe se cancela contra lo que le debemos SIN mover dinero de caja.
 *
 * Mecánica (calcada del compensar de Deudas):
 *  - Se crea un VentaAbono y un EntradaPago por el MISMO monto, ambos sin
 *    método/cuenta y enlazados por compensacion_grupo_id.
 *  - CERO movimientos de tesorería: no entró ni salió dinero real.
 *  - Ambos saldos (venta y entrada) bajan por el monto compensado.
 *  - Los registros de compensación NO se editan (solo se anulan): anular un
 *    lado revierte automáticamente el otro para que nunca queden desparejados.
 *
 * Llamar siempre dentro de una DB::transaction.
 */
class CompensacionCxcCxpService
{
    /**
     * Registra la compensación. Devuelve [VentaAbono, EntradaPago].
     */
    public function crear(Venta $venta, Entrada $entrada, float $monto, string $fecha, ?string $observacion, User $user): array
    {
        $grupoId = (string) Str::uuid();

        $cliente = $venta->cliente?->razon_social
            ?: trim(($venta->cliente?->nombres ?? '') . ' ' . ($venta->cliente?->apellidos ?? ''));
        $proveedor = $entrada->proveedorRel?->razon_social
            ?? $entrada->proveedorRel?->nombre_comercial
            ?? $entrada->proveedor
            ?? 'proveedor';
        $refEntrada = $entrada->correlativo ?: ($entrada->numero_documento ?: "#{$entrada->id}");

        $abono = VentaAbono::create([
            'venta_id'                => $venta->id,
            'user_id'                 => $user->id,
            'fecha'                   => $fecha,
            'monto'                   => $monto,
            'observacion'             => trim("Compensación con compra {$refEntrada} — {$proveedor}. " . ($observacion ?? '')) ?: null,
            'compensacion_grupo_id'   => $grupoId,
            'compensacion_entrada_id' => $entrada->id,
        ]);

        $pagadoVenta = round((float) $venta->monto_pagado + $monto, 2);
        $venta->update([
            'monto_pagado'    => $pagadoVenta,
            'saldo_pendiente' => max(0, round((float) $venta->total - $pagadoVenta, 2)),
        ]);

        $pago = EntradaPago::create([
            'entrada_id'            => $entrada->id,
            'user_id'               => $user->id,
            'fecha'                 => $fecha,
            'monto'                 => $monto,
            'observacion'           => trim("Compensación con venta {$venta->numero} — {$cliente}. " . ($observacion ?? '')) ?: null,
            'compensacion_grupo_id' => $grupoId,
            'compensacion_venta_id' => $venta->id,
        ]);

        $entrada->aplicarPago($monto);

        AuditoriaService::log('compensacion.cxc_cxp', $venta, [
            'grupo'        => $grupoId,
            'monto'        => $monto,
            'venta'        => $venta->numero,
            'entrada'      => $refEntrada,
            'saldo_venta'  => (float) $venta->saldo_pendiente,
            'saldo_compra' => $entrada->saldoPendiente(),
        ], $user);

        return [$abono, $pago];
    }

    /**
     * Revierte el LADO VENTA de un grupo (al anular el EntradaPago compensado):
     * borra el abono hermano y la venta recupera su saldo pendiente.
     */
    public function revertirLadoVenta(string $grupoId, User $user): void
    {
        $abono = VentaAbono::where('compensacion_grupo_id', $grupoId)->first();
        if (!$abono) return; // ya revertido (idempotente)

        $venta  = $abono->venta;
        $pagado = round((float) $venta->monto_pagado - (float) $abono->monto, 2);
        $venta->update([
            'monto_pagado'    => max(0, $pagado),
            'saldo_pendiente' => max(0, round((float) $venta->total - $pagado, 2)),
        ]);

        AuditoriaService::log('compensacion.anulada_lado_venta', $venta, [
            'grupo' => $grupoId, 'monto' => (float) $abono->monto,
        ], $user);

        $abono->delete();
    }

    /**
     * Revierte el LADO COMPRA de un grupo (al anular el VentaAbono compensado):
     * borra el pago hermano y la compra recupera su saldo pendiente.
     */
    public function revertirLadoEntrada(string $grupoId, User $user): void
    {
        $pago = EntradaPago::where('compensacion_grupo_id', $grupoId)->first();
        if (!$pago) return; // ya revertido (idempotente)

        $entrada = $pago->entrada;
        $pagado  = round((float) $entrada->monto_pagado - (float) $pago->monto, 2);
        $entrada->update([
            'monto_pagado' => max(0, $pagado),
            'estado_pago'  => $pagado >= (float) $entrada->total - 0.01 ? 'pagado' : ($pagado > 0 ? 'parcial' : 'pendiente'),
        ]);

        AuditoriaService::log('compensacion.anulada_lado_compra', $entrada, [
            'grupo' => $grupoId, 'monto' => (float) $pago->monto,
        ], $user);

        $pago->delete();
    }
}
