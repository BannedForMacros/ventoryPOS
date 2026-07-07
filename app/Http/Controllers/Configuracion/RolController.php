<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Configuracion\RolRequest;
use App\Models\Empresa;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RolController extends Controller
{
    public function index(Request $request)
    {
        $empresaId = $request->user()->empresa_id;

        return Inertia::render('Configuracion/Roles', [
            'roles'    => Rol::where('empresa_id', $empresaId)->with('empresa')->orderBy('nombre')->get(),
            'empresas' => Empresa::where('id', $empresaId)->where('activo', true)->orderBy('razon_social')->get(),
        ]);
    }

    public function store(RolRequest $request)
    {
        $data = $request->validated();
        $data['empresa_id'] = $request->user()->empresa_id;
        $this->validarGestionDeAdmin($request->user(), (bool) ($data['es_admin'] ?? false));

        Rol::create($data);
        return redirect()->back()->with('success', 'Rol creado correctamente.');
    }

    public function update(RolRequest $request, Rol $rol)
    {
        abort_if($rol->empresa_id !== $request->user()->empresa_id, 403);
        $data = $request->validated();
        $data['empresa_id'] = $rol->empresa_id;
        // Tocar un rol admin, o convertir un rol en admin, exige ser admin.
        $this->validarGestionDeAdmin($request->user(), $rol->es_admin || (bool) ($data['es_admin'] ?? false));

        $rol->update($data);
        return redirect()->back()->with('success', 'Rol actualizado correctamente.');
    }

    public function destroy(Request $request, Rol $rol)
    {
        abort_if($rol->empresa_id !== $request->user()->empresa_id, 403);
        $this->validarGestionDeAdmin($request->user(), $rol->es_admin);

        // users.rol_id es nullOnDelete: borrar un rol en uso dejaría usuarios
        // sin ningún permiso de un momento a otro.
        if (User::where('rol_id', $rol->id)->exists()) {
            return back()->withErrors(['rol' => 'El rol tiene usuarios asignados; reasígnalos antes de eliminarlo.']);
        }

        $rol->delete();
        return redirect()->back()->with('success', 'Rol eliminado correctamente.');
    }

    /**
     * Crear, editar o eliminar roles es_admin exige ser admin: sin esto, un
     * usuario con permiso config.roles se auto-escala creando un rol admin.
     */
    private function validarGestionDeAdmin(User $actor, bool $involucraAdmin): void
    {
        abort_if($involucraAdmin && !$actor->rol?->es_admin, 403, 'Solo un administrador puede gestionar roles de administrador.');
    }
}
