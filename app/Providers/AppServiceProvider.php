<?php

namespace App\Providers;

use App\Models\Entrada;
use App\Models\Venta;
use App\Observers\EntradaObserver;
use App\Observers\VentaObserver;
use App\Services\Facturacion\FacturacionEmpresa;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Quién lee la foto del cuaderno en el visor de ventas (cambiable por un modelo local).
        // VISOR_VENTAS_LECTOR=demo usa una página de ejemplo (nunca en producción).
        $this->app->bind(\App\Services\VisorVentas\LectorCuaderno::class, fn ($app) =>
            config('services.anthropic.lector') === 'demo' && ! $app->isProduction()
                ? new \App\Services\VisorVentas\LectorDemostracion()
                : new \App\Services\VisorVentas\LectorClaude());

        // SINGLETON a propósito: `FacturacionEmpresa` memoiza la conexión de cada
        // empresa y la existencia de la tabla, y esas dos preguntas se repiten
        // varias veces por pantalla (el POS pregunta por el modo, por el umbral y
        // por las series). Con una instancia nueva por resolución cada pantalla
        // volvería a la base y al catálogo de esquema sin necesidad.
        //
        // El alcance es el request (el contenedor se reconstruye en cada uno), así
        // que la memoria nunca sobrevive a un cambio hecho desde otra petición.
        $this->app->singleton(FacturacionEmpresa::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Visor de ventas: límites propios (no comparten el contador del buscador
        // del POS) y mensajes en palabras de la cajera.
        $muchas = fn () => response()->json(['message' => 'Vas muy rápido. Espera un minuto y vuelve a intentar.'], 429);
        \Illuminate\Support\Facades\RateLimiter::for('visor-ventas', fn ($r) =>
            \Illuminate\Cache\RateLimiting\Limit::perMinute(4)->by('visor:u' . $r->user()?->id)->response($muchas));
        \Illuminate\Support\Facades\RateLimiter::for('visor-verificar', fn ($r) =>
            \Illuminate\Cache\RateLimiting\Limit::perMinute(60)->by('visor-v:u' . $r->user()?->id)->response($muchas));

        Vite::prefetch(concurrency: 3);

        Entrada::observe(EntradaObserver::class);
        Venta::observe(VentaObserver::class);
        // Transferencia: la lógica de stock vive en sus métodos (enviar/recibir/anular)
        // para soportar edición flexible en cualquier estado.
    }
}
