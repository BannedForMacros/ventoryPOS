<?php

namespace App\Http\Requests\Configuracion;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id        = $this->route('usuario')?->id;
        // empresa_id NO se acepta del request: el controlador la fuerza desde
        // el usuario autenticado. Rol y local deben pertenecer a esa empresa.
        $empresaId = $this->user()->empresa_id;

        return [
            'local_id'   => ['nullable', Rule::exists('locales', 'id')->where('empresa_id', $empresaId)],
            'rol_id'     => ['required', Rule::exists('roles', 'id')->where('empresa_id', $empresaId)],
            'name'       => 'required|string|max:255',
            'email'      => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($id)],
            'password'   => $id ? 'nullable|string|min:6' : 'required|string|min:6',
            'activo'     => 'boolean',
        ];
    }
}
