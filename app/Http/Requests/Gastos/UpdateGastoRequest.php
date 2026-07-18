<?php

namespace App\Http\Requests\Gastos;

use App\Models\GastoConcepto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Edición de un gasto. A diferencia de StoreGastoRequest, aquí NO se reasigna
 * el turno (un gasto no cambia de turno al editarse) y el método de pago es
 * OPCIONAL: si no se envía, el gasto conserva su cuenta actual.
 */
class UpdateGastoRequest extends FormRequest
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
            // Cambiar de cuenta es OPCIONAL: si no llega método, se mantiene la
            // cuenta con la que se registró el gasto.
            'metodo_pago_id' => [
                'nullable', 'integer',
                Rule::exists('metodos_pago', 'id')->where('empresa_id', $empresaId)->where('activo', true),
            ],
            'cuenta_metodo_pago_id' => ['nullable', 'integer', 'exists:cuenta_metodo_pago,id'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // El concepto debe pertenecer al tipo elegido.
            $concepto = GastoConcepto::find($this->input('gasto_concepto_id'));
            if ($concepto && (int) $concepto->gasto_tipo_id !== (int) $this->input('gasto_tipo_id')) {
                $validator->errors()->add('gasto_concepto_id', 'El concepto no pertenece al tipo de gasto seleccionado.');
            }

            // Si mandan cuenta, su fila pivote debe corresponder al método.
            $cmpId = $this->input('cuenta_metodo_pago_id');
            if ($cmpId) {
                $pivot = \Illuminate\Support\Facades\DB::table('cuenta_metodo_pago')->where('id', $cmpId)->first();
                if (!$pivot || (int) $pivot->metodo_pago_id !== (int) $this->input('metodo_pago_id')) {
                    $validator->errors()->add('cuenta_metodo_pago_id', 'La cuenta no pertenece al método de pago seleccionado.');
                }
            }
        });
    }
}
