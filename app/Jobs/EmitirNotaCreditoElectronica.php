<?php

namespace App\Jobs;

use App\Models\Devolucion;
use App\Models\User;
use App\Services\AuditoriaService;
use App\Services\Facturacion\FacturaMacClient;
use App\Services\Facturacion\FacturaMacException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * V15 — Nota de Crédito por una devolución sobre una venta YA informada a SUNAT.
 *
 * POR QUÉ: cuando el comprobante está aceptado (o camino a estarlo), el importe
 * ya está declarado. Devolver mercadería sin emitir NC deja la venta declarada
 * por un monto que el cliente no pagó. Localmente no se puede "deshacer": el
 * único instrumento legal es la Nota de Crédito.
 *
 * REGLA DE ORO (la razón de que esto sea un job y no parte de la transacción):
 * si la NC falla, la devolución NO se revierte. El stock volvió al almacén y el
 * dinero salió de la caja — eso ya ocurrió y es correcto. Un fallo de SUNAT no
 * puede dejar al cliente sin su plata en el mostrador. Se registra el error, se
 * audita y el admin lo resuelve después.
 */
class EmitirNotaCreditoElectronica implements ShouldQueue
{
    use Queueable;

    public int $tries = 4;

    public int $timeout = 60;

    public array $backoff = [30, 120, 600];

    public function __construct(
        public int $devolucionId,
        public ?int $userId = null,
    ) {}

    public function handle(FacturaMacClient $client): void
    {
        if (!config('facturamac.enabled')) {
            return;
        }

        $devolucion = Devolucion::with(['venta', 'detalles'])->find($this->devolucionId);
        if (!$devolucion || !$devolucion->venta) {
            return;
        }

        // Una devolución rechazada/anulada no genera NC.
        if (!in_array($devolucion->estado, ['pendiente', 'aprobada', 'completada'], true)) {
            return;
        }

        $venta = $devolucion->venta;
        $ce    = $venta->comprobanteElectronico()->first();

        // Sin comprobante informado a SUNAT no hay nada que acreditar: la venta
        // era un `ticket` (nota interna) o su emisión falló. En ambos casos la
        // devolución se queda como está, que es exactamente lo correcto.
        if (!$ce || !$ce->esEmitido() || !$ce->facturamac_id) {
            return;
        }

        $motivo = $this->motivoSunat($devolucion, $venta->id);

        try {
            $respuesta = $client->notaCredito((int) $ce->facturamac_id, [
                // Idempotencia: una devolución = UNA nota de crédito, por más
                // veces que se reintente este job.
                'idempotency_key' => 'devolucion-' . $devolucion->id,
                // Catálogo 09 SUNAT: 06 devolución total | 07 devolución por ítem.
                'motivo'          => $motivo,
                'observaciones'   => sprintf(
                    'Devolución %s — venta %s',
                    $devolucion->numero ?: ('#' . $devolucion->id),
                    $venta->numero,
                ),
                'monto'           => round((float) $devolucion->monto_devolucion, 2),
                'detalles'        => $this->detalles($devolucion),
            ]);
        } catch (FacturaMacException $e) {
            if ($e->reintentable) {
                throw $e;
            }

            // 422: FacturaMac/SUNAT rechazan la NC por contenido. Reintentar da
            // lo mismo; hay que avisar a una persona.
            $this->avisarFallo($devolucion, $e->getMessage());

            return;
        }

        Log::info('Nota de crédito electrónica emitida', [
            'devolucion_id'  => $devolucion->id,
            'venta_id'       => $venta->id,
            'comprobante'    => $ce->numero,
            'motivo'         => $motivo,
            'nota_credito'   => $respuesta['numero'] ?? null,
        ]);

        AuditoriaService::log('venta_comprobante.nota_credito_emitida', $devolucion, [
            'venta_id'          => $venta->id,
            'comprobante'       => $ce->numero,
            'motivo_sunat'      => $motivo,
            'nota_credito'      => $respuesta['numero'] ?? null,
            'nota_credito_id'   => $respuesta['id'] ?? null,
            'monto'             => round((float) $devolucion->monto_devolucion, 2),
        ], $this->usuario());
    }

    /**
     * Catálogo 09 SUNAT: '06' devolución TOTAL (se devolvió todo lo vendido,
     * contando devoluciones anteriores) o '07' devolución POR ÍTEM (parcial).
     * La distinción importa: una NC por '06' cancela el comprobante entero.
     */
    private function motivoSunat(Devolucion $devolucion, int $ventaId): string
    {
        $vendido = DB::table('venta_items')
            ->where('venta_id', $ventaId)
            ->pluck('cantidad', 'id');

        if ($vendido->isEmpty()) {
            return '07';
        }

        $devuelto = DB::table('devoluciones_detalle')
            ->join('devoluciones', 'devoluciones.id', '=', 'devoluciones_detalle.devolucion_id')
            ->where('devoluciones.venta_id', $ventaId)
            ->whereIn('devoluciones.estado', ['pendiente', 'aprobada', 'completada'])
            ->select('devoluciones_detalle.venta_item_id', DB::raw('SUM(devoluciones_detalle.cantidad) as total'))
            ->groupBy('devoluciones_detalle.venta_item_id')
            ->pluck('total', 'venta_item_id');

        foreach ($vendido as $itemId => $cantidad) {
            if ((float) ($devuelto[$itemId] ?? 0) < (float) $cantidad - 0.0001) {
                return '07';
            }
        }

        return '06';
    }

    /** Líneas devueltas, para que FacturaMac acredite solo lo que volvió. */
    private function detalles(Devolucion $devolucion): array
    {
        return $devolucion->detalles->map(fn ($d) => [
            'venta_item_id'   => $d->venta_item_id,
            'producto_id'     => $d->producto_id,
            'cantidad'        => (float) $d->cantidad,
            'precio_unitario' => (float) $d->precio_unitario,
            'subtotal'        => (float) $d->subtotal,
        ])->values()->all();
    }

    private function usuario(): ?User
    {
        return $this->userId ? User::find($this->userId) : null;
    }

    private function avisarFallo(Devolucion $devolucion, string $mensaje): void
    {
        Log::error('No se pudo emitir la nota de crédito de una devolución', [
            'devolucion_id' => $devolucion->id,
            'venta_id'      => $devolucion->venta_id,
            'error'         => $mensaje,
        ]);

        // La auditoría es el canal por el que el admin se entera: la devolución
        // quedó hecha pero SUNAT sigue viendo el importe original declarado.
        AuditoriaService::log('venta_comprobante.nota_credito_fallida', $devolucion, [
            'venta_id' => $devolucion->venta_id,
            'error'    => mb_substr($mensaje, 0, 500),
            'aviso'    => 'La devolución NO se revirtió. Emitir la nota de crédito manualmente en FacturaMac.',
        ], $this->usuario());
    }

    public function failed(Throwable $e): void
    {
        $devolucion = Devolucion::find($this->devolucionId);
        if ($devolucion) {
            $this->avisarFallo($devolucion, $e->getMessage());
        } else {
            Log::error('Falló la nota de crédito y la devolución ya no existe', [
                'devolucion_id' => $this->devolucionId,
                'error'         => $e->getMessage(),
            ]);
        }
    }
}
