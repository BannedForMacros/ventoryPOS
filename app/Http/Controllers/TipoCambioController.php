<?php

namespace App\Http\Controllers;

use App\Services\TipoCambioService;
use Illuminate\Http\Request;

class TipoCambioController extends Controller
{
    public function __construct(private TipoCambioService $tipoCambio) {}

    /**
     * TC del día (o de una fecha) para el POS y Finanzas.
     * GET /tipo-cambio?moneda=USD&fecha=YYYY-MM-DD
     */
    public function show(Request $request)
    {
        $moneda = strtoupper($request->query('moneda', 'USD'));
        $fecha  = $request->query('fecha') ?: now()->toDateString();

        try {
            $tasa = $this->tipoCambio->tasaPara($fecha, $moneda);
            return response()->json(['moneda' => $moneda, 'fecha' => substr($fecha, 0, 10), 'tasa' => $tasa]);
        } catch (\Throwable $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
