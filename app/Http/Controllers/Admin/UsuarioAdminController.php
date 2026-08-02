<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\Local;
use App\Models\Rol;
use App\Models\User;
use App\Services\AuditoriaService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Usuarios de UNA empresa, gestionados por el superadmin. A diferencia de
 * Configuracion\UsuarioController (scoped al tenant del actor y con guardas
 * de escalada), aquí el actor es el proveedor: puede crear/gestionar admins
 * de cualquier empresa — para eso existe el panel. Lo único intocable son
 * otros superadmins (solo se gestionan por consola, superadmin:crear).
 */
class UsuarioAdminController extends Controller
{
    public function index(Empresa $empresa)
    {
        return Inertia::render('Admin/Usuarios', [
            'empresa'  => $empresa,
            'usuarios' => User::where('empresa_id', $empresa->id)->with(['local', 'rol'])->orderBy('name')->get(),
            'locales'  => Local::where('empresa_id', $empresa->id)->where('activo', true)->orderBy('nombre')->get(),
            'roles'    => Rol::where('empresa_id', $empresa->id)->where('activo', true)->orderBy('nombre')->get(),
        ]);
    }

    public function store(Request $request, Empresa $empresa)
    {
        $data = $this->validar($request, $empresa->id);
        $data['empresa_id'] = $empresa->id;

        $u = User::create($data);
        $u->forceFill(['email_verified_at' => now()])->save();

        AuditoriaService::log('admin.usuario.creado', $u, [
            'empresa_id' => $empresa->id,
            'name'       => $u->name,
            'email'      => $u->email,
            'rol_id'     => $u->rol_id,
        ]);
        return redirect()->back()->with('success', 'Usuario creado correctamente.');
    }

    public function update(Request $request, User $usuario)
    {
        abort_if($usuario->es_superadmin, 403, 'Los superadmins se gestionan por consola.');
        abort_if(!$usuario->empresa_id, 404);

        $data = $this->validar($request, $usuario->empresa_id, $usuario->id);
        if (empty($data['password'])) {
            unset($data['password']);
        }

        $cambios = collect($data)
            ->except(['password'])
            ->filter(fn ($val, $key) => $usuario->{$key} != $val)
            ->toArray();

        $usuario->update($data);

        AuditoriaService::log('admin.usuario.actualizado', $usuario, [
            'empresa_id'       => $usuario->empresa_id,
            'name'             => $usuario->name,
            'cambios'          => $cambios,
            'reseteo_password' => isset($data['password']),
        ]);
        return redirect()->back()->with('success', 'Usuario actualizado correctamente.');
    }

    public function destroy(User $usuario)
    {
        abort_if($usuario->es_superadmin, 403, 'Los superadmins se gestionan por consola.');
        abort_if(!$usuario->empresa_id, 404);

        $snapshot = ['empresa_id' => $usuario->empresa_id, 'name' => $usuario->name, 'email' => $usuario->email, 'rol_id' => $usuario->rol_id];
        $usuario->delete();
        AuditoriaService::log('admin.usuario.eliminado', $usuario, $snapshot);
        return redirect()->back()->with('success', 'Usuario eliminado correctamente.');
    }

    private function validar(Request $request, int $empresaId, ?int $usuarioId = null): array
    {
        return $request->validate([
            'local_id' => ['nullable', Rule::exists('locales', 'id')->where('empresa_id', $empresaId)],
            'rol_id'   => ['required', Rule::exists('roles', 'id')->where('empresa_id', $empresaId)],
            'name'     => 'required|string|max:255',
            'email'    => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($usuarioId)],
            'password' => $usuarioId ? 'nullable|string|min:6' : 'required|string|min:6',
            'activo'   => 'boolean',
        ]);
    }
}
