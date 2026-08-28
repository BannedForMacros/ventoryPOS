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
            // Método de pago con el que se paga el gasto (y su cuenta si tiene).
            // El backend lo resuelve a una cuenta_id concreta vía resolverCuenta.
            'metodo_pago_id' => [
                'required', 'integer',
                Rule::exists('metodos_pago', 'id')->where('empresa_id', $empresaId)->where('activo', true),
            ],
            // Id de la fila pivote cuenta_metodo_pago (NO el id de la cuenta).
            // Obligatoria si el método tiene cuentas vinculadas (con 1 el front la
            // autoselecciona; con 2+ el usuario elige). Efectivo/sin-cuenta: opcional.
            'cuenta_metodo_pago_id' => ['nullable', 'integer', 'exists:cuenta_metodo_pago,id',
                function ($attr, $value, $fail) {
                    if (! $value && \App\Support\PagoCuenta::requiere(
                        $this->input('metodo_pago_id') ? (int) $this->input('metodo_pago_id') : null
                    )) {
                        $fail('Debes seleccionar la cuenta para este método de pago.');
                    }
                },
            ],
            // F7 (compat) — cuenta directa de la que sale el dinero (null = efectivo)
            'cuenta_id'  => ['nullable', 'integer', Rule::exists('cuentas', 'id')->where('empresa_id', $empresaId)],
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

            // Si viene cuenta_metodo_pago_id, su fila pivote debe corresponder
            // al método enviado (evita mezclar la cuenta de otro método).
            $cmpId = $this->input('cuenta_metodo_pago_id');
            if ($cmpId) {
                $pivot = \Illuminate\Support\Facades\DB::table('cuenta_metodo_pago')->where('id', $cmpId)->first();
                if (!$pivot || (int) $pivot->metodo_pago_id !== (int) $this->input('metodo_pago_id')) {
                    $validator->errors()->add('cuenta_metodo_pago_id', 'La cuenta no pertenece al método de pago seleccionado.');
                }
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
            } elseif (!$this->user()->rol->es_admin
                && \App\Support\AfectaCaja::activo($this->user()->empresa, 'gastos')) {
                // Non-admin requiere turno_id — solo si el módulo 'gastos' afecta
                // caja para la empresa. Si está apagado, el cajero puede registrar
                // gastos sin turno (no descuentan de ninguna caja).
                $validator->errors()->add('turno_id', 'Debes tener un turno abierto para registrar gastos.');
            }
        });
    }
}
