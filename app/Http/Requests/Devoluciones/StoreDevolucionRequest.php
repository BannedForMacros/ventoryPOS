<?php

namespace App\Http\Requests\Devoluciones;

use App\Models\Devolucion;
use App\Models\Venta;
use App\Services\DevolucionService;
use App\Support\EnEmpresa;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class StoreDevolucionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $empresaId = $this->user()->empresa_id;

        return [
            'venta_id' => [
                'required', 'integer',
                Rule::exists('ventas', 'id')->where('empresa_id', $empresaId),
            ],
            'motivo_id' => [
                'required', 'integer',
                Rule::exists('devolucion_motivos', 'id')->where('empresa_id', $empresaId),
            ],
            'forma_reembolso' => ['required', Rule::in(['efectivo', 'mismo_metodo', 'vale_credito', 'cambio_producto', 'sin_reembolso'])],
            'observacion' => ['nullable', 'string', 'max:500'],
            'turno_id' => ['nullable', 'integer', Rule::exists('turnos', 'id')->where('empresa_id', $empresaId)],

            'items' => ['required', 'array', 'min:1'],
            'items.*.venta_item_id' => ['required', 'integer', Rule::exists('venta_items', 'id')->where('venta_id', (int) $this->input('venta_id'))],
            'items.*.cantidad' => ['required', 'numeric', 'min:0.0001'],
            'items.*.estado_producto' => ['nullable', Rule::in(['bueno', 'defectuoso', 'vencido', 'dañado'])],
            'items.*.restock' => ['nullable', 'boolean'],
            'items.*.motivo_id' => ['nullable', 'integer', Rule::exists('devolucion_motivos', 'id')->where('empresa_id', $empresaId)],
            'items.*.observacion' => ['nullable', 'string'],

            'pagos' => ['nullable', 'array'],
            'pagos.*.metodo_pago_id' => ['required_with:pagos', 'integer', Rule::exists('metodos_pago', 'id')->where('empresa_id', $empresaId)->where('activo', true)],
            'pagos.*.cuenta_metodo_pago_id' => ['nullable', 'integer', EnEmpresa::cuentaDePago()],
            'pagos.*.monto' => ['required_with:pagos', 'numeric', 'min:0.01'],
            'pagos.*.referencia' => ['nullable', 'string', 'max:100'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $forma = $this->input('forma_reembolso');
            $requierePagos = in_array($forma, ['efectivo', 'mismo_metodo'], true);
            $pagos = $this->input('pagos', []) ?: [];

            // Lo que de verdad hay que reembolsar: el importe devuelto CON el
            // descuento global de la venta prorrateado (misma fórmula que la nota
            // de crédito) y, si la venta es al crédito, MENOS lo que se descuenta
            // de su deuda. Lo calcula el servicio para que formulario y registro
            // digan lo mismo al céntimo.
            $venta = Venta::where('id', $this->input('venta_id'))
                ->where('empresa_id', $this->user()->empresa_id)
                ->with('items')
                ->first();
            $totalDevolucion = $venta
                ? app(DevolucionService::class)->montoADevolver($venta, (array) $this->input('items', []))
                : 0.0;
            $reparto = $venta
                ? Devolucion::repartoContraCxc($venta, $totalDevolucion)
                : ['cxc' => 0.0, 'reembolso' => $totalDevolucion];
            $aReembolsar = $reparto['reembolso'];

            // 1. Formas de reembolso que entregan dinero exigen al menos un pago
            //    (salvo que todo lo devuelto se descuente de la deuda de la venta).
            if ($requierePagos && empty($pagos) && $aReembolsar > 0.009) {
                $validator->errors()->add('pagos', 'Debes registrar al menos un pago porque la forma de reembolso requiere entregar dinero.');
                return;
            }

            // 2. Cada fila de pago debe tener método y monto mayor a 0.
            foreach ($pagos as $idx => $pago) {
                if (empty($pago['metodo_pago_id'])) {
                    $validator->errors()->add("pagos.{$idx}.metodo_pago_id", 'Selecciona el método de pago.');
                }
                if (!isset($pago['monto']) || (float) $pago['monto'] <= 0) {
                    $validator->errors()->add("pagos.{$idx}.monto", 'El monto debe ser mayor a 0.');
                }
            }

            // 3. Si hay pagos, la suma debe coincidir con lo que hay que reembolsar.
            if (!empty($pagos)) {
                $totalReembolso = collect($pagos)->sum(fn ($p) => (float) ($p['monto'] ?? 0));

                if (abs($totalReembolso - $aReembolsar) > 0.01) {
                    $validator->errors()->add('pagos', $reparto['cxc'] > 0.009
                        ? sprintf(
                            'El total del reembolso (S/ %.2f) debe ser S/ %.2f: de los S/ %.2f devueltos, S/ %.2f se descuentan de la deuda pendiente de la venta.',
                            $totalReembolso, $aReembolsar, $totalDevolucion, $reparto['cxc'],
                        )
                        : sprintf(
                            'El total del reembolso (S/ %.2f) debe coincidir con el monto a devolver (S/ %.2f).',
                            $totalReembolso,
                            $totalDevolucion
                        ));
                }
            }

            // 4. Si viene cuenta_metodo_pago_id, debe pertenecer al método enviado.
            foreach ($pagos as $idx => $pago) {
                $cmpId = $pago['cuenta_metodo_pago_id'] ?? null;
                $metodoId = $pago['metodo_pago_id'] ?? null;
                if ($cmpId && $metodoId) {
                    $pivot = DB::table('cuenta_metodo_pago')->where('id', $cmpId)->first();
                    if (!$pivot || (int) $pivot->metodo_pago_id !== (int) $metodoId) {
                        $validator->errors()->add("pagos.{$idx}.cuenta_metodo_pago_id", 'La cuenta no pertenece al método de pago seleccionado.');
                    }
                }
            }
        });
    }
}
