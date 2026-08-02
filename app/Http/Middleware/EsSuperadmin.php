<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protege el panel /admin del proveedor. Independiente del sistema de
 * roles/permisos por empresa (CheckPermiso): el superadmin no tiene rol de
 * tenant, y ningún rol de tenant —ni siquiera es_admin— abre esta puerta.
 */
class EsSuperadmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->es_superadmin, 403);

        return $next($request);
    }
}
