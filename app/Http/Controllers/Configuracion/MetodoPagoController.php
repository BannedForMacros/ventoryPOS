<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Configuracion\MetodoPagoRequest;
use App\Models\Cuenta;
use App\Models\MetodoPago;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class MetodoPagoController extends Controller
{
    public function index(Request $request)
    {
        $empresaId = $request->user()->empresa_id;

        $metodos = MetodoPago::deEmpresa($empresaId)
            ->with(['cuentas' => fn($q) => $q->select('cuentas.id', 'nombre', 'numero_cuenta', 'banco', 'activo')])
            ->orderBy('nombre')
            ->get();

        $cuentas = Cuenta::deEmpresa($empresaId)
            ->activo()
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'numero_cuenta', 'banco']);

        return Inertia::render('Configuracion/MetodosPago', [
            'metodos' => $metodos,
            'cuentas' => $cuentas,
        ]);
    }

    public function store(MetodoPagoRequest $request)
    {
        DB::transaction(function () use ($request) {
            $metodo = MetodoPago::create([
                'empresa_id' => $request->user()->empresa_id,
                'nombre'     => $request->input('nombre'),
                'tipo'       => $request->input('tipo'),
                'activo'     => $request->input('activo', true),
            ]);

            $metodo->cuentas()->sync($request->input('cuenta_ids', []));
        });

        return redirect()->back()->with('success', 'Método de pago creado correctamente.');
    }

    public function update(MetodoPagoRequest $request, MetodoPago $metodos_pago)
    {
        abort_if($metodos_pago->empresa_id !== $request->user()->empresa_id, 403);

        DB::transaction(function () use ($request, $metodos_pago) {
            $metodos_pago->update([
                'nombre' => $request->input('nombre'),
                'tipo'   => $request->input('tipo'),
                'activo' => $request->input('activo', $metodos_pago->activo),
            ]);

            $metodos_pago->cuentas()->sync($request->input('cuenta_ids', []));
        });

        return redirect()->back()->with('success', 'Método de pago actualizado correctamente.');
    }

    public function destroy(Request $request, MetodoPago $metodos_pago)
    {
        abort_if($metodos_pago->empresa_id !== $request->user()->empresa_id, 403);

        $metodos_pago->update(['activo' => false]);

        return redirect()->back()->with('success', 'Método de pago desactivado correctamente.');
    }
}
