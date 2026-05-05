<?php

namespace App\Http\Requests\Proveedores;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProveedorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $empresaId = $this->user()->empresa_id;
        $id        = $this->route('proveedor')?->id;

        return [
            'tipo_documento'   => 'required|in:DNI,RUC,CE,PASS,otro',
            'numero_documento' => [
                'nullable', 'string', 'max:20',
                Rule::unique('proveedores', 'numero_documento')
                    ->where('empresa_id', $empresaId)
                    ->ignore($id),
            ],
            'razon_social'     => 'nullable|string|max:200',
            'nombre_comercial' => 'nullable|string|max:200',
            'contacto'         => 'nullable|string|max:150',
            'telefono'         => 'nullable|string|max:20',
            'email'            => 'nullable|email|max:150',
            'direccion'        => 'nullable|string|max:255',
            'observacion'      => 'nullable|string',
            'activo'           => 'boolean',
        ];
    }
}
