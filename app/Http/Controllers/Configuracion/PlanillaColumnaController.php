<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Models\PlanillaColumna;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Columnas de la planilla de caja de la empresa (función opcional
 * usa_planilla_caja). Se administran desde Configuración → Métodos de pago:
 * cada medio de pago elige a qué columna suma.
 */
class PlanillaColumnaController extends Controller
{
    public function store(Request $request)
    {
        $empresaId = $this->empresaConPlanilla($request);
        $data = $this->validar($request, $empresaId);

        PlanillaColumna::create([
            'empresa_id' => $empresaId,
            'nombre'     => $data['nombre'],
            'orden'      => (int) PlanillaColumna::deEmpresa($empresaId)->max('orden') + 1,
        ]);

        return back()->with('success', 'Columna creada.');
    }

    public function update(Request $request, PlanillaColumna $columna)
    {
        $empresaId = $this->empresaConPlanilla($request);
        abort_if($columna->empresa_id !== $empresaId, 403);
        $data = $this->validar($request, $empresaId, $columna->id);

        $columna->update(['nombre' => $data['nombre'], 'orden' => $data['orden'] ?? $columna->orden]);

        return back()->with('success', 'Columna actualizada.');
    }

    /** Los medios de pago de la columna pasan a mostrarse con su propio nombre. */
    public function destroy(Request $request, PlanillaColumna $columna)
    {
        abort_if($columna->empresa_id !== $this->empresaConPlanilla($request), 403);
        $columna->delete();

        return back()->with('success', 'Columna eliminada. Sus medios de pago salen ahora con su propio nombre.');
    }

    private function validar(Request $request, int $empresaId, ?int $ignorar = null): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:60',
                Rule::unique('planilla_columnas', 'nombre')->where('empresa_id', $empresaId)->ignore($ignorar)],
            'orden'  => ['nullable', 'integer', 'min:0', 'max:999'],
        ], [
            'nombre.unique' => 'Ya existe una columna con ese nombre.',
        ]);
    }

    private function empresaConPlanilla(Request $request): int
    {
        $user = $request->user();
        abort_unless((bool) ($user->empresa?->usa_planilla_caja ?? false), 403,
            'Esta empresa no tiene activada la planilla de caja.');
        return $user->empresa_id;
    }
}
