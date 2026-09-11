<?php

namespace App\Services;

use App\Models\Deuda;
use App\Models\DeudaPago;
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
     * Revierte el LADO DEUDA de un grupo: borra el movimiento hermano de la
     * deuda (préstamo) y su saldo se recalcula solo.
     */
    public function revertirLadoDeuda(string $grupoId, User $user): void
    {
        $pago = DeudaPago::where('compensacion_grupo_id', $grupoId)->first();
        if (!$pago) return; // ya revertido (idempotente)

        $deuda = $pago->deuda;
        AuditoriaService::log('compensacion.anulada_lado_deuda', $deuda, [
            'grupo' => $grupoId, 'monto' => (float) $pago->monto,
        ], $user);

        $pago->delete(); // soft delete: el módulo Deudas muestra eliminados con withTrashed
        $deuda?->recalcularSaldo();
    }

    /**
     * Revierte la contraparte de un VentaAbono compensado, sea cual sea:
     * un pago de COMPRA (CxC↔CxP) o un movimiento de DEUDA (CxC↔deuda).
     */
    public function revertirContraparteDesdeVenta(string $grupoId, User $user): void
    {
        if (EntradaPago::where('compensacion_grupo_id', $grupoId)->exists()) {
            $this->revertirLadoEntrada($grupoId, $user);
            return;
        }
        $this->revertirLadoDeuda($grupoId, $user);
    }

    /**
     * Revierte la contraparte de un EntradaPago compensado, sea cual sea:
     * un abono de VENTA (CxP↔CxC) o un movimiento de DEUDA (CxP↔deuda).
     */
    public function revertirContraparteDesdeEntrada(string $grupoId, User $user): void
    {
        if (VentaAbono::where('compensacion_grupo_id', $grupoId)->exists()) {
            $this->revertirLadoVenta($grupoId, $user);
            return;
        }
        $this->revertirLadoDeuda($grupoId, $user);
    }

    /**
     * Compensa una DEUDA POR PAGAR (le debemos un préstamo al tercero) contra
     * una VENTA al crédito de ese mismo tercero (nos debe como cliente).
     * Sin tesorería. Devuelve [DeudaPago, VentaAbono].
     */
    public function compensarDeudaConVenta(Deuda $deuda, Venta $venta, float $monto, string $fecha, ?string $observacion, User $user): array
    {
        $grupoId = (string) Str::uuid();

        $cliente = $venta->cliente?->razon_social
            ?: trim(($venta->cliente?->nombres ?? '') . ' ' . ($venta->cliente?->apellidos ?? ''));

        $pago = $deuda->pagos()->create([
            'user_id'               => $user->id,
            'fecha'                 => $fecha,
            'tipo'                  => 'amortizacion',
            'monto'                 => $monto,
            'observacion'           => trim("Compensación con venta {$venta->numero} — {$cliente}. " . ($observacion ?? '')) ?: null,
            'compensacion_grupo_id' => $grupoId,
            'compensacion_venta_id' => $venta->id,
        ]);
        $deuda->recalcularSaldo();

        $abono = VentaAbono::create([
            'venta_id'              => $venta->id,
            'user_id'               => $user->id,
            'fecha'                 => $fecha,
            'monto'                 => $monto,
            'observacion'           => trim("Compensación con deuda «{$deuda->nombre}». " . ($observacion ?? '')) ?: null,
            'compensacion_grupo_id' => $grupoId,
            'compensacion_deuda_id' => $deuda->id,
        ]);
        $pagado = round((float) $venta->monto_pagado + $monto, 2);
        $venta->update([
            'monto_pagado'    => $pagado,
            'saldo_pendiente' => max(0, round((float) $venta->total - $pagado, 2)),
        ]);

        AuditoriaService::log('compensacion.deuda_cxc', $deuda, [
            'grupo'       => $grupoId,
            'monto'       => $monto,
            'venta'       => $venta->numero,
            'saldo_deuda' => (float) $deuda->saldo,
            'saldo_venta' => (float) $venta->saldo_pendiente,
        ], $user);

        return [$pago, $abono];
    }

    /**
     * Compensa una DEUDA POR COBRAR (el tercero nos debe un préstamo) contra
     * una COMPRA con saldo a ese mismo tercero (le debemos como proveedor).
     * Sin tesorería. Devuelve [DeudaPago, EntradaPago].
     */
    public function compensarDeudaConEntrada(Deuda $deuda, Entrada $entrada, float $monto, string $fecha, ?string $observacion, User $user): array
    {
        $grupoId = (string) Str::uuid();

        $proveedor = $entrada->proveedorRel?->razon_social
            ?? $entrada->proveedorRel?->nombre_comercial
            ?? $entrada->proveedor
            ?? 'proveedor';
        $refEntrada = $entrada->correlativo ?: ($entrada->numero_documento ?: "#{$entrada->id}");

        $pago = $deuda->pagos()->create([
            'user_id'                 => $user->id,
            'fecha'                   => $fecha,
            'tipo'                    => 'amortizacion',
            'monto'                   => $monto,
            'observacion'             => trim("Compensación con compra {$refEntrada} — {$proveedor}. " . ($observacion ?? '')) ?: null,
            'compensacion_grupo_id'   => $grupoId,
            'compensacion_entrada_id' => $entrada->id,
        ]);
        $deuda->recalcularSaldo();

        $entradaPago = EntradaPago::create([
            'entrada_id'            => $entrada->id,
            'user_id'               => $user->id,
            'fecha'                 => $fecha,
            'monto'                 => $monto,
            'observacion'           => trim("Compensación con deuda «{$deuda->nombre}». " . ($observacion ?? '')) ?: null,
            'compensacion_grupo_id' => $grupoId,
            'compensacion_deuda_id' => $deuda->id,
        ]);
        $entrada->aplicarPago($monto);

        AuditoriaService::log('compensacion.deuda_cxp', $deuda, [
            'grupo'        => $grupoId,
            'monto'        => $monto,
            'entrada'      => $refEntrada,
            'saldo_deuda'  => (float) $deuda->saldo,
            'saldo_compra' => $entrada->saldoPendiente(),
        ], $user);

        return [$pago, $entradaPago];
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
