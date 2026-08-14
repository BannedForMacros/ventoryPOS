<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ImpresionController extends Controller
{
    /**
     * Valida el PIN maestro antes de permitir cambiar la URL del agente
     * de impresión (VentoryPrint) en un dispositivo.
     *
     * El PIN vive en VENTORY_PRINT_PIN (.env / config/app.php). Si no está
     * configurado, no se requiere protección y siempre responde OK.
     */
    public function verificarPin(Request $request): JsonResponse
    {
        $request->validate([
            'pin' => ['nullable', 'string', 'max:50'],
        ]);

        $pinConfigurado = config('app.print_pin', '');

        // Sin PIN configurado: no se protege el cambio.
        if ($pinConfigurado === null || trim($pinConfigurado) === '') {
            return response()->json(['ok' => true]);
        }

        $pinIngresado = (string) $request->input('pin', '');

        if (!hash_equals($pinConfigurado, $pinIngresado)) {
            throw ValidationException::withMessages([
                'pin' => ['PIN incorrecto.'],
            ]);
        }

        return response()->json(['ok' => true]);
    }
}
