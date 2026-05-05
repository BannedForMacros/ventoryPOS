<?php

namespace App\Providers;

use App\Models\Entrada;
use App\Models\Venta;
use App\Observers\EntradaObserver;
use App\Observers\VentaObserver;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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
