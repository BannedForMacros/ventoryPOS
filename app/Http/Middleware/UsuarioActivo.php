<?php

namespace App\Http\Middleware;

use App\Http\Requests\Auth\LoginRequest;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Corta una sesión ya abierta cuando el usuario o su empresa se desactivan.
 *
 * El login ya rechaza a los desactivados (LoginRequest), pero quien estaba
 * dentro seguía operando hasta cerrar sesión: desactivar a un cajero o, desde
 * /admin, a una empresa entera no surtía efecto. Corre en todo el grupo web;
 * a un invitado lo deja pasar sin más.
 *
 * El superadmin no tiene empresa: solo cuenta su propio `activo`.
 */
class UsuarioActivo
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        $mensaje = null;
        if (! $user->activo) {
            $mensaje = LoginRequest::MENSAJE_USUARIO_INACTIVO;
        } elseif (! $user->es_superadmin && $user->empresa_id && ! $user->empresa?->activo) {
            $mensaje = LoginRequest::MENSAJE_EMPRESA_INACTIVA;
        }

        if ($mensaje === null) {
            return $next($request);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['message' => $mensaje], 401);
        }

        return redirect()->route('login')->withErrors(['email' => $mensaje]);
    }
}
