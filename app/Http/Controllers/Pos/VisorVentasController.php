<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Services\VisorVentas\LecturaFallida;
use App\Services\VisorVentas\VisorVentasService;
use Illuminate\Http\Request;

/** POS → "Leer cuaderno": recibe la foto y devuelve las ventas leídas para revisar. */
class VisorVentasController extends Controller
{
    public function __construct(private VisorVentasService $visor) {}

    public function leer(Request $request)
    {
        $request->validate([
            // El POS ya la reduce a 1568 px; el tope cuida el costo de un envío armado a mano.
            'foto' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:3500', 'dimensions:max_width=2600,max_height=2600'],
        ], [
            'foto.required' => 'Toma o elige una foto del cuaderno.',
            'foto.mimes'    => 'La foto debe ser JPG, PNG o WEBP.',
            'foto.max'        => 'La foto pesa demasiado. Tómala de nuevo o elige una más liviana.',
            'foto.dimensions' => 'La foto es demasiado grande. Tómala de nuevo desde el POS.',
        ]);

        // Si la conexión se corta mientras la IA lee, la lectura igual termina y
        // queda guardada: al volver a enviar la misma foto se devuelve gratis.
        ignore_user_abort(true);
        @set_time_limit(150);

        $foto = $request->file('foto');

        try {
            $resultado = $this->visor->procesar(
                $request->user(),
                base64_encode((string) file_get_contents($foto->getRealPath())),
                $foto->getMimeType() === 'image/jpg' ? 'image/jpeg' : (string) $foto->getMimeType(),
            );
        } catch (LecturaFallida $e) {
            return response()->json([
                'message'   => $e->getMessage(),
                'restantes' => $request->user()->empresa ? $this->visor->restantesHoy($request->user()->empresa) : 0,
            ], 422);
        }

        return response()->json($resultado);
    }

    /** Antes de cargar al carrito: ¿esa venta de esa lectura ya se cobró? (otra cajera pudo cobrarla). */
    public function verificar(Request $request)
    {
        $data = $request->validate([
            'sesion' => ['required', 'integer', 'min:1'],
            'indice' => ['required', 'integer', 'min:0', 'max:500'],
        ]);

        return response()->json([
            'ya_cobrada' => $this->visor->yaCobrada($request->user()->empresa_id, (int) $data['sesion'], (int) $data['indice']),
        ]);
    }
}
