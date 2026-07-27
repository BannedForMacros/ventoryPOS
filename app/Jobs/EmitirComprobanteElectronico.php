<?php

namespace App\Jobs;

use App\Exceptions\MapeoComprobanteException;
use App\Models\Venta;
use App\Models\VentaComprobante;
use App\Services\Facturacion\FacturaMacClient;
use App\Services\Facturacion\FacturaMacException;
use App\Services\Facturacion\VentaAComprobante;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * V6 — Emite el comprobante electrónico (boleta 03 / factura 01) de una venta
 * YA CERRADA contra FacturaMac.
 *
 * POR QUÉ ES UN JOB Y NO UNA LLAMADA SÍNCRONA EN EL `store`:
 * si SUNAT tarda 40 s o está caído, el cajero no puede quedarse esperando con
 * la cola de clientes delante. La venta se cierra SIEMPRE; el comprobante se
 * emite en segundo plano y se reintenta. El corolario es innegociable: este
 * job NUNCA revierte, anula ni modifica la venta. Una venta cobrada en caja no
 * se deshace porque SUNAT falle — a lo sumo queda un comprobante en error,
 * visible y reintentable.
 *
 * Reintentos: 5 intentos con backoff 10s / 30s / 2min / 10min. Cubre el caso
 * real (SUNAT intermitente en hora punta) sin martillar el servicio.
 */
