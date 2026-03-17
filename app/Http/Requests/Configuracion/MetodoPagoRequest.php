<?php

namespace App\Http\Requests\Configuracion;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MetodoPagoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $empresaId = $this->user()->empresa_id;
        $id        = $this->route('metodos_pago')?->id;

        return [
            'nombre' => [
                'required', 'string', 'max:80',
                Rule::unique('metodos_pago', 'nombre')
                    ->where('empresa_id', $empresaId)
                    ->ignore($id),
            ],
            'tipo'   => 'required|in:efectivo,tarjeta_debito,tarjeta_credito,transferencia,yape,plin,otro',
            'activo' => 'boolean',

            'cuentas'                 => 'nullable|array',
            'cuentas.*.id'            => 'nullable|integer|exists:metodo_pago_cuentas,id',
            'cuentas.*.nombre'        => 'required_with:cuentas|string|max:150',
            'cuentas.*.numero_cuenta' => 'nullable|string|max:100',
            'cuentas.*.banco'         => 'nullable|string|max:100',
            'cuentas.*.cci'           => 'nullable|string|max:50',
            'cuentas.*.titular'       => 'nullable|string|max:150',
            'cuentas.*.activo'        => 'boolean',
        ];
    }
}
