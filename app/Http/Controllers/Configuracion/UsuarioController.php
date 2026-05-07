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
        $u = User::create($request->validated());
        \App\Services\AuditoriaService::log('usuario.creado', $u, [
            'name'  => $u->name,
            'email' => $u->email,
            'rol_id'=> $u->rol_id,
            'local_id' => $u->local_id,
        ]);
        return redirect()->back()->with('success', 'Usuario creado correctamente.');
    }

    public function update(UsuarioRequest $request, User $usuario)
    {
        $data = $request->validated();
        if (empty($data['password'])) {
            unset($data['password']);
        }
        $cambios = collect($data)
            ->except(['password', 'password_confirmation'])
            ->filter(fn($val, $key) => $usuario->{$key} != $val)
            ->toArray();

        $usuario->update($data);

        \App\Services\AuditoriaService::log('usuario.actualizado', $usuario, [
            'name'    => $usuario->name,
            'cambios' => $cambios,
            'reseteo_password' => isset($data['password']),
        ]);
        return redirect()->back()->with('success', 'Usuario actualizado correctamente.');
    }

    public function destroy(User $usuario)
    {
        $snapshot = ['name' => $usuario->name, 'email' => $usuario->email, 'rol_id' => $usuario->rol_id];
        $usuario->delete();
        \App\Services\AuditoriaService::log('usuario.eliminado', $usuario, $snapshot);
        return redirect()->back()->with('success', 'Usuario eliminado correctamente.');
    }
}
