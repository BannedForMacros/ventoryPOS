<?php

namespace App\Http\Requests\Configuracion;

use Illuminate\Foundation\Http\FormRequest;

class CuentaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre'        => 'required|string|max:150',
            'numero_cuenta' => 'nullable|string|max:100',
            'banco'         => 'nullable|string|max:100',
            'cci'           => 'nullable|string|max:50',
            'titular'       => 'nullable|string|max:150',
            'activo'        => 'boolean',
        ];
    }
}
