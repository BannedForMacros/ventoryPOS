<?php

namespace App\Services;

use App\Models\Cuenta;
use App\Models\CuentaMovimiento;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * F7 — Tesorería: registra y consulta los movimientos de dinero por cuenta.
 *
 * Regla de oro: NINGÚN movimiento sin origen. Todo ingreso/egreso referencia
 * la operación que lo generó (ref_tipo + ref_id); los ajustes manuales
 * llevan motivo y quedan en auditoría.
 */
class TesoreriaService
{
    /** Cuenta "Efectivo" de la empresa (se crea si no existe). */
    public static function efectivo(int $empresaId): Cuenta
    {
        return Cuenta::firstOrCreate(
            ['empresa_id' => $empresaId, 'es_efectivo' => true],
            ['nombre' => 'Efectivo', 'activo' => true],
        );
    }

    /**
     * Resuelve a qué cuenta va un pago del POS/módulos:
     *  1. El pivot cuenta_metodo_pago elegido en el pago.
     *  2. Si el método tiene cuenta(s) vinculada(s), la primera.
     *  3. Método tipo efectivo → caja Efectivo.
     *  4. Método electrónico SIN cuenta (Yape sin configurar, etc.) →
     *     se CREA automáticamente una cuenta con el nombre del método y
     *     se vincula. El dinero de Yape nunca cae a "Efectivo": no es
     *     billete físico, y el cliente no tiene por qué configurar nada.
     */
    public function resolverCuenta(int $empresaId, ?int $cuentaMetodoPagoId, ?int $metodoPagoId): int
    {
        if ($cuentaMetodoPagoId) {
            $cuentaId = DB::table('cuenta_metodo_pago')->where('id', $cuentaMetodoPagoId)->value('cuenta_id');
            if ($cuentaId) return (int) $cuentaId;
        }

        if ($metodoPagoId) {
            $cuentas = DB::table('cuenta_metodo_pago')->where('metodo_pago_id', $metodoPagoId)->pluck('cuenta_id');
            if ($cuentas->count() >= 1) return (int) $cuentas->first();

            $metodo = \App\Models\MetodoPago::with('tipo:id,slug')->find($metodoPagoId);
            if ($metodo && $metodo->tipo?->slug !== 'efectivo') {
                // Auto-crear la cuenta del método y vincularla (una sola vez).
                $cuenta = Cuenta::firstOrCreate(
                    ['empresa_id' => $empresaId, 'nombre' => $metodo->nombre],
                    ['activo' => true, 'es_efectivo' => false],
                );
                $yaVinculada = DB::table('cuenta_metodo_pago')
                    ->where('cuenta_id', $cuenta->id)->where('metodo_pago_id', $metodo->id)->exists();
                if (!$yaVinculada) {
                    DB::table('cuenta_metodo_pago')->insert([
                        'cuenta_id' => $cuenta->id, 'metodo_pago_id' => $metodo->id,
                    ]);
                }

                return $cuenta->id;
            }
        }

        return self::efectivo($empresaId)->id;
    }

    public function registrar(
        int $empresaId,
        ?int $cuentaId,
        User|int $user,
        string $fecha,
        string $tipo,           // 'ingreso' | 'egreso'
        float $monto,           // SIEMPRE en soles (PEN)
        string $descripcion,
        ?string $refTipo = null,
        ?int $refId = null,
        // Trío multimoneda (opcional, aditivo): el monto ya viene convertido a
        // soles; esto solo deja rastro del original en moneda extranjera.
        string $moneda = 'PEN',
        ?float $tipoCambio = null,
        ?float $montoMoneda = null,
    ): ?CuentaMovimiento {
        if (round($monto, 2) <= 0) return null; // sin monto no hay movimiento

        return CuentaMovimiento::create([
            'empresa_id'   => $empresaId,
            'cuenta_id'    => $cuentaId ?? self::efectivo($empresaId)->id,
            'user_id'      => $user instanceof User ? $user->id : $user,
            'fecha'        => substr($fecha, 0, 10),
            'tipo'         => $tipo,
            'monto'        => round($monto, 2),
            'descripcion'  => mb_substr($descripcion, 0, 250),
            'ref_tipo'     => $refTipo,
            'ref_id'       => $refId,
            'moneda'       => strtoupper($moneda) ?: 'PEN',
            'tipo_cambio'  => $tipoCambio,
            'monto_moneda' => $montoMoneda,
        ]);
    }

    /** Elimina los movimientos de una operación revertida (venta anulada, gasto borrado...). */
    public function revertir(string $refTipo, int $refId): int
    {
        return CuentaMovimiento::where('ref_tipo', $refTipo)->where('ref_id', $refId)->delete();
    }

    /** Saldo de una cuenta hasta una fecha inclusive (o total si es null). */
    public function saldo(int $cuentaId, ?string $hastaFecha = null): float
    {
        $q = CuentaMovimiento::where('cuenta_id', $cuentaId);
        if ($hastaFecha) {
            $q->where('fecha', '<=', substr($hastaFecha, 0, 10));
        }

        return round((float) $q->selectRaw("COALESCE(SUM(CASE WHEN tipo = 'ingreso' THEN monto ELSE -monto END), 0) as s")->value('s'), 2);
    }

    /**
     * Ajuste manual de saldo: el usuario cuenta el dinero real y el sistema
     * genera el movimiento por la DIFERENCIA, con motivo obligatorio.
     * Así el número cuadra sin perder la trazabilidad.
     */
    public function ajustarSaldo(Cuenta $cuenta, User $user, string $fecha, float $saldoReal, string $motivo): ?CuentaMovimiento
    {
        $saldoActual = $this->saldo($cuenta->id, $fecha);
        $diferencia  = round($saldoReal - $saldoActual, 2);

        if (abs($diferencia) < 0.01) return null; // ya cuadra

        $mov = $this->registrar(
            $cuenta->empresa_id,
            $cuenta->id,
            $user,
            $fecha,
            $diferencia > 0 ? 'ingreso' : 'egreso',
            abs($diferencia),
            "Ajuste de saldo: {$motivo}",
            'ajuste',
            null,
        );

        AuditoriaService::log('tesoreria.ajuste', $mov, [
            'cuenta'       => $cuenta->nombre,
            'saldo_previo' => $saldoActual,
            'saldo_real'   => $saldoReal,
            'diferencia'   => $diferencia,
            'motivo'       => $motivo,
        ], $user);

        return $mov;
    }
}
