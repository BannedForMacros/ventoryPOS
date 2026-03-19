<?php

namespace App\Http\Requests\Gastos;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGastoConceptoRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $empresaId  = $this->user()->empresa_id;
        $conceptoId = $this->route('concepto')?->id;

        return [
            'gasto_tipo_id' => [
                'required', 'integer',
                Rule::exists('gasto_tipos', 'id')->where('empresa_id', $empresaId),
            ],
            'nombre' => [
                'required', 'string', 'max:150',
                Rule::unique('gasto_conceptos', 'nombre')
                    ->where('empresa_id', $empresaId)
                    ->ignore($conceptoId),
            ],
            'activo' => ['boolean'],
        ];
    }
}
