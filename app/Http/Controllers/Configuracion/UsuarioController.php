<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Configuracion\UsuarioRequest;
use App\Models\Empresa;
use App\Models\Local;
use App\Models\Rol;
use App\Models\User;
use Inertia\Inertia;

class UsuarioController extends Controller
{
    public function index()
    {
        return Inertia::render('Configuracion/Usuarios', [
            'usuarios' => User::with(['empresa', 'local', 'rol'])->orderBy('name')->get(),
            'empresas' => Empresa::where('activo', true)->orderBy('razon_social')->get(),
            'locales'  => Local::where('activo', true)->with('empresa')->orderBy('nombre')->get(),
            'roles'    => Rol::where('activo', true)->with('empresa')->orderBy('nombre')->get(),
        ]);
    }

    public function store(UsuarioRequest $request)
    {
        User::create($request->validated());
        return redirect()->back()->with('success', 'Usuario creado correctamente.');
    }

    public function update(UsuarioRequest $request, User $usuario)
    {
        $data = $request->validated();
        if (empty($data['password'])) {
            unset($data['password']);
        }
        $usuario->update($data);
        return redirect()->back()->with('success', 'Usuario actualizado correctamente.');
    }

    public function destroy(User $usuario)
    {
        $usuario->delete();
        return redirect()->back()->with('success', 'Usuario eliminado correctamente.');
    }
}
