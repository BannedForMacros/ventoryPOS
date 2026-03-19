<?php

namespace App\Http\Controllers\Gastos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gastos\StoreGastoTipoRequest;
use App\Models\GastoConcepto;
use App\Models\GastoTipo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class GastoTipoController extends Controller
{
    public function index(Request $request)
    {
        $tipos = GastoTipo::deEmpresa($request->user()->empresa_id)
            ->with(['conceptos' => fn($q) => $q->orderBy('nombre')])
            ->orderBy('nombre')
            ->get();

        return Inertia::render('Configuracion/GastosTipos', [
            'tipos' => $tipos,
        ]);
    }

    public function store(StoreGastoTipoRequest $request)
    {
        $empresaId = $request->user()->empresa_id;

        DB::transaction(function () use ($request, $empresaId) {
            $tipo = GastoTipo::create([
                'empresa_id' => $empresaId,
                'nombre'     => $request->input('nombre'),
                'categoria'  => $request->input('categoria'),
                'activo'     => $request->input('activo', true),
            ]);

            foreach ($request->input('conceptos', []) as $c) {
                GastoConcepto::create([
                    'empresa_id'    => $empresaId,
                    'gasto_tipo_id' => $tipo->id,
                    'nombre'        => $c['nombre'],
                    'activo'        => $c['activo'] ?? true,
                ]);
            }
        });

        return redirect()->back()->with('success', 'Tipo de gasto creado correctamente.');
    }

    public function update(StoreGastoTipoRequest $request, GastoTipo $tipo)
    {
        abort_if($tipo->empresa_id !== $request->user()->empresa_id, 403);

        DB::transaction(function () use ($request, $tipo) {
            $tipo->update([
                'nombre'    => $request->input('nombre'),
                'categoria' => $request->input('categoria'),
                'activo'    => $request->input('activo', $tipo->activo),
            ]);

            $conceptosEnviados = collect($request->input('conceptos', []));
            $nombresEnviados   = $conceptosEnviados->pluck('nombre');

            // Desactivar conceptos que no vienen
            $tipo->conceptos()->whereNotIn('nombre', $nombresEnviados)->update(['activo' => false]);

            // Crear o reactivar conceptos que vienen
            foreach ($conceptosEnviados as $c) {
                GastoConcepto::updateOrCreate(
                    ['empresa_id' => $tipo->empresa_id, 'nombre' => $c['nombre']],
                    ['gasto_tipo_id' => $tipo->id, 'activo' => $c['activo'] ?? true]
                );
            }
        });

        return redirect()->back()->with('success', 'Tipo de gasto actualizado correctamente.');
    }

    public function destroy(Request $request, GastoTipo $tipo)
    {
        abort_if($tipo->empresa_id !== $request->user()->empresa_id, 403);

        $tipo->update(['activo' => false]);
        $tipo->conceptos()->update(['activo' => false]);

        return redirect()->back()->with('success', 'Tipo de gasto desactivado correctamente.');
    }
}
