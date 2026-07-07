<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Http\Requests\Configuracion\UsuarioRequest;
use App\Models\Empresa;
use App\Models\Local;
use App\Models\Rol;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UsuarioController extends Controller
{
    public function index(Request $request)
    {
        $empresaId = $request->user()->empresa_id;

        return Inertia::render('Configuracion/Usuarios', [
            'usuarios' => User::where('empresa_id', $empresaId)->with(['empresa', 'local', 'rol'])->orderBy('name')->get(),
            'empresas' => Empresa::where('id', $empresaId)->where('activo', true)->orderBy('razon_social')->get(),
            'locales'  => Local::where('empresa_id', $empresaId)->where('activo', true)->with('empresa')->orderBy('nombre')->get(),
            'roles'    => Rol::where('empresa_id', $empresaId)->where('activo', true)->with('empresa')->orderBy('nombre')->get(),
        ]);
    }

    public function store(UsuarioRequest $request)
    {
        $data = $request->validated();
        $data['empresa_id'] = $request->user()->empresa_id;
        $this->validarRolAsignable($request->user(), (int) $data['rol_id']);

        $u = User::create($data);
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
        $actor = $request->user();
        abort_if($usuario->empresa_id !== $actor->empresa_id, 403);
        // Gestionar (editar/resetear password de) un usuario admin exige ser admin.
        abort_if($usuario->rol?->es_admin && !$actor->rol?->es_admin, 403, 'Solo un administrador puede gestionar usuarios administradores.');

        $data = $request->validated();
        $data['empresa_id'] = $actor->empresa_id;
        if (empty($data['password'])) {
            unset($data['password']);
        }

        if ($usuario->id === $actor->id) {
            abort_if((int) $data['rol_id'] !== (int) $usuario->rol_id, 403, 'No puedes cambiar tu propio rol.');
            abort_if(array_key_exists('activo', $data) && !$data['activo'], 403, 'No puedes desactivar tu propio usuario.');
        }
        $this->validarRolAsignable($actor, (int) $data['rol_id']);

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

    public function destroy(Request $request, User $usuario)
    {
        $actor = $request->user();
        abort_if($usuario->empresa_id !== $actor->empresa_id, 403);
        abort_if($usuario->id === $actor->id, 403, 'No puedes eliminar tu propio usuario.');
        abort_if($usuario->rol?->es_admin && !$actor->rol?->es_admin, 403, 'Solo un administrador puede gestionar usuarios administradores.');

        $snapshot = ['name' => $usuario->name, 'email' => $usuario->email, 'rol_id' => $usuario->rol_id];
        $usuario->delete();
        \App\Services\AuditoriaService::log('usuario.eliminado', $usuario, $snapshot);
        return redirect()->back()->with('success', 'Usuario eliminado correctamente.');
    }

    /**
     * Otorgar un rol admin exige que quien asigna sea admin: sin esto, un
     * usuario con permiso config.usuarios se auto-escala asignándose un rol
     * es_admin (o creándole un admin a un tercero).
     */
    private function validarRolAsignable(User $actor, int $rolId): void
    {
        $rol = Rol::findOrFail($rolId);
        abort_if($rol->es_admin && !$actor->rol?->es_admin, 403, 'Solo un administrador puede asignar un rol de administrador.');
    }
}
