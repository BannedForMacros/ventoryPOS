<?php

namespace App\Http\Requests\Clientes;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ClienteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $empresaId = $this->user()->empresa_id;
        $id        = $this->route('cliente')?->id;

        return [
            'tipo_documento'   => 'required|in:DNI,RUC,CE,pasaporte,otro',
            'numero_documento' => [
                'nullable', 'string', 'max:20',
                Rule::unique('clientes', 'numero_documento')
                    ->where('empresa_id', $empresaId)
                    ->ignore($id),
            ],
            'nombres'          => 'nullable|string|max:100',
            'apellidos'        => 'nullable|string|max:100',
            'razon_social'     => 'nullable|string|max:200',
            'telefono'         => 'nullable|string|max:20',
            'email'            => 'nullable|email|max:150',
            'direccion'        => 'nullable|string|max:255',
            'fecha_nacimiento' => 'nullable|date|before:today',
            'activo'           => 'boolean',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $tipo = $this->input('tipo_documento');

            if ($tipo === 'DNI' && empty($this->input('nombres'))) {
                $v->errors()->add('nombres', 'El nombre es requerido para DNI.');
            }

            if ($tipo === 'RUC' && empty($this->input('razon_social'))) {
                $v->errors()->add('razon_social', 'La razón social es requerida para RUC.');
            }
        });
    }
}
