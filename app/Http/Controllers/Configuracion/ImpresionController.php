<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class ImpresionController extends Controller
{
    private const MAX_INTENTOS = 5;

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

        // Límite de intentos: tras 5 PIN incorrectos en un minuto se bloquea
        // (aunque el siguiente sea el correcto) hasta que pase el minuto. Sin
        // esto, un PIN de 4 dígitos se adivinaba probando en minutos. Va aquí
        // y no como throttle en la ruta para responder en español y contar
        // solo los fallos.
        $clave = 'pin-impresion:' . $request->user()->id . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($clave, self::MAX_INTENTOS)) {
            $segundos = RateLimiter::availableIn($clave);
            throw ValidationException::withMessages([
                'pin' => ["Demasiados intentos con PIN incorrecto. Espera {$segundos} segundos y vuelve a intentarlo."],
            ]);
        }

        $pinIngresado = (string) $request->input('pin', '');

        if (!hash_equals($pinConfigurado, $pinIngresado)) {
            RateLimiter::hit($clave, 60);
            throw ValidationException::withMessages([
                'pin' => ['PIN incorrecto.'],
            ]);
        }

        RateLimiter::clear($clave);

        return response()->json(['ok' => true]);
    }
}
