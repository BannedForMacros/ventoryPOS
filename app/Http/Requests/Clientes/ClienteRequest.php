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
            'tipo_documento'   => 'required|in:DNI,RUC,CE',
            'numero_documento' => [
                'nullable', 'string',
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
            $num  = $this->input('numero_documento');

            if ($tipo === 'DNI') {
                if ($num && (!ctype_digit($num) || strlen($num) !== 8)) {
                    $v->errors()->add('numero_documento', 'El DNI debe tener exactamente 8 dígitos numéricos.');
                }
                if (empty($this->input('nombres'))) {
                    $v->errors()->add('nombres', 'El nombre es requerido para DNI.');
                }
            }

            if ($tipo === 'RUC') {
                if ($num && (!ctype_digit($num) || strlen($num) !== 11)) {
                    $v->errors()->add('numero_documento', 'El RUC debe tener exactamente 11 dígitos numéricos.');
                }
                if (empty($this->input('razon_social'))) {
                    $v->errors()->add('razon_social', 'La razón social es requerida para RUC.');
                }
            }

            if ($tipo === 'CE' && $num && strlen($num) > 12) {
                $v->errors()->add('numero_documento', 'El CE no puede superar los 12 caracteres.');
            }
        });
    }
}
