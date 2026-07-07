<?php

namespace App\Http\Requests\Configuracion;

use Illuminate\Foundation\Http\FormRequest;

class RolRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * El frontend manda max_descuento_porcentaje como string vacío cuando el
     * admin deja el campo en blanco (= "sin tope"). Convertimos a null antes
     * de validar para que la regla nullable|numeric pase.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('max_descuento_porcentaje') && trim((string) $this->input('max_descuento_porcentaje')) === '') {
            $this->merge(['max_descuento_porcentaje' => null]);
        }
    }

    public function rules(): array
    {
        return [
            // empresa_id NO se acepta del request: el controlador la fuerza
            // desde el usuario autenticado.
            'nombre'      => 'required|string|max:255',
            'descripcion' => 'nullable|string|max:500',
            'es_admin'    => 'boolean',
            'activo'      => 'boolean',
            // M20: tope de descuento por rol. NULL = sin tope (típicamente admin).
            // 0-100% con 2 decimales. El admin define la política por cada rol.
            'max_descuento_porcentaje' => 'nullable|numeric|min:0|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'max_descuento_porcentaje.min' => 'El tope no puede ser negativo.',
            'max_descuento_porcentaje.max' => 'El tope no puede ser mayor a 100%.',
        ];
    }
}
