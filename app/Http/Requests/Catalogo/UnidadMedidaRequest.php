<?php

namespace App\Http\Requests\Catalogo;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UnidadMedidaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $empresaId = $this->user()->empresa_id;
        $id        = $this->route('unidades_medida')?->id;

        return [
            'nombre'      => 'required|string|max:100',
            'abreviatura' => [
                'required', 'string', 'max:20',
                Rule::unique('unidades_medida', 'abreviatura')
                    ->where('empresa_id', $empresaId)
                    ->ignore($id),
            ],
            'activo'      => 'boolean',
        ];
    }
}
