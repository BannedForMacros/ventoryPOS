<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        $middleware->alias([
            'permiso'    => \App\Http\Middleware\CheckPermiso::class,
            'superadmin' => \App\Http\Middleware\EsSuperadmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Stock insuficiente → 422 con mensaje claro al usuario, sin stack trace.
        // En requests Inertia/web devuelve back()->withErrors para que el frontend
        // muestre el error en el formulario. En requests JSON puros responde 422.
        $exceptions->render(function (\App\Exceptions\InsufficientStockException $e, Request $request) {
            if ($request->expectsJson() && !$request->header('X-Inertia')) {
                return response()->json([
                    'message' => $e->getMessage(),
                    'errors'  => ['stock' => [$e->getMessage()]],
                ], 422);
            }

            return back()
                ->withErrors(['stock' => $e->getMessage()])
                ->withInput();
        });

        // Renderiza una página Inertia bonita en lugar del HTML por defecto de Laravel
        // para los errores que el usuario puede llegar a ver: 401, 403, 404, 419, 429, 500, 503.
        // Se respeta el modo debug en local (ahí seguirá apareciendo Whoops) y los requests
        // JSON/AJAX no-Inertia siguen recibiendo respuesta JSON estándar.
        $exceptions->respond(function (Response $response, \Throwable $exception, Request $request) {
            $status = $response->getStatusCode();

            if (!in_array($status, [401, 403, 404, 419, 429, 500, 503], true)) {
                return $response;
            }

            // No interceptar APIs/JSON puros para no romper integraciones (Decolecta, etc.).
            if ($request->expectsJson() && !$request->header('X-Inertia')) {
                return $response;
            }

            // En desarrollo, dejar que Whoops muestre el stack trace para los 500.
            if (app()->hasDebugModeEnabled() && $status === 500) {
                return $response;
            }

            // 419 (CSRF/sesión expirada) → mejor regresar con flash que mostrar página.
            if ($status === 419) {
                return back()->with('error', 'La página expiró. Vuelve a intentarlo.');
            }

            return Inertia::render('Errors/Error', [
                'status'  => $status,
                'message' => $exception->getMessage() ?: null,
            ])
                ->toResponse($request)
                ->setStatusCode($status);
        });
    })->create();
