<?php

namespace App\Jobs;

use App\Models\VentaComprobante;
use App\Services\Facturacion\FacturaMacClient;
use App\Services\Facturacion\FacturaMacException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * V7 — Polling del estado de un comprobante ya entregado a FacturaMac.
 *
 * POR QUÉ EXISTE: la emisión no es síncrona de punta a punta.
 *   · Factura (01) → FacturaMac la manda a SUNAT en su propio job; el CDR
 *     llega en segundos, pero después de que nosotros ya respondimos.
 *   · Boleta  (03) → SUNAT la conoce recién con el RESUMEN DIARIO de las
 *     23:55. Entre la venta de las 09:00 y su aceptación pueden pasar 15 h.
 *
 * De ahí que el ritmo sea deliberadamente LENTO (no un bucle apretado):
 * consultar mil veces no acelera a SUNAT, solo quema cola y ancho de banda.
 * El job se re-encola a sí mismo con delays crecientes y se rinde cuando el
 * estado es terminal o cuando el comprobante ya es demasiado viejo.
 */
class ConsultarEstadoComprobante implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 30;

    public array $backoff = [30, 120];

    /** Estados que ya no cambian: consultar de nuevo no aporta nada. */
    private const TERMINALES = ['aceptado', 'rechazado', 'anulado', 'error_mapeo'];

    /**
     * Techo de vida del polling. Si en 7 días SUNAT no resolvió, el problema
     * no es de tiempo: hay que mirarlo a mano. Seguir consultando para siempre
     * dejaría trabajo zombie en la cola.
     */
    private const DIAS_MAX = 7;

    public function __construct(
        public int $comprobanteId,
        public int $intento = 0,
    ) {}

    public function handle(FacturaMacClient $client): void
    {
        if (!config('facturamac.enabled')) {
            return;
        }

        $ce = VentaComprobante::find($this->comprobanteId);
        if (!$ce || !$ce->facturamac_id) {
            return;
        }

        if (in_array($ce->estado, self::TERMINALES, true)) {
            return;
        }

        if ($this->caducado($ce)) {
            Log::warning('Se abandona el polling del comprobante electrónico (supera el plazo máximo)', [
                'comprobante_id' => $ce->id,
                'venta_id'       => $ce->venta_id,
                'numero'         => $ce->numero,
                'estado'         => $ce->estado,
            ]);

            return;
        }

        try {
            $respuesta = $client->consultar((int) $ce->facturamac_id);
        } catch (FacturaMacException $e) {
            if ($e->reintentable) {
                throw $e; // 5xx/timeout: que la cola aplique el backoff
            }

            // 404/422 al consultar: el comprobante no existe o el token no lo
            // alcanza. Insistir no lo va a arreglar; se deja constancia.
            Log::warning('Consulta de comprobante no reintentable', [
                'comprobante_id' => $ce->id,
                'facturamac_id'  => $ce->facturamac_id,
                'error'          => $e->getMessage(),
            ]);

            return;
        }

        $estadoPrevio = $ce->estado;

        // Se leen los campos a través del DTO del contrato y no del array crudo.
        // La versión anterior accedía a claves de primer nivel —'hash_cpe', 'serie',
        // 'sunat_codigo', 'error'— que la respuesta NO tiene: van anidadas en
        // `comprobante` y `sunat`. El resultado era que ocho de los nueve campos no
        // se actualizaban nunca y, en particular, el motivo de un rechazo de SUNAT
        // se perdía por completo: quedaba un estado `rechazado` sin explicación.
        // Con el DTO, un cambio de forma en la respuesta rompe al compilar en vez
        // de degradar en silencio.
        $cpe = $respuesta->comprobante;

        $ce->estado            = $respuesta->estado?->value      ?? $ce->estado;
        $ce->hash_cpe          = $cpe?->hash                     ?? $ce->hash_cpe;
        $ce->qr                = $cpe?->qr                       ?? $ce->qr;
        $ce->numero            = $cpe?->numeroCompleto           ?: $ce->numero;
        $ce->serie             = $cpe?->serie                    ?: $ce->serie;
        $ce->correlativo       = $cpe?->numero                   ?: $ce->correlativo;
        $ce->sunat_codigo      = $respuesta->sunat?->codigo      ?? $ce->sunat_codigo;
        $ce->sunat_descripcion = $respuesta->sunat?->descripcion ?? $ce->sunat_descripcion;

        // El motivo del rechazo es la descripción del CDR. Se copia a `error` solo
        // cuando SUNAT no aceptó, para que la UI pueda mostrar por qué falló sin
        // tener que interpretar códigos.
        if ($respuesta->sunat && ! $respuesta->sunat->aceptado() && $respuesta->sunat->descripcion) {
            $ce->error = mb_substr($respuesta->sunat->descripcion, 0, 1000);
        }

        $ce->save();

        if (in_array($ce->estado, self::TERMINALES, true)) {
            Log::info('Comprobante electrónico resuelto', [
                'comprobante_id' => $ce->id,
                'venta_id'       => $ce->venta_id,
                'numero'         => $ce->numero,
                'de'             => $estadoPrevio,
                'a'              => $ce->estado,
            ]);

            return;
        }

        // Sigue en vuelo (`pendiente_resumen` o `enviando`) → otra vuelta.
        self::dispatch($ce->id, $this->intento + 1)
            ->delay(self::proximaConsulta($ce->estado, $this->intento + 1));
    }

    /**
     * Cuándo volver a preguntar. El criterio depende del canal, no del azar:
     *
     *  · `enviando` (factura): SUNAT responde en segundos. Backoff corto
     *    1 → 2 → 4 → 8 → 15 min, con techo de 15 min.
     *  · `pendiente_resumen` (boleta): no hay nada que preguntar hasta que
     *    corra el Resumen Diario (23:55). La primera consulta se agenda justo
     *    después de esa ventana y a partir de ahí una cada 2 h.
     */
    public static function proximaConsulta(string $estado, int $intento): Carbon
    {
        if ($estado === 'pendiente_resumen') {
            $trasResumen = now()->setTime(23, 59);

            return ($intento === 0 && now()->lt($trasResumen))
                ? $trasResumen
                : now()->addHours(2);
        }

        return now()->addMinutes(min(15, 2 ** min($intento, 4)));
    }

    private function caducado(VentaComprobante $ce): bool
    {
        $desde = $ce->enviado_at ?? $ce->created_at;

        return $desde !== null && $desde->lt(now()->subDays(self::DIAS_MAX));
    }

    /**
     * Agotados los reintentos solo se loguea: el estado real del comprobante
     * lo sigue custodiando FacturaMac y la tarea programada
     * (`comprobantes:reencolar-consultas`) volverá a preguntar. No se marca
     * error en la venta: no saber el estado no es lo mismo que fallar.
     */
    public function failed(Throwable $e): void
    {
        Log::warning('Falló la consulta de estado del comprobante electrónico', [
            'comprobante_id' => $this->comprobanteId,
            'intento'        => $this->intento,
            'error'          => $e->getMessage(),
        ]);
    }
}
