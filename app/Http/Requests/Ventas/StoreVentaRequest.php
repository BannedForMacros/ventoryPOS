<?php

namespace App\Http\Requests\Ventas;

use App\Models\ProductoUnidad;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVentaRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $empresaId = $this->user()->empresa_id;

        return [
            'cliente_id'             => ['nullable', 'integer', Rule::exists('clientes', 'id')->where('empresa_id', $empresaId)],
            'tipo_comprobante'       => ['required', Rule::in(['ticket', 'boleta', 'factura'])],
            'observacion'            => ['nullable', 'string', 'max:500'],
            'descuento_total'        => ['nullable', 'numeric', 'min:0'],
            'descuento_concepto_id'  => [
                'nullable', 'integer',
                Rule::exists('descuento_conceptos', 'id')->where('empresa_id', $empresaId),
            ],

            // Items
            'items'                           => ['required', 'array', 'min:1'],
            'items.*.producto_id'             => ['required', 'integer', Rule::exists('productos', 'id')->where('empresa_id', $empresaId)],
            'items.*.producto_unidad_id'      => ['required', 'integer', 'exists:producto_unidades,id'],
            'items.*.cantidad'                => ['required', 'numeric', 'min:0.0001'],
            'items.*.precio_unitario'         => ['required', 'numeric', 'min:0'],
            'items.*.descuento_item'          => ['nullable', 'numeric', 'min:0'],
            'items.*.descuento_concepto_id'   => [
                'nullable', 'integer',
                Rule::exists('descuento_conceptos', 'id')->where('empresa_id', $empresaId),
            ],

            // Pagos
            'pagos'                              => ['required', 'array', 'min:1'],
            'pagos.*.metodo_pago_id'             => ['required', 'integer', Rule::exists('metodos_pago', 'id')->where('empresa_id', $empresaId)],
            'pagos.*.cuenta_metodo_pago_id'      => ['nullable', 'integer', 'exists:cuenta_metodo_pago,id'],
            'pagos.*.monto'                      => ['required', 'numeric', 'min:0.01'],
            'pagos.*.referencia'                 => ['nullable', 'string', 'max:200'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validar que cada producto_unidad_id pertenece al producto_id correspondiente
            foreach ($this->input('items', []) as $index => $item) {
                if (empty($item['producto_id']) || empty($item['producto_unidad_id'])) continue;

                $unidad = ProductoUnidad::find($item['producto_unidad_id']);
                if ($unidad && (int) $unidad->producto_id !== (int) $item['producto_id']) {
                    $validator->errors()->add(
                        "items.{$index}.producto_unidad_id",
                        'La unidad no pertenece al producto seleccionado.'
                    );
                }
            }

            // Validar que el total de pagos cubre el total de la venta
            $baseItems = 0;
            foreach ($this->input('items', []) as $item) {
                $precio      = (float) ($item['precio_unitario'] ?? 0);
                $descuento   = (float) ($item['descuento_item'] ?? 0);
                $cantidad    = (float) ($item['cantidad'] ?? 0);
                $incluyeIgv  = !empty($item['incluye_igv']);
                $importe     = ($precio - $descuento) * $cantidad;
                $baseItems  += $incluyeIgv ? $importe / 1.18 : $importe;
            }

            $descuentoTotal = (float) ($this->input('descuento_total') ?? 0);
            $base  = max(0, $baseItems - $descuentoTotal);
            $total = round($base * 1.18, 2);

            $totalPagado = collect($this->input('pagos', []))->sum(fn($p) => (float) ($p['monto'] ?? 0));

            if ($totalPagado < $total - 0.01) {
                $validator->errors()->add('pagos', "El total pagado ({$totalPagado}) no cubre el total de la venta ({$total}).");
            }
        });
    }
}
