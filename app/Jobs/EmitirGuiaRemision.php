<?php

namespace App\Jobs;

use App\Models\Guia;
use App\Services\Facturacion\FacturacionEmpresa;
use App\Services\Facturacion\FacturaMacException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use MacSoft\Facturacion\Contrato\Dto\RespuestaGuia;
use Throwable;

/**
 * Manda a FacturaMac una guía ya creada aquí.
 *
 * ─── POR QUÉ ES UN JOB ─────────────────────────────────────────────────────────
 *
 * Mismo motivo que la emisión de comprobantes: quien despacha no puede quedarse
 * mirando una pantalla mientras SUNAT decide. La guía se registra SIEMPRE; el envío
 * ocurre detrás y se reintenta.
 *
 * ─── ESTE JOB NO TOCA EL DESPACHO ──────────────────────────────────────────────
 *
 * Corolario innegociable, igual que en las ventas: pase lo que pase con SUNAT, el
 * despacho ni se revierte ni se modifica. A lo sumo queda una guía en error, visible
 * y reintentable.
 *
 * ─── Y TERMINA SIN AUTORIZAR NADA ──────────────────────────────────────────────
 *
 * Una guía entregada NO ampara todavía ningún traslado: SUNAT responde en diferido.
 * Lo que este job deja es `puede_trasladar` tal y como lo mandó el emisor —nunca
 * deducido aquí— y si viene en `false`, la pantalla lo dice con todas las letras.
 */
class EmitirGuiaRemision implements ShouldQueue
{
    use Queueable;

    /** Cubre SUNAT intermitente sin martillar el servicio. */
    public int $tries = 5;

    public array $backoff = [10, 30, 120, 600];

    /**
     * 60 s de techo: el POST solo crea la guía y encola el envío del lado de allá,
     * así que no debería tardar nada. Si tarda más, conviene liberar el worker.
     */
    public int $timeout = 60;

    public function __construct(public Guia $guia)
    {
    }

    public function handle(FacturacionEmpresa $facturacion): void
    {
        $guia = $this->guia->fresh();

        if ($guia === null) {
            return;
        }

        // Idempotencia local: si FacturaMac ya le puso número, no se vuelve a mandar.
        // Protege contra un re-dispatch accidental —doble clic en «reintentar», el
        // scheduler, una edición— que consumiría un segundo número de SUNAT.
        if ($guia->facturamac_id !== null) {
            return;
        }

        $empresaId = (int) $guia->empresa_id;

        if (! $facturacion->activa($empresaId)) {
            return;
        }

        try {
            $respuesta = $facturacion->cliente($empresaId)->emitirGuia($guia->payload ?? []);
        } catch (FacturaMacException $e) {
            $this->marcarError($guia, $e->getMessage(), $e->reintentable);

            return;
        } catch (Throwable $e) {
            $this->marcarError($guia, $e->getMessage(), true);

            return;
        }

        $this->guardar($guia, $respuesta);
    }

    /** Lo que contestó el emisor, guardado tal cual. */
    private function guardar(Guia $guia, RespuestaGuia $r): void
    {
        $guia->update([
            'facturamac_id'     => $r->id,
            'serie'             => $r->serie,
            'correlativo'       => $r->numero,
            'numero'            => $r->numeroCompleto,
            'estado'            => $r->estado->value,
            // Resuelto por el emisor. Aquí NO se calcula.
            'puede_trasladar'   => $r->puedeTrasladar,
            'aviso'             => $r->aviso,
            'sunat_codigo'      => $r->sunatCodigo,
            'sunat_descripcion' => $r->sunatDescripcion,
            'error'             => null,
            'enviado_at'        => now(),
            'intentos'          => $guia->intentos + 1,
        ]);

        // Una guía entregada todavía espera el veredicto de SUNAT. Se vuelve a
        // preguntar en medio minuto, que es lo que suele tardar.
        if ($r->esperaRespuesta()) {
            ConsultarGuiaRemision::dispatch($guia)->delay(now()->addSeconds(30));
        }
    }

    private function marcarError(Guia $guia, string $mensaje, bool $reintentable): void
    {
        $guia->update([
            'estado'   => $reintentable ? 'error_envio' : Guia::ESTADO_ERROR_MAPEO,
            'error'    => $mensaje,
            'intentos' => $guia->intentos + 1,
            // Ante la duda, NO. Un fallo de envío nunca autoriza a cargar el camión.
            'puede_trasladar' => false,
        ]);

        Log::warning('Guía no emitida', [
            'guia'    => $guia->id,
            'empresa' => $guia->empresa_id,
            'error'   => $mensaje,
        ]);
    }

    public function failed(Throwable $e): void
    {
        $guia = $this->guia->fresh();

        if ($guia !== null && $guia->facturamac_id === null) {
            $this->marcarError($guia, $e->getMessage(), true);
        }
    }
}
