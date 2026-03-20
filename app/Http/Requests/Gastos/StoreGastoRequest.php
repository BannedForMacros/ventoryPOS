<?php

namespace App\Http\Requests\Gastos;

use App\Models\GastoConcepto;
use App\Models\Turno;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGastoRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $empresaId = $this->user()->empresa_id;

        return [
            'gasto_tipo_id' => [
                'required', 'integer',
                Rule::exists('gasto_tipos', 'id')->where('empresa_id', $empresaId),
            ],
            'gasto_concepto_id' => [
                'required', 'integer',
                Rule::exists('gasto_conceptos', 'id')->where('empresa_id', $empresaId),
            ],
            'monto'      => ['required', 'numeric', 'min:0.01'],
            'fecha'      => ['required', 'date'],
            'comentario' => ['nullable', 'string', 'max:500'],
            'turno_id'   => ['nullable', 'integer', 'exists:turnos,id'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validar que el concepto pertenece al tipo seleccionado
            $concepto = GastoConcepto::find($this->input('gasto_concepto_id'));
            if ($concepto && (int) $concepto->gasto_tipo_id !== (int) $this->input('gasto_tipo_id')) {
                $validator->errors()->add('gasto_concepto_id', 'El concepto no pertenece al tipo de gasto seleccionado.');
            }

            // Validar turno si viene
            if ($this->input('turno_id')) {
                $turno = Turno::find($this->input('turno_id'));
                if (!$turno) return;

                if ($turno->estado !== 'abierto') {
                    $validator->errors()->add('turno_id', 'El turno seleccionado no está abierto.');
                }

                // Non-admin: turno debe pertenecer al usuario
                if (!$this->user()->rol->es_admin && $turno->user_id !== $this->user()->id) {
                    $validator->errors()->add('turno_id', 'El turno no pertenece a tu usuario.');
                }
            } elseif (!$this->user()->rol->es_admin) {
                // Non-admin requiere turno_id
                $validator->errors()->add('turno_id', 'Debes tener un turno abierto para registrar gastos.');
            }
        });
    }
}
