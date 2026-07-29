<?php

namespace App\Jobs;

use App\Models\Venta;
use App\Models\VentaComprobante;
use App\Services\Facturacion\FacturaMacClient;
use App\Services\Facturacion\FacturaMacException;
use App\Services\Facturacion\VentaAContrato;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use MacSoft\Facturacion\Contrato\Dto\Respuesta;
use MacSoft\Facturacion\Contrato\Enum\EstadoComprobante;
use MacSoft\Facturacion\Contrato\Excepcion\ContratoInvalidoException;
use Throwable;

/**
 * V6 — Manda a FacturaMac la venta YA CERRADA para que emita su comprobante.
 *
 * POR QUÉ ES UN JOB Y NO UNA LLAMADA SÍNCRONA EN EL `store`:
 * si SUNAT tarda 40 s o está caído, el cajero no puede quedarse esperando con la cola
 * de clientes delante. La venta se cierra SIEMPRE; el comprobante se emite en segundo
 * plano y se reintenta. El corolario es innegociable: este job NUNCA revierte, anula
 * ni modifica la venta. Una venta cobrada en caja no se deshace porque SUNAT falle —
 * a lo sumo queda un comprobante en error, visible y reintentable.
 *
 * Reintentos: 5 intentos con backoff 10s / 30s / 2min / 10min. Cubre el caso real
 * (SUNAT intermitente en hora punta) sin martillar el servicio.
 */
