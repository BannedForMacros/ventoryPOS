<?php

namespace App\Http\Controllers\Finanzas;

use App\Http\Controllers\Controller;
use App\Models\Cuenta;
use App\Models\MetodoPago;
use App\Models\Proveedor;
use App\Models\ProveedorAdelanto;
use App\Services\AuditoriaService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Adelantos a proveedores: dinero entregado antes de recibir el material
 * ("ADELANTO DE PROVEEDORES" del balance, activo).
 *
 * El consumo del adelanto contra una entrada se hace desde Cuentas por
 * Pagar (abonar con proveedor_adelanto_id); aquí se gestiona el alta,
 * la devolución y la anulación.
 */
class AdelantoProveedorController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = ProveedorAdelanto::deEmpresa($user->empresa_id)
            ->with(['proveedor', 'metodoPago', 'cuenta', 'aplicaciones.entrada', 'aplicaciones.user'])
            ->when($request->input('proveedor_id'), fn ($q, $v) => $q->where('proveedor_id', $v));

        if ($request->input('estado', 'activos') === 'activos') {
            $query->activo();
        }

        $adelantos = $query->orderByDesc('fecha')->orderByDesc('id')->paginate(25)->withQueryString();

        $totalActivo = (float) ProveedorAdelanto::deEmpresa($user->empresa_id)->activo()->sum('saldo');

        return Inertia::render('Finanzas/Adelantos', [
            'adelantos'   => $adelantos,
            'totalActivo' => round($totalActivo, 2),
            'estado'      => $request->input('estado', 'activos'),
            'proveedores' => Proveedor::where('empresa_id', $user->empresa_id)->where('activo', true)
                ->orderBy('razon_social')->get(['id', 'razon_social', 'nombre_comercial']),
            'metodosPago' => MetodoPago::deEmpresa($user->empresa_id)->activo()->orderBy('nombre')->get(['id', 'nombre']),
            'cuentas'     => Cuenta::deEmpresa($user->empresa_id)->activo()->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'proveedor_id'   => ['required', 'integer', Rule::exists('proveedores', 'id')->where('empresa_id', $user->empresa_id)->where('activo', true)],
            'fecha'          => ['required', 'date'],
            'monto'          => ['required', 'numeric', 'min:0.01'],
            'metodo_pago_id' => ['nullable', 'integer', Rule::exists('metodos_pago', 'id')->where('empresa_id', $user->empresa_id)],
            'cuenta_id'      => ['nullable', 'integer', Rule::exists('cuentas', 'id')->where('empresa_id', $user->empresa_id)],
            'referencia'     => ['nullable', 'string', 'max:200'],
            'observacion'    => ['nullable', 'string', 'max:500'],
        ]);

        $adelanto = ProveedorAdelanto::create($data + [
            'empresa_id' => $user->empresa_id,
            'user_id'    => $user->id,
            'saldo'      => $data['monto'],
            'estado'     => 'activo',
        ]);

        AuditoriaService::log('adelanto_proveedor.creado', $adelanto, [
            'proveedor_id' => $adelanto->proveedor_id,
            'monto'        => (float) $adelanto->monto,
        ], $user);

        return back()->with('success', 'Adelanto registrado correctamente.');
    }

    /**
     * El proveedor devolvió el dinero, o el registro fue un error.
     */
    public function anular(Request $request, ProveedorAdelanto $adelanto)
    {
        $user = $request->user();
        abort_if($adelanto->empresa_id !== $user->empresa_id, 403);
        abort_unless($adelanto->estado === 'activo', 422, 'El adelanto no está activo.');

        $data = $request->validate([
            'accion' => ['required', Rule::in(['devuelto', 'anulado'])],
            'motivo' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $adelanto->update(['estado' => $data['accion']]);

        AuditoriaService::log('adelanto_proveedor.' . $data['accion'], $adelanto, [
            'motivo' => $data['motivo'],
            'saldo'  => (float) $adelanto->saldo,
        ], $user);

        return back()->with('success', $data['accion'] === 'devuelto' ? 'Adelanto marcado como devuelto.' : 'Adelanto anulado.');
    }
}
