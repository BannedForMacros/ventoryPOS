<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Reglas de "cuenta obligatoria" en los pagos.
 *
 * Un método de pago con cuentas vinculadas (pivote cuenta_metodo_pago) EXIGE que
 * el pago traiga una cuenta concreta: con 1 el front la autoselecciona, con 2+ el
 * usuario debe elegir. Los métodos SIN cuentas (efectivo, o electrónico sin
 * vincular) NO se exigen: TesoreriaService::resolverCuenta los resuelve solo
 * (efectivo → caja Efectivo; electrónico → auto-crea su cuenta).
 */
class PagoCuenta
{
    /** ¿El método tiene al menos una cuenta vinculada? (entonces exige elegirla). */
    public static function requiere(?int $metodoId): bool
    {
        if (! $metodoId) {
            return false;
        }
        return DB::table('cuenta_metodo_pago')->where('metodo_pago_id', $metodoId)->exists();
    }

    /**
     * Conjunto (id => true) de los métodos —de la lista dada— que tienen cuentas.
     * Para validar arrays de pagos con una sola query.
     *
     * @param  array<int|null>  $metodoIds
     * @return array<int, int>
     */
    public static function conCuenta(array $metodoIds): array
    {
        $ids = array_values(array_filter($metodoIds));
        if (empty($ids)) {
            return [];
        }
        return DB::table('cuenta_metodo_pago')
            ->whereIn('metodo_pago_id', $ids)
            ->pluck('metodo_pago_id')
            ->flip()
            ->all();
    }
}
