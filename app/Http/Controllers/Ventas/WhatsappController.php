<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Models\DescuentoLog;
use App\Models\Venta;
use App\Services\WhatsappService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhatsappController extends Controller
{
    public function __construct(private WhatsappService $whatsapp) {}

    /**
     * Genera la URL de WhatsApp para solicitar aprobación de un descuento.
     * Recibe el log_id del descuento y el teléfono del supervisor.
     */
    public function urlAprobacion(Request $request): JsonResponse
    {
        $request->validate([
            'descuento_log_id'    => ['required', 'integer', 'exists:descuentos_log,id'],
            'telefono_supervisor' => ['required', 'string', 'max:20'],
        ]);

        $log = DescuentoLog::with(['user', 'cliente', 'concepto', 'venta'])->findOrFail($request->descuento_log_id);

        abort_if($log->empresa_id !== $request->user()->empresa_id, 403);

        $url = $this->whatsapp->generarUrlAprobacion(
            telefonoSupervisor: $request->telefono_supervisor,
            vendedorNombre:     $log->user->name ?? 'Vendedor',
            clienteNombre:      $log->cliente?->nombre ?? 'Cliente general',
            conceptoNombre:     $log->concepto?->nombre ?? $request->descuento_log_id,
            montoDescuento:     (float) $log->monto_descuento,
            ventaNumero:        $log->venta?->numero ?? '—',
        );

        $log->update(['notificacion_enviada' => true]);

        return response()->json(['url' => $url]);
    }

    /**
     * Genera la URL de WhatsApp para confirmar la compra al cliente.
     */
    public function urlConfirmacion(Request $request, Venta $venta): JsonResponse
    {
        abort_if($venta->empresa_id !== $request->user()->empresa_id, 403);

        $venta->loadMissing('cliente');

        if (!$venta->cliente?->telefono) {
            return response()->json(['url' => null, 'message' => 'El cliente no tiene teléfono registrado.'], 422);
        }

        $url = $this->whatsapp->generarUrlConfirmacionCliente(
            telefonoCliente: $venta->cliente->telefono,
            ventaNumero:     $venta->numero,
            total:           (float) $venta->total,
        );

        return response()->json(['url' => $url]);
    }
}
