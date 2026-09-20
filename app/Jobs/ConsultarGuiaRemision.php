<?php

namespace App\Jobs;

use App\Models\Guia;
use App\Services\Facturacion\FacturacionEmpresa;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Pregunta a FacturaMac en qué quedó una guía.
 *
 * SUNAT responde en diferido, así que una guía entregada puede tardar minutos en
 * resolverse. Mientras tanto NO ampara ningún traslado, y quien está en el almacén
 * necesita que la pantalla se entere sola.
 *
 * No se decide nada aquí: se copia lo que diga el emisor, incluido
 * `puede_trasladar`. Si esta clase dedujera el estado por su cuenta, tendríamos otra
 * vez dos listas que se desincronizan.
 */
class ConsultarGuiaRemision implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** Espaciado: SUNAT no responde más rápido por preguntarle más veces. */
    public array $backoff = [30, 60, 180, 600];

    public int $timeout = 30;

    public function __construct(public Guia $guia)
    {
    }

    public function handle(FacturacionEmpresa $facturacion): void
    {
        $guia = $this->guia->fresh();

        if ($guia === null || $guia->facturamac_id === null || ! $guia->esperaRespuesta()) {
            return;
        }

        try {
            $r = $facturacion->cliente((int) $guia->empresa_id)->consultarGuia($guia->facturamac_id);
        } catch (Throwable $e) {
            // Que no se pueda preguntar no dice NADA sobre la guía: FacturaMac y
            // SUNAT la tienen. No se toca el estado y se reintenta.
            Log::warning('Guía: no se pudo consultar', ['guia' => $guia->id, 'error' => $e->getMessage()]);

            throw $e;
        }

        $guia->update([
            'estado'            => $r->estado->value,
            'puede_trasladar'   => $r->puedeTrasladar,
            'aviso'             => $r->aviso,
            'sunat_codigo'      => $r->sunatCodigo,
            'sunat_descripcion' => $r->sunatDescripcion,
            'numero'            => $r->numeroCompleto ?: $guia->numero,
        ]);

        // Sigue en el aire: se vuelve a preguntar más tarde. El reintento del job lo
        // gestiona la cola con su backoff.
        if ($r->esperaRespuesta()) {
            self::dispatch($guia)->delay(now()->addMinutes(2));
        }
    }
}
