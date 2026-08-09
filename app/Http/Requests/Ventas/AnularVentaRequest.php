<?php

namespace App\Http\Requests\Ventas;

use Illuminate\Foundation\Http\FormRequest;

/**
 * M21 — Anular una venta exige motivo. El motivo queda en la auditoria
 * para que cualquier disputa posterior (cliente, SUNAT, gerencia) tenga
 * trazabilidad clara de POR QUÉ se anuló esa venta.
 */
class AnularVentaRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'motivo' => ['required', 'string', 'min:10', 'max:500'],
            // Código de autorización (clave de un admin) que la cajera debe
            // ingresar para anular fuera del plazo configurable de la empresa.
            // El admin no lo usa.
            'codigo_autorizacion' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function messages(): array
    {
        return [
            'motivo.required' => 'El motivo de la anulación es obligatorio (mínimo 10 caracteres).',
            'motivo.min'      => 'El motivo debe describir brevemente por qué se anula (mín 10 caracteres).',
        ];
    }
}