class EmitirComprobanteElectronico implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /**
     * 60 s de techo: el POST solo crea el comprobante y encola el envío a SUNAT del
     * lado de allá, así que no debería tardar nada. Si tarda más, es que algo está mal
     * y conviene liberar el worker y reintentar.
     */
    public int $timeout = 60;

    /** Backoff exponencial entre reintentos (segundos). */
    public array $backoff = [10, 30, 120, 600];

    public function __construct(public Venta $venta) {}

    public function handle(VentaAContrato $mapper, FacturaMacClient $client): void
    {
        // Interruptor global: con la integración apagada el POS funciona exactamente
        // como antes de que existiera (todo el flujo histórico es `ticket`).
        if (!config('facturamac.enabled')) {
            return;
        }

        $venta = $this->venta;

        // El `ticket` es nota de venta INTERNA: no se informa a SUNAT.
        if ($venta->tipo_comprobante === 'ticket') {
            return;
        }

        // Idempotencia local: si el comprobante ya está informado a SUNAT no se vuelve
        // a emitir jamás. Protege contra un re-dispatch accidental (doble click en
        // "reintentar", scheduler, edición de la venta).
        $ce = $venta->comprobanteElectronico()->first();
        if ($ce && $ce->esEmitido()) {
            return;
        }

        $ce = $this->marcarEnviando($venta, $ce);

        // G11 — Un comprobante RECHAZADO ya consumió su correlativo en FacturaMac.
        // Reenviarlo por /reintentar reutiliza ese mismo correlativo; volver a emitir
        // dejaría un hueco en la numeración que hay que justificar ante SUNAT.
        $usarReintento = $ce->facturamac_id && $ce->estado === EstadoComprobante::RECHAZADO->value;

        // ── Mapeo ────────────────────────────────────────────────────────────
        // Lo que falla aquí son DEFECTOS DE DATOS (un ticket, una venta sin cliente,
        // un RUC de 10 dígitos): reintentar no los arregla, solo quema cola. La
        // validación la hacen los DTOs del contrato al construirse, y `campo` dice
        // exactamente qué corregir.
        $payload = null;
        if (!$usarReintento) {
            try {
                $payload = $mapper->mapear($venta)->aArray();
            } catch (ContratoInvalidoException $e) {
                $this->registrarFallo($ce, 'error_mapeo', $e->getMessage());
                Log::error('Comprobante electrónico: la venta no cumple el contrato (no reintentable)', [
                    'venta_id' => $venta->id,
                    'numero'   => $venta->numero,
                    'campo'    => $e->campo,
                    'error'    => $e->getMessage(),
                ]);
                $this->fail($e);

                return;
            }
        }

        // ── Envío ────────────────────────────────────────────────────────────
        try {
            $respuesta = $usarReintento
                ? Respuesta::desdeArray($client->reintentar((int) $ce->facturamac_id))
                : $client->emitirVenta($payload);
        } catch (FacturaMacException $e) {
            if (!$e->reintentable) {
                // 422/409: rechazan el contenido. Insistir daría el mismo resultado;
                // queda `rechazado` para que el admin corrija y reintente a mano.
                $this->registrarFallo($ce, EstadoComprobante::RECHAZADO->value, $e->getMessage());
                Log::warning('Comprobante electrónico rechazado (no reintentable)', [
                    'venta_id' => $venta->id,
                    'numero'   => $venta->numero,
                    'error'    => $e->getMessage(),
                ]);
                $this->fail($e);

                return;
            }

            // 5xx / timeout: la culpa es del transporte, no del contenido. Se relanza
            // para que la cola aplique el backoff.
            throw $e;
        }

        // Emisión desactivada en el emisor. NO ES UN ERROR: es la configuración de la
        // empresa. No se creó comprobante ni se consumió correlativo, así que no hay
        // nada que seguir; ni reintentos, ni alertas, ni polling.
        if (!$respuesta->emitido) {
            $this->registrarFallo($ce, 'no_emitido', $respuesta->motivo ?: 'La emisión electrónica está desactivada para esta empresa.');
            Log::info('Venta no emitida: el emisor tiene la emisión desactivada', [
                'venta_id' => $venta->id,
                'numero'   => $venta->numero,
                'modo'     => $respuesta->modo,
            ]);

            return;
        }

        $this->persistirRespuesta($ce, $respuesta);

        // Boleta: SUNAT la conoce recién con el Resumen Diario de las 23:55, así que
        // el estado real llega horas después. Factura: FacturaMac la envía en su
        // propio job y el CDR tarda segundos, pero tampoco es síncrono. En ambos casos
        // hay que preguntar; en un estado terminal (simulado, aceptado) no.
        $estado = EstadoComprobante::tryFrom((string) $ce->estado);

        if ($estado && !$estado->esTerminal() && $ce->facturamac_id) {
            ConsultarEstadoComprobante::dispatch($ce->id)
                ->delay(ConsultarEstadoComprobante::proximaConsulta($ce->estado, 0));
        }
    }

    /**
     * Crea (o reutiliza) la fila de seguimiento y la deja en `enviando` ANTES de salir
     * a la red: si el proceso muere a mitad del POST, la venta queda con evidencia de
     * que hubo un intento en vuelo.
     */
    private function marcarEnviando(Venta $venta, ?VentaComprobante $ce): VentaComprobante
    {
        $ce ??= new VentaComprobante();
        $ce->venta_id = $venta->id;
        $ce->tipo     = $venta->tipo_comprobante === 'factura' ? '01' : '03';

        // G6 — La clave de idempotencia se fija UNA sola vez y no cambia entre
        // reintentos: es lo único que impide que un timeout de red convierta un
        // reintento en un segundo comprobante fiscal real.
        $ce->idempotency_key ??= $venta->idempotency_key ?: ('venta-' . $venta->id);

        $ce->estado   = EstadoComprobante::ENVIANDO->value;
        $ce->intentos = (int) $ce->intentos + 1;
        $ce->error    = null;
        $ce->save();

        return $ce;
    }

    /**
     * Copia el estado que dicta el EMISOR. Regla de oro: el estado nunca se infiere
     * aquí. La única excepción es el modo simulación, donde el comprobante se calcula
     * y numera pero no llega a SUNAT: se marca `simulado` para que nadie lo confunda
     * con uno real.
     */
    private function persistirRespuesta(VentaComprobante $ce, Respuesta $respuesta): void
    {
        $comprobante = $respuesta->comprobante;

        $estado = $respuesta->modo === 'simulacion'
            ? EstadoComprobante::SIMULADO
            : ($respuesta->estado ?? EstadoComprobante::ENVIANDO);

        $ce->facturamac_id = $respuesta->id ?? $ce->facturamac_id;
        $ce->estado        = $estado->value;
        $ce->numero        = $comprobante?->numeroCompleto ?? $ce->numero;
        $ce->serie         = $comprobante?->serie          ?? $ce->serie;
        $ce->correlativo   = $comprobante?->numero         ?? $ce->correlativo;
        $ce->hash_cpe      = $comprobante?->hash           ?? $ce->hash_cpe;
        $ce->qr            = $comprobante?->qr             ?? $ce->qr;
        $ce->error         = null;
        $ce->enviado_at    = now();
        $ce->save();

        // Los avisos no son errores, pero un ajuste de céntimos invisible reaparece
        // semanas después como un descuadre inexplicable. Quedan en el log.
        if ($respuesta->tieneAvisos()) {
            Log::info('Avisos del emisor al emitir el comprobante', [
                'venta_id' => $ce->venta_id,
                'avisos'   => array_map(static fn ($a) => $a->aArray(), $respuesta->avisos),
            ]);
        }
    }

    private function registrarFallo(VentaComprobante $ce, string $estado, string $mensaje): void
    {
        $ce->estado = $estado;
        // Se recorta: el mensaje de SUNAT puede traer un XML entero y la columna no
        // tiene por qué aguantarlo.
        $ce->error = mb_substr($mensaje, 0, 1000);
        $ce->save();
    }

    /**
     * Último recurso: se agotaron los reintentos (o alguien llamó a fail()).
     *
     * NUNCA se toca la venta: el dinero y el stock ya se movieron y son correctos.
     * Solo se deja el rastro del fallo para que el admin lo vea en la ficha de la
     * venta y decida (reintentar / emitir a mano).
     */
    public function failed(Throwable $e): void
    {
        try {
            $ce = VentaComprobante::where('venta_id', $this->venta->id)->first();

            // No pisar un estado ya explicado (error_mapeo, rechazado, no_emitido) ni
            // uno terminal: `failed()` corre DESPUÉS de fail() y borraría el motivo
            // real, que es mucho más útil que un genérico "error de envío".
            $intocables = ['error_mapeo', 'no_emitido', 'rechazado', 'aceptado', 'anulado', 'simulado', 'pendiente_resumen'];

            if ($ce && !in_array($ce->estado, $intocables, true)) {
                $ce->estado = EstadoComprobante::ERROR_ENVIO->value;
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
