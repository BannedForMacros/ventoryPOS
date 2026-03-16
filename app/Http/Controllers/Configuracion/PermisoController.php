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
        $roles   = Rol::with('empresa')->orderBy('nombre')->get();
        $rolId   = $request->query('rol_id');
        $rol     = $rolId ? Rol::with('permisos')->find($rolId) : null;
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
        $request->validate([
            'permisos'               => 'required|array',
            'permisos.*.modulo_id'   => 'required|exists:modulos,id',
            'permisos.*.ver'         => 'boolean',
            'permisos.*.crear'       => 'boolean',
            'permisos.*.editar'      => 'boolean',
            'permisos.*.eliminar'    => 'boolean',
        ]);

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

        return redirect()->back()->with('success', 'Permisos guardados correctamente.');
    }
}
