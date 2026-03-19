<?php

namespace App\Http\Requests\Ventas;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDescuentoConceptoRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $empresaId = $this->user()->empresa_id;
        $id        = $this->route('descuento_concepto')?->id;

        return [
            'nombre' => [
                'required', 'string', 'max:150',
                Rule::unique('descuento_conceptos', 'nombre')
                    ->where('empresa_id', $empresaId)
                    ->ignore($id),
            ],
            'requiere_aprobacion' => ['required', 'boolean'],
            'activo'              => ['sometimes', 'boolean'],
        ];
    }
}
