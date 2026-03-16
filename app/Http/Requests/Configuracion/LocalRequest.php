<?php

namespace App\Http\Requests\Configuracion;

use Illuminate\Foundation\Http\FormRequest;

class LocalRequest extends FormRequest
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
            'direccion'   => 'nullable|string|max:255',
            'telefono'    => 'nullable|string|max:20',
            'es_principal'=> 'boolean',
            'activo'      => 'boolean',
        ];
    }
}
