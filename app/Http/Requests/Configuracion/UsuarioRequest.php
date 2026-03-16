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
        $id = $this->route('usuario')?->id;

        return [
            'empresa_id' => 'required|exists:empresas,id',
            'local_id'   => 'nullable|exists:locales,id',
            'rol_id'     => 'required|exists:roles,id',
            'name'       => 'required|string|max:255',
            'email'      => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($id)],
            'password'   => $id ? 'nullable|string|min:6' : 'required|string|min:6',
            'activo'     => 'boolean',
        ];
    }
}
