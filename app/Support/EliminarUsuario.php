<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Borrar un usuario con historial reventaba con un 500: users es referenciado
 * por ~30 tablas (ventas, turnos, gastos, entradas, pagos...) casi todas con
 * FK RESTRICT. En vez de enumerarlas (y quedarse desfasado con la próxima
 * tabla), se intenta el borrado dentro de un savepoint y, si la base lo
 * rechaza por una FK (23503), se desactiva al usuario: conserva su historial
 * y ya no puede iniciar sesión (LoginRequest + middleware UsuarioActivo).
 */
final class EliminarUsuario
{
    /** @return bool true si se borró; false si se desactivó por tener historial. */
    public static function eliminarODesactivar(User $usuario): bool
    {
        try {
            DB::transaction(fn () => $usuario->delete());
            return true;
        } catch (QueryException $e) {
            if ($e->getCode() !== '23503') throw $e;
        }

        $usuario->update(['activo' => false]);
        return false;
    }
}
