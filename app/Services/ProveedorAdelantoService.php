<?php

namespace App\Services;

use App\Models\Entrada;
use App\Models\EntradaPago;
use App\Models\ProveedorAdelanto;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Operaciones de negocio de adelantos a proveedores.
 */
class ProveedorAdelantoService
{
    /**
     * Aplica un adelanto activo a una entrada (compra) y crea el pago correspondiente.
     *
     * Si la entrada está en estado 'borrador', el adelanto igualmente se descuenta:
     * el proveedor ya recibió el dinero y el pago queda registrado en la entrada.
     *
     * @param  int $proveedorAdelantoId  ID del adelanto a consumir.
     * @param  float $monto              Monto a aplicar (en moneda principal).
     * @param  Entrada $entrada          Compra/entrada a la que se abona.
     * @param  User|int $user            Usuario que registra la operación.
     * @param  string $fecha              Fecha del pago (no confundir con fecha de la entrada).
     * @param  int|string|null $turnoId  Turno al que se imputa (solo visual/afecta caja, el dinero ya salió).
     * @return EntradaPago               Pago creado vinculado al adelanto.
     *
     * @throws \Illuminate\Http\Exceptions\HttpResponseException si el adelanto no existe, no está activo,
     *         no pertenece al proveedor de la entrada o no tiene saldo suficiente.
     */
    public function aplicar(
        int $proveedorAdelantoId,
        float $monto,
        Entrada $entrada,
        User|int $user,
        string $fecha,
        int|string|null $turnoId = null,
    ): EntradaPago {
        return DB::transaction(function () use ($proveedorAdelantoId, $monto, $entrada, $user, $fecha, $turnoId) {
            $adelanto = ProveedorAdelanto::where('id', $proveedorAdelantoId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($adelanto->empresa_id !== $entrada->empresa_id) {
                throw ValidationException::withMessages(['proveedor_adelanto_id' => 'El adelanto no pertenece a esta empresa.']);
            }
            if ($adelanto->proveedor_id !== $entrada->proveedor_id) {
                throw ValidationException::withMessages(['proveedor_adelanto_id' => 'El adelanto no corresponde al proveedor de esta compra.']);
            }
            if ($adelanto->estado !== 'activo') {
                throw ValidationException::withMessages(['proveedor_adelanto_id' => 'El adelanto no está activo.']);
            }
            if ((float) $adelanto->saldo < $monto - 0.01) {
                throw ValidationException::withMessages(['monto' => 'El adelanto no tiene saldo suficiente.']);
            }

            $nuevoSaldo = round((float) $adelanto->saldo - $monto, 2);
            $adelanto->update([
                'saldo'  => max(0, $nuevoSaldo),
                'estado' => $nuevoSaldo <= 0.01 ? 'aplicado' : 'activo',
            ]);

            $adelanto->aplicaciones()->create([
                'entrada_id' => $entrada->id,
                'user_id'    => $user instanceof User ? $user->id : $user,
                'fecha'      => substr($fecha, 0, 10),
                'monto'      => $monto,
            ]);

            $pago = EntradaPago::create([
                'entrada_id'            => $entrada->id,
                'user_id'               => $user instanceof User ? $user->id : $user,
                'turno_id'              => $turnoId ?: null,
                'metodo_pago_id'        => null,
                'cuenta_id'             => null,
                'proveedor_adelanto_id' => $adelanto->id,
                'fecha'                 => substr($fecha, 0, 10),
                'monto'                 => $monto,
            ]);

            $entrada->aplicarPago($monto);

            return $pago;
        });
    }

    /**
     * Reversa el consumo de un adelanto en un pago de entrada, restaurando su saldo.
     * Se usa al editar/anular pagos ya registrados que consumían un adelanto.
     */
    public function revertirAplicacion(EntradaPago $pago): void
    {
        if (!$pago->proveedor_adelanto_id) {
            return;
        }

        $adelanto = ProveedorAdelanto::where('id', $pago->proveedor_adelanto_id)->lockForUpdate()->first();
        if (!$adelanto) {
            return;
        }

        $adelanto->update([
            'saldo'  => round((float) $adelanto->saldo + (float) $pago->monto, 2),
            'estado' => 'activo',
        ]);

        $adelanto->aplicaciones()
            ->where('entrada_id', $pago->entrada_id)
            ->where('monto', $pago->monto)
            ->orderByDesc('id')
            ->first()?->delete();
    }
}
