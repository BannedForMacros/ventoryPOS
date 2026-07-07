<?php

namespace App\Http\Controllers\Configuracion;

use App\Http\Controllers\Controller;
use App\Models\Modulo;
use App\Models\Permiso;
use App\Models\Rol;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PermisoController extends Controller
{
    public function index(Request $request)
    {
        $empresaId = $request->user()->empresa_id;
        $roles   = Rol::where('empresa_id', $empresaId)->with('empresa')->orderBy('nombre')->get();
        $rolId   = $request->query('rol_id');
        $rol     = $rolId ? Rol::where('empresa_id', $empresaId)->with('permisos')->find($rolId) : null;
        $modulos = Modulo::with('hijos')->whereNull('padre_id')->where('activo', true)->orderBy('orden')->get();

        return Inertia::render('Configuracion/Permisos', [
            'roles'         => $roles,
            'modulos'       => $modulos,
            'rolSeleccionado'=> $rol,
            'permisos'      => $rol?->permisos ?? [],
        ]);
    }

    public function store(Request $request, Rol $rol)
    {
        abort_if($rol->empresa_id !== $request->user()->empresa_id, 403);

        $request->validate([
            'permisos'               => 'required|array',
            'permisos.*.modulo_id'   => 'required|exists:modulos,id',
            'permisos.*.ver'         => 'boolean',
            'permisos.*.crear'       => 'boolean',
            'permisos.*.editar'      => 'boolean',
            'permisos.*.eliminar'    => 'boolean',
        ]);

        // Snapshot de permisos previos para auditar el diff
        $previos = Permiso::where('rol_id', $rol->id)->get()->keyBy('modulo_id')
            ->map(fn($p) => ['ver'=>$p->ver,'crear'=>$p->crear,'editar'=>$p->editar,'eliminar'=>$p->eliminar])
            ->toArray();

        foreach ($request->permisos as $p) {
            Permiso::updateOrCreate(
                ['rol_id' => $rol->id, 'modulo_id' => $p['modulo_id']],
                [
                    'ver'      => $p['ver']      ?? false,
                    'crear'    => $p['crear']    ?? false,
                    'editar'   => $p['editar']   ?? false,
                    'eliminar' => $p['eliminar'] ?? false,
                ]
            );
        }

        \App\Services\AuditoriaService::log('permisos.modificados', $rol, [
            'rol_nombre'       => $rol->nombre,
            'permisos_previos' => $previos,
            'permisos_nuevos'  => collect($request->permisos)->keyBy('modulo_id')
                ->map(fn($p) => [
                    'ver'=>(bool)($p['ver']??false),
                    'crear'=>(bool)($p['crear']??false),
                    'editar'=>(bool)($p['editar']??false),
                    'eliminar'=>(bool)($p['eliminar']??false),
                ])->toArray(),
        ]);

        return redirect()->back()->with('success', 'Permisos guardados correctamente.');
    }
}
