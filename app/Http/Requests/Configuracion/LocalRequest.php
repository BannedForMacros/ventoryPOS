<?php

namespace App\Http\Requests\Configuracion;

use Illuminate\Foundation\Http\FormRequest;

class LocalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'empresa_id'               => 'required|exists:empresas,id',
            'nombre'                   => 'required|string|max:255',
            'direccion'                => 'nullable|string|max:255',
            'telefono'                 => 'nullable|string|max:20',
            'es_principal'             => 'boolean',
            'activo'                   => 'boolean',
            'descuenta_stock_en_venta'        => 'nullable|boolean',
            'modo_cierre_caja'                => 'nullable|in:rapido,con_declaraciones',
            'modo_cierre_inventario'          => 'nullable|in:por_venta,declarado',
            'usa_fondos_iniciales'            => 'nullable|boolean',
            'fondos_iniciales_en_declaracion' => 'nullable|boolean',
        ];
    }
}
