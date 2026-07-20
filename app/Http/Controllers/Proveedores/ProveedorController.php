<?php

namespace App\Http\Controllers\Proveedores;

use App\Http\Controllers\Controller;
use App\Http\Requests\Proveedores\ProveedorRequest;
use App\Models\Proveedor;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProveedorController extends Controller
{
    public function index(Request $request)
    {
        $empresaId = $request->user()->empresa_id;
        $busqueda  = $request->input('busqueda', '');

        $proveedores = Proveedor::deEmpresa($empresaId)
            ->when($busqueda, function ($q) use ($busqueda) {
                $q->where(function ($q) use ($busqueda) {
                    $q->where('razon_social', 'ilike', "%{$busqueda}%")
                      ->orWhere('nombre_comercial', 'ilike', "%{$busqueda}%")
                      ->orWhere('numero_documento', 'ilike', "%{$busqueda}%")
                      ->orWhere('contacto', 'ilike', "%{$busqueda}%");
                });
            })
            ->when($request->estado === 'activos', fn ($q) => $q->where('activo', true))
            ->when($request->estado === 'inactivos', fn ($q) => $q->where('activo', false))
            ->orderBy('razon_social')
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('Proveedores/Index', [
            'proveedores' => $proveedores,
            'busqueda'    => $busqueda,
            'estado'      => $request->estado,
        ]);
    }

    public function store(ProveedorRequest $request)
    {
        $proveedor = Proveedor::create([
            'empresa_id' => $request->user()->empresa_id,
            ...$request->validated(),
        ]);

        // Si la petición espera JSON (creación inline desde Entradas), devolver el modelo
        if ($request->wantsJson()) {
            return response()->json($proveedor);
        }

        return redirect()->back()->with('success', 'Proveedor creado correctamente.');
    }

    public function show(Request $request, Proveedor $proveedor)
    {
        abort_if($proveedor->empresa_id !== $request->user()->empresa_id, 403);

        $proveedor->load(['entradas' => fn($q) => $q->latest('fecha')->limit(20)]);

        return Inertia::render('Proveedores/Show', [
            'proveedor' => $proveedor,
            'entradas'  => $proveedor->entradas,
        ]);
    }

    public function update(ProveedorRequest $request, Proveedor $proveedor)
    {
        abort_if($proveedor->empresa_id !== $request->user()->empresa_id, 403);

        $proveedor->update($request->validated());

        return redirect()->back()->with('success', 'Proveedor actualizado correctamente.');
    }

    public function destroy(Request $request, Proveedor $proveedor)
    {
        abort_if($proveedor->empresa_id !== $request->user()->empresa_id, 403);

        if ($proveedor->entradas()->exists()) {
            $proveedor->update(['activo' => false]);
            return redirect()->back()->with('success', 'Proveedor desactivado (tiene entradas registradas).');
        }

        $proveedor->delete();
        return redirect()->back()->with('success', 'Proveedor eliminado.');
    }
}
