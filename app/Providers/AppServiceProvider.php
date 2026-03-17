<?php

namespace App\Providers;

use App\Models\Entrada;
use App\Models\Transferencia;
use App\Observers\EntradaObserver;
use App\Observers\TransferenciaObserver;
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
        Transferencia::observe(TransferenciaObserver::class);
    }
}
