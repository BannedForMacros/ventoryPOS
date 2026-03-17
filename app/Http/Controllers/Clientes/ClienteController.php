<?php

namespace App\Http\Controllers\Clientes;

use App\Http\Controllers\Controller;
use App\Http\Requests\Clientes\ClienteRequest;
use App\Models\Cliente;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ClienteController extends Controller
{
    public function index(Request $request)
    {
        $empresaId = $request->user()->empresa_id;
        $busqueda  = $request->input('busqueda', '');

        $clientes = Cliente::deEmpresa($empresaId)
            ->when($busqueda, function ($q) use ($busqueda) {
                $q->where(function ($q) use ($busqueda) {
                    $q->where('nombres', 'ilike', "%{$busqueda}%")
                      ->orWhere('apellidos', 'ilike', "%{$busqueda}%")
                      ->orWhere('razon_social', 'ilike', "%{$busqueda}%")
                      ->orWhere('numero_documento', 'ilike', "%{$busqueda}%");
                });
            })
            ->orderByRaw("CASE WHEN numero_documento IS NULL THEN 0 ELSE 1 END")
            ->orderBy('nombres')
            ->orderBy('razon_social')
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('Clientes/Index', [
            'clientes' => $clientes,
            'busqueda' => $busqueda,
        ]);
    }

    public function store(ClienteRequest $request)
    {
        Cliente::create([
            'empresa_id' => $request->user()->empresa_id,
            ...$request->validated(),
        ]);

        return redirect()->back()->with('success', 'Cliente creado correctamente.');
    }

    public function show(Request $request, Cliente $cliente)
    {
        abort_if($cliente->empresa_id !== $request->user()->empresa_id, 403);

        return Inertia::render('Clientes/Show', [
            'cliente' => $cliente,
            'compras' => [],
        ]);
    }

    public function update(ClienteRequest $request, Cliente $cliente)
    {
        abort_if($cliente->empresa_id !== $request->user()->empresa_id, 403);

        if ($cliente->es_cliente_general) {
            abort(403, 'El Cliente General no puede ser modificado.');
        }

        $cliente->update($request->validated());

        return redirect()->back()->with('success', 'Cliente actualizado correctamente.');
    }

    public function destroy(Request $request, Cliente $cliente)
    {
        abort_if($cliente->empresa_id !== $request->user()->empresa_id, 403);

        if ($cliente->es_cliente_general) {
            abort(403, 'El Cliente General no puede ser desactivado.');
        }

        $cliente->update(['activo' => false]);

        return redirect()->back()->with('success', 'Cliente desactivado correctamente.');
    }
}
