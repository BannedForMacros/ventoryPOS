<?php

namespace App\Http\Requests\Configuracion;

use Illuminate\Foundation\Http\FormRequest;

class RolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'empresa_id'  => 'required|exists:empresas,id',
            'nombre'      => 'required|string|max:255',
            'descripcion' => 'nullable|string|max:500',
            'es_admin'    => 'boolean',
            'activo'      => 'boolean',
        ];
    }
}
