<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Configuracion\MetodoPagoRequest;
use App\Models\Cuenta;
use App\Models\MetodoPago;
use App\Models\TipoMetodoPago;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class MetodoPagoController extends Controller
{
    public function index(Request $request)
    {
        $empresaId = $request->user()->empresa_id;

        $metodos = MetodoPago::deEmpresa($empresaId)
            ->with([
                'tipo:id,slug,nombre,icono,admite_vuelto_default,requiere_referencia',
                'cuentas' => fn($q) => $q->select('cuentas.id', 'nombre', 'numero_cuenta', 'banco', 'activo'),
            ])
            ->orderBy('nombre')
            ->get();

        $cuentas = Cuenta::deEmpresa($empresaId)
            ->activo()
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'numero_cuenta', 'banco']);

        // Catálogo de tipos visible para el form (reemplaza array hardcodeado del front).
        $tiposMetodoPago = TipoMetodoPago::activo()
            ->orderBy('orden')
            ->get(['id', 'slug', 'nombre', 'icono', 'admite_vuelto_default', 'requiere_referencia']);

        return Inertia::render('Configuracion/MetodosPago', [
            'metodos'         => $metodos,
            'cuentas'         => $cuentas,
            'tiposMetodoPago' => $tiposMetodoPago,
        ]);
    }

    public function store(MetodoPagoRequest $request)
    {
        DB::transaction(function () use ($request) {
            $metodo = MetodoPago::create([
                'empresa_id'    => $request->user()->empresa_id,
                'nombre'        => $request->input('nombre'),
                'tipo_id'       => $request->input('tipo_id'),
                'admite_vuelto' => $request->boolean('admite_vuelto'),
                'activo'        => $request->input('activo', true),
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
                'nombre'        => $request->input('nombre'),
                'tipo_id'       => $request->input('tipo_id'),
                'admite_vuelto' => $request->boolean('admite_vuelto'),
                'activo'        => $request->input('activo', $metodos_pago->activo),
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
