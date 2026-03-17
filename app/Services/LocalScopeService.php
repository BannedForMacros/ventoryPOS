<?php

namespace App\Services;

use App\Models\Almacen;
use App\Models\Local;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class LocalScopeService
{
    /**
     * Almacenes visibles para el usuario.
     * - Con local_id asignado → solo almacenes de ese local
     * - Sin local_id (admin global) → todos los almacenes de la empresa
     */
    public function almacenesVisibles(User $user): Collection
    {
        $query = Almacen::deEmpresa($user->empresa_id)->activo();

        if ($user->local_id) {
            $query->where('local_id', $user->local_id);
        }

        return $query->with('local')->orderBy('nombre')->get();
    }

    /**
     * Locales visibles para el usuario.
     * - Con local_id asignado → solo su local
     * - Sin local_id (admin global) → todos los locales de la empresa
     */
    public function localesVisibles(User $user): Collection
    {
        $query = Local::where('empresa_id', $user->empresa_id)
                      ->where('activo', true);

        if ($user->local_id) {
            $query->where('id', $user->local_id);
        }

        return $query->orderBy('nombre')->get();
    }

    /**
     * Verifica que el usuario pueda acceder a un almacén.
     * Protege los endpoints show/edit/delete/confirmar.
     */
    public function puedeAccederAlmacen(User $user, Almacen $almacen): bool
    {
        if ($almacen->empresa_id !== $user->empresa_id) {
            return false;
        }

        if ($user->local_id && $almacen->local_id !== $user->local_id) {
            return false;
        }

        return true;
    }

    /**
     * Retorna true cuando el usuario ve más de 1 local.
     * El frontend usa esto para decidir si mostrar el selector de almacén/local.
     */
    public function mostrarSelectorLocal(User $user): bool
    {
        return $this->localesVisibles($user)->count() > 1;
    }

    /**
     * IDs de almacenes visibles — útil para filtrar queries directamente.
     */
    public function almacenIdsVisibles(User $user): array
    {
        return $this->almacenesVisibles($user)->pluck('id')->toArray();
    }
}
