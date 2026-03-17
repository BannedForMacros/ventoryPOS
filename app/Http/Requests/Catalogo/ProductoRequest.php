<?php

namespace App\Http\Requests\Catalogo;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $empresaId = $this->user()->empresa_id;
        $id        = $this->route('producto')?->id;

        $rules = [
            'categoria_id' => [
                'nullable',
                Rule::exists('categorias', 'id')->where('empresa_id', $empresaId),
            ],
            'codigo'       => [
                'nullable', 'string', 'max:50',
                Rule::unique('productos', 'codigo')
                    ->where('empresa_id', $empresaId)
                    ->ignore($id),
            ],
            'nombre'       => 'required|string|max:150',
            'descripcion'  => 'nullable|string',
            'tipo'         => 'required|in:producto,servicio',
            'tipo_precio'  => 'required|in:fijo,referencial',
            'precio_venta' => 'required|numeric|min:0',
            'precio_costo' => 'nullable|numeric|min:0',
            'activo'       => 'boolean',
        ];

        if ($this->input('tipo') === 'producto') {
            $rules['unidades']                     = 'required|array|min:1';
            $rules['unidades.*.unidad_medida_id']  = [
                'required',
                Rule::exists('unidades_medida', 'id')->where('empresa_id', $empresaId),
            ];
            $rules['unidades.*.es_base']           = 'required|boolean';
            $rules['unidades.*.factor_conversion'] = 'required|numeric|min:0.0001';
            $rules['unidades.*.tipo_precio']       = 'required|in:fijo,referencial';
            $rules['unidades.*.precio_venta']      = 'required|numeric|min:0';
            $rules['unidades.*.precio_costo']      = 'nullable|numeric|min:0';
            $rules['unidades.*.activo']            = 'boolean';
        }

        return $rules;
    }

    public function withValidator($validator): void
    {
        if ($this->input('tipo') !== 'producto') {
            return;
        }

        $validator->after(function ($v) {
            $unidades = $this->input('unidades', []);
            $bases    = array_filter($unidades, fn($u) => !empty($u['es_base']));

            if (count($bases) !== 1) {
                $v->errors()->add('unidades', 'Exactamente una unidad debe ser la unidad base.');
            }
        });
    }
}
