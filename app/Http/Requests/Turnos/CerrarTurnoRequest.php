<?php

namespace App\Http\Requests\Turnos;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CerrarTurnoRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'observacion_cierre'               => ['nullable', 'string', 'max:500'],
            'arqueo'                           => ['required', 'array'],
            'arqueo.*.denominacion'            => ['required', 'numeric', Rule::in([200, 100, 50, 20, 10, 5, 2, 1, 0.50, 0.20, 0.10])],
            'arqueo.*.cantidad'                => ['required', 'integer', 'min:0'],
            'arqueo_metodos'                   => ['nullable', 'array'],
            'arqueo_metodos.*.metodo_pago_id'  => ['required', 'integer', 'exists:metodos_pago,id'],
            'arqueo_metodos.*.monto_declarado' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $denominaciones = collect($this->input('arqueo', []))->pluck('denominacion');
            if ($denominaciones->count() !== $denominaciones->unique()->count()) {
                $validator->errors()->add('arqueo', 'No puede haber denominaciones duplicadas en el arqueo.');
            }

            $metodos = collect($this->input('arqueo_metodos', []))->pluck('metodo_pago_id');
            if ($metodos->count() !== $metodos->unique()->count()) {
                $validator->errors()->add('arqueo_metodos', 'No puede haber métodos de pago duplicados en el arqueo.');
            }
        });
    }
}
