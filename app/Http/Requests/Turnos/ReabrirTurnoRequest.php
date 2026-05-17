<?php

namespace App\Http\Requests\Turnos;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A8 — La reapertura de un turno cerrado es una accion sensible: borra el
 * arqueo previo y anula el cierre de inventario asociado. Exigimos motivo
 * obligatorio (auditable, no opcional) para que quede registro de por que
 * se hizo: error de declaracion, denominacion mal contada, etc.
 */
class ReabrirTurnoRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'motivo.required' => 'El motivo de la reapertura es obligatorio (mínimo 10 caracteres).',
            'motivo.min'      => 'El motivo debe describir brevemente por qué se reabre (mín 10 caracteres).',
        ];
    }
}