class EmitirComprobanteElectronico implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /**
     * 60 s de techo: el POST a FacturaMac solo crea el comprobante y encola el
     * envío a SUNAT del lado de allá, así que no debería tardar nada. Si tarda
     * más, es que algo está mal y conviene liberar el worker y reintentar.
     */
    public int $timeout = 60;

    /** Backoff exponencial entre reintentos (segundos). */
    public array $backoff = [10, 30, 120, 600];

    public function __construct(public Venta $venta) {}

    public function handle(VentaAComprobante $mapper, FacturaMacClient $client): void
    {
        // Interruptor global: con la integración apagada el POS funciona
        // exactamente como antes (todo el flujo histórico es `ticket`).
        if (!config('facturamac.enabled')) {
            return;
        }

        $venta = $this->venta;

        // El `ticket` es nota de venta INTERNA: no se informa a SUNAT.
        if ($venta->tipo_comprobante === 'ticket') {
            return;
        }

        // Idempotencia local: si el comprobante ya está informado a SUNAT no se
        // vuelve a emitir jamás. Protege contra un re-dispatch accidental
        // (doble click en "reintentar", scheduler, edición de la venta).
        $ce = $venta->comprobanteElectronico()->first();
        if ($ce && $ce->esEmitido()) {
            return;
        }

        $ce = $this->marcarEnviando($venta, $ce);

        // ── Mapeo ────────────────────────────────────────────────────────────
        // Errores de mapeo (delta de céntimos fuera de umbral, tasa de IGV
        // distinta de 18, cliente sin RUC en una factura…) son DEFECTOS DE
        // DATOS: reintentar no los arregla, solo quema cola. Se cortan aquí.
        $payload = null;
        $usarReintento = $ce->facturamac_id && $ce->estado === 'rechazado';
        if (!$usarReintento) {
            try {
                $payload = $mapper->mapear($venta);
            } catch (MapeoComprobanteException $e) {
                $this->registrarFallo($ce, 'error_mapeo', $e->getMessage());
                Log::error('Comprobante electrónico: error de mapeo (no reintentable)', [
                    'venta_id' => $venta->id,
                    'numero'   => $venta->numero,
                    'error'    => $e->getMessage(),
                ]);
                $this->fail($e);

                return;
            }
        }

        // ── Envío ────────────────────────────────────────────────────────────
        try {
            // G11 — Un comprobante RECHAZADO ya consumió su correlativo en
            // FacturaMac. Reenviarlo por /reintentar reutiliza ese mismo
            // correlativo; volver a emitir dejaría un hueco en la numeración.
            $respuesta = $usarReintento
                ? $client->reintentar((int) $ce->facturamac_id)
                : $client->emitir($payload);
        } catch (FacturaMacException $e) {
            if (!$e->reintentable) {
                // 422: SUNAT/FacturaMac rechazan el contenido. Insistir daría el
                // mismo resultado; queda `rechazado` para que el admin corrija
                // y use el botón de reintentar.
                $this->registrarFallo($ce, 'rechazado', $e->getMessage());
                Log::warning('Comprobante electrónico rechazado (no reintentable)', [
                    'venta_id' => $venta->id,
                    'numero'   => $venta->numero,
                    'error'    => $e->getMessage(),
                ]);
                $this->fail($e);

                return;
            }

            // 5xx / timeout: la culpa es del transporte, no del contenido.
            // Se relanza para que la cola aplique el backoff.
            throw $e;
        }

        $this->persistirRespuesta($ce, $respuesta);

        // Boleta (03): SUNAT la conoce recién con el Resumen Diario de las
        // 23:55, así que el estado real llega horas después → polling.
        // Factura (01): FacturaMac la envía en su propio job; el CDR tarda
        // segundos, pero tampoco es síncrono → también se consulta.
        if (in_array($ce->estado, ['pendiente_resumen', 'enviando'], true) && $ce->facturamac_id) {
            ConsultarEstadoComprobante::dispatch($ce->id)
                ->delay(ConsultarEstadoComprobante::proximaConsulta($ce->estado, 0));
        }
    }

    /**
     * Crea (o reutiliza) la fila de seguimiento y la deja en `enviando` ANTES
     * de salir a la red: si el proceso muere a mitad del POST, la venta queda
     * con evidencia de que hubo un intento en vuelo.
     */
    private function marcarEnviando(Venta $venta, ?VentaComprobante $ce): VentaComprobante
    {
        $ce ??= new VentaComprobante();
        $ce->venta_id = $venta->id;
        $ce->tipo     = $venta->tipo_comprobante === 'factura' ? '01' : '03';

        // G6 — La clave de idempotencia se fija UNA sola vez y no cambia entre
        // reintentos: es lo único que impide que un timeout de red convierta
        // un reintento en un segundo comprobante fiscal real.
        $ce->idempotency_key ??= $venta->idempotency_key ?: ('venta-' . $venta->id);

        $ce->estado   = 'enviando';
        $ce->intentos = (int) $ce->intentos + 1;
        $ce->error    = null;
        $ce->save();

        return $ce;
    }

    private function persistirRespuesta(VentaComprobante $ce, array $respuesta): void
    {
        $ce->facturamac_id = $respuesta['id']          ?? $ce->facturamac_id;
        $ce->numero        = $respuesta['numero']      ?? $ce->numero;
        $ce->serie         = $respuesta['serie']       ?? $ce->serie;
        $ce->correlativo   = $respuesta['correlativo'] ?? $ce->correlativo;
        $ce->estado        = $respuesta['estado']      ?? 'enviando';
        $ce->hash_cpe      = $respuesta['hash_cpe']    ?? $ce->hash_cpe;
        $ce->qr            = $respuesta['qr']          ?? $ce->qr;
        $ce->sunat_codigo      = $respuesta['sunat_codigo']      ?? $ce->sunat_codigo;
        $ce->sunat_descripcion = $respuesta['sunat_descripcion'] ?? $ce->sunat_descripcion;
        $ce->error       = null;
        $ce->enviado_at  = now();
        $ce->save();
    }

    private function registrarFallo(VentaComprobante $ce, string $estado, string $mensaje): void
    {
        $ce->estado = $estado;
        // Se recorta: el mensaje de SUNAT puede traer un XML entero y la
        // columna no tiene por qué aguantarlo.
        $ce->error = mb_substr($mensaje, 0, 1000);
        $ce->save();
    }

    /**
     * Último recurso: se agotaron los reintentos (o alguien llamó a fail()).
     *
     * NUNCA se toca la venta: el dinero y el stock ya se movieron y son
     * correctos. Solo se deja el rastro del fallo para que el admin lo vea en
     * la ficha de la venta y decida (reintentar / emitir a mano).
     */
    public function failed(Throwable $e): void
    {
        try {
            $ce = VentaComprobante::where('venta_id', $this->venta->id)->first();

            // No pisar un estado ya explicado (error_mapeo, rechazado) ni uno
            // terminal: `failed()` corre DESPUÉS de fail() y borraría el motivo
            // real, que es mucho más útil que un genérico "error de envío".
            if ($ce && !in_array($ce->estado, ['error_mapeo', 'rechazado', 'aceptado', 'anulado', 'pendiente_resumen'], true)) {
                $ce->estado = 'error_envio';
                $ce->error  = mb_substr($e->getMessage(), 0, 1000);
                $ce->save();
            }
        } catch (Throwable $inner) {
            Log::error('No se pudo marcar el comprobante como error_envio', [
                'venta_id' => $this->venta->id ?? null,
                'error'    => $inner->getMessage(),
            ]);
        }

        Log::error('Falló la emisión del comprobante electrónico', [
            'venta_id' => $this->venta->id ?? null,
            'numero'   => $this->venta->numero ?? null,
            'error'    => $e->getMessage(),
        ]);
    }
}
