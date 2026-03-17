<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Configuracion\MetodoPagoRequest;
use App\Models\MetodoPago;
use App\Models\MetodoPagoCuenta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class MetodoPagoController extends Controller
{
    public function index(Request $request)
    {
        $empresaId = $request->user()->empresa_id;

        $metodos = MetodoPago::deEmpresa($empresaId)
            ->with(['cuentas' => fn($q) => $q->orderBy('nombre')])
            ->orderBy('nombre')
            ->get();

        return Inertia::render('Configuracion/MetodosPago', [
            'metodos' => $metodos,
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

            foreach ($request->input('cuentas', []) as $cuenta) {
                $metodo->cuentas()->create([
                    'nombre'        => $cuenta['nombre'],
                    'numero_cuenta' => $cuenta['numero_cuenta'] ?? null,
                    'banco'         => $cuenta['banco']         ?? null,
                    'cci'           => $cuenta['cci']           ?? null,
                    'titular'       => $cuenta['titular']       ?? null,
                    'activo'        => $cuenta['activo']        ?? true,
                ]);
            }
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

            $cuentasEnviadas = $request->input('cuentas', []);
            $idsEnviados     = collect($cuentasEnviadas)->pluck('id')->filter()->values();

            // Desactivar cuentas que ya no vienen en el array
            $metodos_pago->cuentas()
                ->whereNotIn('id', $idsEnviados)
                ->update(['activo' => false]);

            foreach ($cuentasEnviadas as $cuentaData) {
                $campos = [
                    'nombre'        => $cuentaData['nombre'],
                    'numero_cuenta' => $cuentaData['numero_cuenta'] ?? null,
                    'banco'         => $cuentaData['banco']         ?? null,
                    'cci'           => $cuentaData['cci']           ?? null,
                    'titular'       => $cuentaData['titular']       ?? null,
                    'activo'        => $cuentaData['activo']        ?? true,
                ];

                if (!empty($cuentaData['id'])) {
                    MetodoPagoCuenta::where('id', $cuentaData['id'])
                        ->where('metodo_pago_id', $metodos_pago->id)
                        ->update($campos);
                } else {
                    $metodos_pago->cuentas()->create($campos);
                }
            }
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
