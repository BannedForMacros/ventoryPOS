<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware de autorización por permisos. Lee la matriz `permisos` (rol/módulo/acción)
 * y aborta con 403 si el usuario no tiene la acción permitida.
 *
 * Uso:
 *   permiso:catalogo.productos,editar       → verifica acción explícita
 *   permiso:catalogo.productos              → deriva la acción del verbo HTTP
 *
 * Mapeo automático verbo → acción:
 *   GET, HEAD    → ver
 *   POST         → crear
 *   PUT, PATCH   → editar
 *   DELETE       → eliminar
 *
 * Para acciones custom (anular, confirmar, recibir, aprobar, etc.) que usan POST
 * pero NO son "crear", pasar la acción explícita: permiso:devoluciones,editar.
 *
 * Bypass automático para roles con `es_admin = true`.
 */
class CheckPermiso
{
    private const ACCIONES = ['ver', 'crear', 'editar', 'eliminar'];

    public function handle(Request $request, Closure $next, string $modulo, ?string $accion = null): Response
    {
        $accion ??= $this->accionDesdeMetodo($request->method());

        if (!in_array($accion, self::ACCIONES, true)) {
            abort(500, "Acción inválida en middleware permiso: '{$accion}'.");
        }

        $user = $request->user();
        if (!$user) {
            abort(401);
        }

        if (!$user->tienePermiso($modulo, $accion)) {
            abort(403, "No tienes permiso para {$accion} en {$modulo}.");
        }

        return $next($request);
    }

    private function accionDesdeMetodo(string $metodo): string
    {
        return match (strtoupper($metodo)) {
            'GET', 'HEAD' => 'ver',
            'POST'        => 'crear',
            'PUT', 'PATCH'=> 'editar',
            'DELETE'      => 'eliminar',
            default       => 'ver',
        };
    }
}
