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
            'tipo'       => 'required|in:efectivo,tarjeta_debito,tarjeta_credito,transferencia,yape,plin,otro',
            'activo'     => 'boolean',
            'cuenta_ids' => 'nullable|array',
            'cuenta_ids.*' => [
                'integer',
                Rule::exists('cuentas', 'id')->where('empresa_id', $empresaId),
            ],
        ];
    }
}
