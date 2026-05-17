<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Configuracion\EmpresaRequest;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Services\AlmacenSyncService;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use RuntimeException;

class EmpresaController extends Controller
{
    public function __construct(private AlmacenSyncService $almacenSync) {}

    public function index()
    {
        return Inertia::render('Configuracion/Empresas', [
            'empresas' => Empresa::orderBy('razon_social')->get(),
        ]);
    }

    public function store(EmpresaRequest $request)
    {
        $empresa = Empresa::create($request->validated());

        // Cliente general por defecto. La flag `es_cliente_general` (A15) es
        // la fuente de verdad; mantenemos DNI 99999999 como valor poblado pero
        // ya no se usa para identificarlo.
        Cliente::create([
            'empresa_id'         => $empresa->id,
            'tipo_documento'     => 'DNI',
            'numero_documento'   => '99999999',
            'nombres'            => 'Clientes Varios',
            'apellidos'          => '',
            'activo'             => true,
            'es_cliente_general' => true,
        ]);

        return redirect()->back()->with('success', 'Empresa creada correctamente.');
    }

    public function update(EmpresaRequest $request, Empresa $empresa)
    {
        $modoAnterior = $empresa->modo_almacen;
        $datos        = $request->validated();
        $modoNuevo    = $datos['modo_almacen'];

        if ($modoNuevo !== $modoAnterior && !$this->almacenSync->puedeCambiarModo($empresa)) {
            return back()->withErrors([
                'modo_almacen' => 'No se puede cambiar el modo de almacén porque la empresa ya tiene movimientos '
                    . '(entradas, transferencias o ventas).',
            ])->withInput();
        }

        try {
            DB::transaction(function () use ($empresa, $datos, $modoAnterior) {
                $empresa->update($datos);
                $this->almacenSync->aplicarCambioModo($empresa->fresh(), $modoAnterior);
            });
        } catch (RuntimeException $e) {
            return back()->withErrors(['modo_almacen' => $e->getMessage()])->withInput();
        }

        return redirect()->back()->with('success', 'Empresa actualizada correctamente.');
    }

    public function destroy(Empresa $empresa)
    {
        $empresa->delete();
        return redirect()->back()->with('success', 'Empresa eliminada correctamente.');
    }
}
