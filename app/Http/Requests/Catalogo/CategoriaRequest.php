<?php

namespace App\Http\Requests\Catalogo;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CategoriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $empresaId = $this->user()->empresa_id;
        $id        = $this->route('categoria')?->id;

        return [
            'nombre'      => [
                'required', 'string', 'max:100',
                Rule::unique('categorias', 'nombre')
                    ->where('empresa_id', $empresaId)
                    ->ignore($id),
            ],
            'descripcion' => 'nullable|string|max:255',
            'activo'      => 'boolean',
        ];
    }
}
