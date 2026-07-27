<?php

use App\Jobs\ConsultarEstadoComprobante;
use App\Models\VentaComprobante;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * Red de seguridad del polling de comprobantes electrónicos.
 *
 * POR QUÉ EXISTE: `ConsultarEstadoComprobante` se re-encola a sí mismo, pero esa
 * cadena se puede romper — el worker se cayó, la cola se vació al desplegar, o
 * el job agotó sus 3 intentos durante una caída larga de FacturaMac. Si eso pasa
 * nadie vuelve a preguntar y la boleta se queda en `pendiente_resumen` para
 * siempre, aunque SUNAT ya la haya aceptado esa misma noche.
 *
 * Cada hora se reencolan los comprobantes que llevan más de 2 h sin moverse.
 * Es idempotente y barato: consultar de más NO emite nada ante SUNAT.
 */
Schedule::call(function () {
    if (!config('facturamac.enabled') || !class_exists(VentaComprobante::class)) {
        return;
    }

    $comprobantes = VentaComprobante::query()
        ->whereIn('estado', ['pendiente_resumen', 'enviando'])
        ->whereNotNull('facturamac_id')
        ->where('updated_at', '<', now()->subHours(2))
        // Más allá de una semana el problema ya no es de espera: se revisa a mano.
        ->where('updated_at', '>', now()->subDays(7))
        ->limit(200)
        ->get(['id']);

    foreach ($comprobantes as $ce) {
        ConsultarEstadoComprobante::dispatch($ce->id);
    }

    if ($comprobantes->isNotEmpty()) {
        Log::info('Reencoladas consultas de comprobantes electrónicos pendientes', [
            'cantidad' => $comprobantes->count(),
        ]);
    }
})->hourly()->name('comprobantes:reencolar-consultas')->withoutOverlapping();
