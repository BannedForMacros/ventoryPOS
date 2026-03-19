<?php

namespace App\Http\Controllers\Ventas;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ventas\StoreDescuentoConceptoRequest;
use App\Models\DescuentoConcepto;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DescuentoConceptoController extends Controller
{
    public function index(Request $request)
    {
        $user      = $request->user();
        $conceptos = DescuentoConcepto::deEmpresa($user->empresa_id)
            ->orderBy('nombre')
            ->get();

        return Inertia::render('Configuracion/DescuentoConceptos', [
            'conceptos' => $conceptos,
        ]);
    }

    public function store(StoreDescuentoConceptoRequest $request)
    {
        DescuentoConcepto::create([
            'empresa_id'          => $request->user()->empresa_id,
            'nombre'              => $request->nombre,
            'requiere_aprobacion' => $request->boolean('requiere_aprobacion'),
            'activo'              => $request->boolean('activo', true),
        ]);

        return redirect()->back()->with('success', 'Concepto creado correctamente.');
    }

    public function update(StoreDescuentoConceptoRequest $request, DescuentoConcepto $descuentoConcepto)
    {
        abort_if($descuentoConcepto->empresa_id !== $request->user()->empresa_id, 403);

        $descuentoConcepto->update([
            'nombre'              => $request->nombre,
            'requiere_aprobacion' => $request->boolean('requiere_aprobacion'),
            'activo'              => $request->boolean('activo', true),
        ]);

        return redirect()->back()->with('success', 'Concepto actualizado correctamente.');
    }

    public function destroy(Request $request, DescuentoConcepto $descuentoConcepto)
    {
        abort_if($descuentoConcepto->empresa_id !== $request->user()->empresa_id, 403);

        $descuentoConcepto->delete();

        return redirect()->back()->with('success', 'Concepto eliminado correctamente.');
    }
}
