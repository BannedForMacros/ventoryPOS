<?php

namespace App\Http\Requests\Configuracion;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ModuloRequest extends FormRequest
{
    /** Los módulos son el menú de TODAS las empresas: solo el superadmin los cambia. */
    public function authorize(): bool
    {
        return (bool) $this->user()?->es_superadmin;
    }

    public function rules(): array
    {
        $id = $this->route('modulo')?->id;

        return [
            'padre_id' => 'nullable|exists:modulos,id',
            'nombre'   => 'required|string|max:255',
            'slug'     => ['required', 'string', 'max:100', Rule::unique('modulos', 'slug')->ignore($id)],
            'icono'    => 'nullable|string|max:100',
            'ruta'     => 'nullable|string|max:255',
            'orden'    => 'required|integer|min:0',
            'activo'   => 'boolean',
        ];
    }
}
