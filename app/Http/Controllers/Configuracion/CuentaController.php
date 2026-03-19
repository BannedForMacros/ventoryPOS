<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Configuracion\CuentaRequest;
use App\Models\Cuenta;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CuentaController extends Controller
{
    public function index(Request $request)
    {
        $empresaId = $request->user()->empresa_id;

        $cuentas = Cuenta::deEmpresa($empresaId)
            ->with(['metodosPago' => fn($q) => $q->select('metodos_pago.id', 'nombre', 'tipo')])
            ->orderBy('nombre')
            ->get();

        return Inertia::render('Configuracion/Cuentas', [
            'cuentas' => $cuentas,
        ]);
    }

    public function store(CuentaRequest $request)
    {
        Cuenta::create([
            'empresa_id' => $request->user()->empresa_id,
            ...$request->validated(),
        ]);

        return redirect()->back()->with('success', 'Cuenta creada correctamente.');
    }

    public function update(CuentaRequest $request, Cuenta $cuenta)
    {
        abort_if($cuenta->empresa_id !== $request->user()->empresa_id, 403);

        $cuenta->update($request->validated());

        return redirect()->back()->with('success', 'Cuenta actualizada correctamente.');
    }

    public function destroy(Request $request, Cuenta $cuenta)
    {
        abort_if($cuenta->empresa_id !== $request->user()->empresa_id, 403);

        $cuenta->update(['activo' => false]);

        return redirect()->back()->with('success', 'Cuenta desactivada correctamente.');
    }
}
