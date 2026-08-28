<?php

namespace App\Http\Requests\Turnos;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Permite corregir el monto de apertura de un turno abierto. Es una accion
 * sensible porque afecta directamente el monto esperado al cierre, por eso
 * exige motivo obligatorio y se audita explicitamente.
 */
class ActualizarAperturaRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'monto_apertura'           => ['required', 'numeric', 'min:0'],
            'monto_fondos_adicionales' => ['nullable', 'numeric', 'min:0'],
            'motivo'                   => ['required', 'string', 'min:10', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'monto_apertura.required' => 'El monto de apertura es obligatorio.',
            'monto_apertura.numeric'  => 'El monto debe ser un numero valido.',
            'monto_apertura.min'      => 'El monto no puede ser negativo.',
            'motivo.required'         => 'El motivo de la correccion es obligatorio (minimo 10 caracteres).',
            'motivo.min'              => 'Describe brevemente por que se corrige el monto (min 10 caracteres).',
        ];
    }
}
