<?php

namespace App\Http\Controllers\Clientes;

use App\Http\Controllers\Controller;
use App\Services\DecolectaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DecolectaController extends Controller
{
    public function __construct(private DecolectaService $decolecta) {}

    public function consultarDni(Request $request): JsonResponse
    {
        $request->validate(['dni' => 'required|string|size:8']);

        try {
            $datos = $this->decolecta->consultarDni($request->input('dni'));
            return response()->json($datos);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function consultarRuc(Request $request): JsonResponse
    {
        $request->validate(['ruc' => 'required|string|size:11']);

        try {
            $datos = $this->decolecta->consultarRuc($request->input('ruc'));
            return response()->json($datos);
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }
}
