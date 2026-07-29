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
        Vite::prefetch(concurrency: 3);

        Entrada::observe(EntradaObserver::class);
        Venta::observe(VentaObserver::class);
        // Transferencia: la lógica de stock vive en sus métodos (enviar/recibir/anular)
        // para soportar edición flexible en cualquier estado.
    }
}
