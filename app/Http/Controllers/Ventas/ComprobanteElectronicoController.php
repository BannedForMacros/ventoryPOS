<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Jobs\EmitirComprobanteElectronico;
use App\Models\Venta;
use App\Services\Facturacion\FacturaMacClient;
use App\Services\Facturacion\FacturaMacException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * V8 — Cara visible del comprobante electrónico para el POS y la ficha de venta.
 *
 *  · estado()     → JSON liviano para el polling del POS tras cerrar la venta.
 *  · reintentar() → re-encola la emisión cuando quedó en error o rechazada.
 *  · pdf()        → proxy del PDF de FacturaMac.
 *
 * Nada de esto llama a SUNAT en línea salvo el PDF: el estado se sirve desde
 * `venta_comprobantes`, que los jobs mantienen al día. Un POS que pregunta cada
 * pocos segundos no puede depender de la latencia de un tercero.
 */
class ComprobanteElectronicoController extends Controller
{
    /**
     * Estado del comprobante de una venta. Pensado para que el POS lo consulte
     * cada pocos segundos tras cobrar sin costo apreciable (una fila, sin red).
     */
    public function estado(Request $request, Venta $venta): JsonResponse
    {
        abort_if($venta->empresa_id !== $request->user()->empresa_id, 403);

        $ce = $venta->comprobanteElectronico()->first();

        if (!$ce) {
            return response()->json([
                'tiene_comprobante' => false,
                // `emitible` le dice al POS si vale la pena seguir preguntando:
                // un ticket nunca va a tener comprobante.
                'emitible' => $venta->tipo_comprobante !== 'ticket' && (bool) config('facturamac.enabled'),
                'estado'   => null,
                'mensaje'  => $venta->tipo_comprobante === 'ticket'
                    ? 'Nota de venta interna: no se informa a SUNAT.'
                    : 'La emisión está en cola.',
            ]);
        }

        return response()->json([
            'tiene_comprobante' => true,
            'emitible'          => true,
            'id'                => $ce->id,
            'tipo'              => $ce->tipo,
            'serie'             => $ce->serie,
            'correlativo'       => $ce->correlativo,
            'numero'            => $ce->numero,
            'estado'            => $ce->estado,
            'mensaje'           => $this->mensajeDeEstado($ce->estado),
            'hash_cpe'          => $ce->hash_cpe,
            'qr'                => $ce->qr,
            'sunat_codigo'      => $ce->sunat_codigo,
            'sunat_descripcion' => $ce->sunat_descripcion,
            'error'             => $ce->error,
            'intentos'          => (int) $ce->intentos,
            'enviado_at'        => $ce->enviado_at,
            'puede_reintentar'  => $ce->puedeReintentar(),
            'tiene_pdf'         => (bool) $ce->facturamac_id,
            // Estado terminal: el POS puede dejar de preguntar.
            'final'             => in_array($ce->estado, ['aceptado', 'rechazado', 'anulado'], true),
        ]);
    }

    /**
     * Reintento manual. No re-emite nada por su cuenta: solo vuelve a encolar
     * el job, que es el único que sabe distinguir entre "emitir de nuevo" y
     * "reenviar el mismo correlativo" (G11).
     */
    public function reintentar(Request $request, Venta $venta)
    {
        abort_if($venta->empresa_id !== $request->user()->empresa_id, 403);
        abort_if($venta->tipo_comprobante === 'ticket', 422,
            'Un ticket es una nota de venta interna: no se emite ante SUNAT.');
        abort_unless(config('facturamac.enabled'), 422,
            'La facturación electrónica está desactivada.');

        $ce = $venta->comprobanteElectronico()->first();

        // Sin fila previa (la emisión nunca llegó a arrancar) el reintento es
        // simplemente la primera emisión.
        if ($ce && !$ce->puedeReintentar()) {
            abort(422, $ce->esEmitido()
                ? "El comprobante {$ce->numero} ya fue informado a SUNAT."
                : 'Este comprobante no admite reintentos.');
        }

        EmitirComprobanteElectronico::dispatch($venta);

        $mensaje = "Reintentando la emisión del comprobante de la venta {$venta->numero}.";

        if ($request->expectsJson() && !$request->header('X-Inertia')) {
            return response()->json(['message' => $mensaje]);
        }

        return back()->with('success', $mensaje);
    }

    /**
     * PDF del comprobante. Se proxea a propósito: el token de FacturaMac vive
     * en el servidor y NUNCA debe llegar al navegador del cajero. De paso, el
     * permiso de ventas sigue aplicando sobre el documento.
     */
    public function pdf(Request $request, Venta $venta, FacturaMacClient $client)
    {
        abort_if($venta->empresa_id !== $request->user()->empresa_id, 403);

        $ce = $venta->comprobanteElectronico()->first();
        abort_unless($ce && $ce->facturamac_id, 404,
            'Esta venta todavía no tiene comprobante electrónico emitido.');

        try {
            $binario = $client->pdf((int) $ce->facturamac_id);
        } catch (FacturaMacException $e) {
            Log::warning('No se pudo obtener el PDF del comprobante', [
                'venta_id'      => $venta->id,
                'facturamac_id' => $ce->facturamac_id,
                'error'         => $e->getMessage(),
            ]);
            abort(502, 'No se pudo obtener el PDF desde FacturaMac. Intenta de nuevo en unos segundos.');
        }

        $nombre = ($ce->numero ?: 'comprobante-' . $venta->numero) . '.pdf';

        return response($binario, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $nombre . '"',
        ]);
    }

    /** Traducción del estado técnico a algo que una cajera entienda. */
    private function mensajeDeEstado(?string $estado): string
    {
        return match ($estado) {
            'pendiente'         => 'En cola para enviarse a SUNAT.',
            'enviando'          => 'Enviándose a SUNAT…',
            // G10 — La boleta NO está aceptada todavía: viaja en el Resumen
            // Diario de las 23:55. Decir "aceptada" aquí sería mentir.
            'pendiente_resumen' => 'Se informará a SUNAT en el resumen diario de hoy.',
            'aceptado'          => 'Aceptado por SUNAT.',
            'rechazado'         => 'Rechazado por SUNAT. Revisa el detalle del error.',
            'error_envio'       => 'No se pudo enviar a SUNAT. Puedes reintentar.',
            'error_mapeo'       => 'La venta no se pudo convertir en comprobante. Requiere revisión del administrador.',
            'anulado'           => 'Anulado ante SUNAT.',
            default             => 'Estado desconocido.',
        };
    }
}
