<?php

namespace App\Http\Requests\Gastos;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGastoTipoRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $empresaId = $this->user()->empresa_id;
        $tipoId    = $this->route('tipo')?->id;

        return [
            'nombre' => [
                'required', 'string', 'max:100',
                Rule::unique('gasto_tipos', 'nombre')
                    ->where('empresa_id', $empresaId)
                    ->ignore($tipoId),
            ],
            'categoria'          => ['required', Rule::in(['administrativo', 'operativo', 'otro'])],
            'activo'             => ['boolean'],
            'conceptos'          => ['nullable', 'array'],
            'conceptos.*.nombre' => ['required', 'string', 'max:150'],
            'conceptos.*.activo' => ['boolean'],
        ];
    }
}
