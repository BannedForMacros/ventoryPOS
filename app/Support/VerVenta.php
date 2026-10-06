<?php

namespace App\Support;

use App\Models\User;
use App\Models\Venta;

/**
 * ¿Puede este usuario ver esta venta (detalle, ticket, PDF, comprobante)?
 *
 * Mismo alcance que la lista de ventas: siempre de su empresa; con local
 * asignado, solo su local; y la cajera (no admin) solo las suyas. Esa última
 * regla NO aplica a quien tiene un permiso que ya le muestra ventas ajenas en
 * otra pantalla (Kardex, reportes, despachos…): esas pantallas enlazan al
 * detalle y un 403 ahí solo rompe el clic, no protege nada.
 *
 * A propósito NO cuentan `devoluciones,ver` (la cajera lo trae por defecto:
 * dejarlo pasar anularía la regla para todas) ni `ventas,editar` (editar o
 * anular en los 3 minutos es sobre lo propio, y su lista de ventas sigue
 * filtrada a lo suyo).
 */
final class VerVenta
{
    /** [módulo, acción] que dan visión de ventas de otros usuarios. */
    private const PERMISOS_AMPLIOS = [
        ['reportes.ventas', 'ver'],
        ['reportes.kardex', 'ver'],
        ['reportes.descuentos', 'ver'],
        ['reportes.devoluciones', 'ver'],
        ['reportes.utilidad', 'ver'],
        ['despachos', 'ver'],
        ['devoluciones', 'editar'],
    ];

    public static function puede(User $user, Venta $venta): bool
    {
        if ((int) $venta->empresa_id !== (int) $user->empresa_id) return false;
        if ($user->local_id && (int) $venta->local_id !== (int) $user->local_id) return false;
        if ($user->rol?->es_admin) return true;
        if ((int) $venta->user_id === (int) $user->id) return true;

        foreach (self::PERMISOS_AMPLIOS as [$modulo, $accion]) {
            if ($user->tienePermiso($modulo, $accion)) return true;
        }
        return false;
    }

    public static function autorizar(User $user, Venta $venta): void
    {
        abort_unless(self::puede($user, $venta), 403);
    }
}
