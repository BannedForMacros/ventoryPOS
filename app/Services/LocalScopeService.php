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
     * Todos los almacenes activos de la empresa, sin filtrar por local del usuario.
     * Usado en transferencias, donde origen y destino pueden ser cualquier almacén.
     */
    public function todosLosAlmacenes(User $user): Collection
    {
        return Almacen::deEmpresa($user->empresa_id)
            ->activo()
            ->with('local')
            ->orderBy('tipo')
            ->orderBy('nombre')
            ->get();
    }

    /**
     * IDs de almacenes visibles — útil para filtrar queries directamente.
     */
    public function almacenIdsVisibles(User $user): array
    {
        return $this->almacenesVisibles($user)->pluck('id')->toArray();
    }

    /**
     * Almacenes válidos para registrar ENTRADAS (compras a proveedor).
     *
     * modo_simple:
     *   → El único almacén de la empresa (ahí entran las compras).
     *
     * central_y_local:
     *   → SOLO el almacén central. Las compras nunca ingresan directo a locales;
     *     se reciben en el central y luego se mueven a los locales vía transferencias.
     *
     * Los almacenes locales se abastecen exclusivamente por transferencias.
     */
    public function almacenesParaCompras(User $user): Collection
    {
        $user->loadMissing('empresa');
        $empresa = $user->empresa;

        if ($empresa->usaModoSimple()) {
            return $this->almacenesVisibles($user);
        }

        return Almacen::deEmpresa($empresa->id)
            ->activo()
            ->central()
            ->with('local')
            ->get();
    }

    /**
     * Almacenes que pueden ser ORIGEN de una transferencia (siempre tipo='central').
     */
    public function almacenesOrigenTransferencia(User $user): Collection
    {
        return Almacen::deEmpresa($user->empresa_id)
            ->activo()
            ->central()
            ->with('local')
            ->get();
    }

    /**
     * Almacenes que pueden ser DESTINO de una transferencia (siempre tipo='local').
     * Si el usuario tiene local_id, solo ve su propio local.
     */
    public function almacenesDestinoTransferencia(User $user): Collection
    {
        $query = Almacen::deEmpresa($user->empresa_id)
            ->activo()
            ->local()
            ->with('local');

        if ($user->local_id) {
            $query->where('local_id', $user->local_id);
        }

        return $query->get();
    }

    /**
     * Retorna el almacén desde el cual se debe descontar stock al registrar una venta.
     *
     * modo_simple:
     *   → Almacén único (tipo='local') ligado al local principal de la empresa.
     *     Las entradas entran aquí y las ventas se descuentan aquí mismo.
     *
     * central_y_local:
     *   → Almacén de tipo 'local' vinculado al local del usuario (cajero).
     *     Las entradas van al central, las transferencias abastecen los locales,
     *     y las ventas descuentan del almacén local del cajero.
     *
     * Retorna null si no se puede determinar (configuración incompleta).
     */
    public function almacenParaVentas(User $user): ?Almacen
    {
        $user->loadMissing('empresa');
        $empresa = $user->empresa;

        if ($empresa->usaModoSimple()) {
            // Modo simple: el único almacén es tipo='local' del local principal.
            // Si el usuario tiene local_id asignado, usar ese; si no, caer al único almacén activo.
            $query = Almacen::deEmpresa($empresa->id)->activo()->local();

            if ($user->local_id) {
                $query->where('local_id', $user->local_id);
            }

            return $query->first();
        }

        // Modo central_y_local: usar el almacén del local asignado al usuario
        if (!$user->local_id) {
            // Admin global sin local asignado: no puede vender directamente,
            // debe seleccionar un local explícitamente
            return null;
        }

        return Almacen::deEmpresa($empresa->id)
            ->activo()
            ->local()
            ->where('local_id', $user->local_id)
            ->first();
    }

    /**
     * Resuelve el almacén de ventas (tipo='local') de un local específico.
     * Se usa al editar/anular una venta: los movimientos de stock deben ir al
     * almacén DONDE se hizo la venta, no al del usuario que la edita (p. ej. un
     * admin sin local puede editar la venta de una cajera de otro local).
     */
    public function almacenVentasDeLocal(int $empresaId, ?int $localId): ?Almacen
    {
        if (!$localId) return null;

        return Almacen::deEmpresa($empresaId)
            ->activo()
            ->local()
            ->where('local_id', $localId)
            ->first();
    }

    /**
     * Retorna true si el usuario puede operar ventas (tiene almacén de ventas resuelto).
     * Útil para mostrar alertas de configuración en el frontend.
     */
    public function puedeVender(User $user): bool
    {
        return $this->almacenParaVentas($user) !== null;
    }
}
