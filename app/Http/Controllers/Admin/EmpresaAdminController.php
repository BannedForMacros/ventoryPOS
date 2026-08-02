<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Services\AuditoriaService;
use App\Services\OnboardingEmpresaService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * CRUD de empresas del panel del superadmin (/admin). A diferencia de
 * Configuracion\EmpresaController (el tenant edita SU empresa y su
 * configuración fina), aquí el proveedor ve TODAS las empresas, las da de
 * alta con su infraestructura completa (OnboardingEmpresaService) y las
 * activa/desactiva. No hay destroy: borrar una empresa cascadea todo su
 * historial; para dejarla fuera de servicio se desactiva.
 */
class EmpresaAdminController extends Controller
{
    public function __construct(private OnboardingEmpresaService $onboarding) {}

    public function index()
    {
        return Inertia::render('Admin/Empresas', [
            'empresas' => Empresa::withCount(['users', 'locales'])
                ->orderBy('razon_social')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $datos = $request->validate([
            'razon_social'     => 'required|string|max:255',
            'nombre_comercial' => 'nullable|string|max:255',
            'ruc'              => ['required', 'string', 'size:11', Rule::unique('empresas', 'ruc')],
            'direccion'        => 'nullable|string|max:255',
            'telefono'         => 'nullable|string|max:20',
            'email'            => 'nullable|email|max:255',
            'admin_name'       => 'required|string|max:255',
            'admin_email'      => ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'admin_password'   => 'required|string|min:6',
        ]);

        $empresa = $this->onboarding->crear(
            collect($datos)->except(['admin_name', 'admin_email', 'admin_password'])->all(),
            [
                'name'     => $datos['admin_name'],
                'email'    => $datos['admin_email'],
                'password' => $datos['admin_password'],
            ],
        );

        AuditoriaService::log('admin.empresa.creada', $empresa, [
            'razon_social' => $empresa->razon_social,
            'ruc'          => $empresa->ruc,
            'admin_email'  => $datos['admin_email'],
        ]);

        return redirect()->back()->with('success', "Empresa «{$empresa->razon_social}» creada y lista para operar.");
    }

    public function update(Request $request, Empresa $empresa)
    {
        $datos = $request->validate([
            'razon_social'     => 'required|string|max:255',
            'nombre_comercial' => 'nullable|string|max:255',
            'ruc'              => ['required', 'string', 'size:11', Rule::unique('empresas', 'ruc')->ignore($empresa->id)],
            'direccion'        => 'nullable|string|max:255',
            'telefono'         => 'nullable|string|max:20',
            'email'            => 'nullable|email|max:255',
            'activo'           => 'boolean',
        ]);

        $cambios = collect($datos)->filter(fn ($val, $key) => $empresa->{$key} != $val)->toArray();
        $empresa->update($datos);

        AuditoriaService::log('admin.empresa.actualizada', $empresa, [
            'razon_social' => $empresa->razon_social,
            'cambios'      => $cambios,
        ]);

        return redirect()->back()->with('success', 'Empresa actualizada correctamente.');
    }
}
