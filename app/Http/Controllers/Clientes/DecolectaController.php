<?php

namespace App\Http\Controllers\Clientes;

use App\Http\Controllers\Controller;
use App\Services\AuditoriaService;
use App\Services\DecolectaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Proxy a la API de Decolecta para consultar DNI/RUC.
 *
 * Seguridad:
 *   - Requiere usuario autenticado (heredado del grupo `auth+verified`).
 *   - Throttle 30/min por usuario (definido en routes/web.php).
 *   - Autorizacion OR: el usuario debe poder crear clientes o proveedores,
 *     que son los dos formularios que invocan este endpoint en el front.
 *     Sin esto cualquier usuario autenticado podia hacer enumeracion de DNIs
 *     (Habeas Data, Ley 29733 PE) y consumir cuota del token.
 *   - Cada consulta queda registrada en auditoria con el documento consultado,
 *     para investigaciones posteriores de uso indebido.
 */
class DecolectaController extends Controller
{
    public function __construct(private DecolectaService $decolecta) {}

    public function consultarDni(Request $request): JsonResponse
    {
        $this->autorizarConsulta($request);
        $request->validate(['dni' => 'required|string|size:8']);
        $dni = $request->input('dni');

        try {
            $datos = $this->decolecta->consultarDni($dni);
            AuditoriaService::log('decolecta.dni.consultado', null, ['dni' => $dni]);
            return response()->json($datos);
        } catch (\Exception $e) {
            AuditoriaService::log('decolecta.dni.error', null, [
                'dni'   => $dni,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function consultarRuc(Request $request): JsonResponse
    {
        $this->autorizarConsulta($request);
        $request->validate(['ruc' => 'required|string|size:11']);
        $ruc = $request->input('ruc');

        try {
            $datos = $this->decolecta->consultarRuc($ruc);
            AuditoriaService::log('decolecta.ruc.consultado', null, ['ruc' => $ruc]);
            return response()->json($datos);
        } catch (\Exception $e) {
            AuditoriaService::log('decolecta.ruc.error', null, [
                'ruc'   => $ruc,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /**
     * Aborta con 403 si el usuario no puede crear clientes ni proveedores.
     * Es una autorizacion OR: basta con tener una de las dos para poder
     * usar el lookup, porque ambos formularios lo necesitan.
     */
    private function autorizarConsulta(Request $request): void
    {
        $user = $request->user();
        if (!$user) abort(401);

        $puede = $user->tienePermiso('clientes', 'crear')
              || $user->tienePermiso('proveedores', 'crear');

        if (!$puede) {
            abort(403, 'No tienes permiso para consultar datos de DNI/RUC.');
        }
    }
}
