<?php

use App\Jobs\ConsultarEstadoComprobante;
use App\Models\VentaComprobante;
use App\Services\Facturacion\FacturacionEmpresa;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Schema;

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
    // El scheduler corre sin sesión y sirve a TODAS las empresas de la instalación,
    // así que no puede preguntar por "la" empresa: recoge el `empresa_id` de la
    // venta de cada comprobante y decide uno a uno. Antes bastaba con mirar el flag
    // global `facturamac.enabled`; hoy cada contribuyente tiene su propio emisor y
    // reencolar los de una empresa apagada solo llenaría la cola de trabajo inútil.
    $facturacion = app(FacturacionEmpresa::class);

    if (! $facturacion->tablaDisponible() || ! Schema::hasTable('venta_comprobantes')) {
        return;
    }

    $comprobantes = VentaComprobante::query()
        ->join('ventas', 'ventas.id', '=', 'venta_comprobantes.venta_id')
        ->whereIn('venta_comprobantes.estado', ['pendiente_resumen', 'enviando'])
        ->whereNotNull('venta_comprobantes.facturamac_id')
        ->where('venta_comprobantes.updated_at', '<', now()->subHours(2))
        // Más allá de una semana el problema ya no es de espera: se revisa a mano.
        ->where('venta_comprobantes.updated_at', '>', now()->subDays(7))
        ->limit(200)
        ->get(['venta_comprobantes.id', 'ventas.empresa_id']);

    $encolados = 0;

    foreach ($comprobantes as $ce) {
        $empresaId = (int) $ce->empresa_id;

        // `activa()` memoiza por empresa: dos empresas = dos consultas, no 200.
        if (! $facturacion->activa($empresaId)) {
            continue;
        }

        ConsultarEstadoComprobante::dispatch($ce->id, $empresaId);
        $encolados++;
    }

    if ($encolados > 0) {
        Log::info('Reencoladas consultas de comprobantes electrónicos pendientes', [
            'cantidad' => $encolados,
        ]);
    }
})->hourly()->name('comprobantes:reencolar-consultas')->withoutOverlapping();
