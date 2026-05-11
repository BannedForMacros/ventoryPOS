<?php

namespace App\Http\Requests\Agenda;

use App\Models\Empresa;
use App\Models\ProductoUnidad;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validacion de creacion/edicion de citas.
 *
 * Multidisciplina: el campo sujeto_nombre es obligatorio solo si la empresa
 * tiene agenda_sujeto_requerido=true (vet, taller). En peluqueria/spa
 * el cliente es suficiente y el campo no se valida.
 */
class StoreCitaRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        $user      = $this->user();
        $empresaId = $user->empresa_id;

        return [
            'local_id'           => [
                'required', 'integer',
                Rule::exists('locales', 'id')->where('empresa_id', $empresaId),
            ],
            'cliente_id'         => [
                'required', 'integer',
                Rule::exists('clientes', 'id')->where('empresa_id', $empresaId),
            ],
            'profesional_id'     => [
                'nullable', 'integer',
                Rule::exists('users', 'id')->where('empresa_id', $empresaId),
            ],
            'fecha_hora'         => ['required', 'date'],
            'observaciones'      => ['nullable', 'string', 'max:500'],
            'sujeto_nombre'      => [$this->sujetoEsRequerido() ? 'required' : 'nullable', 'string', 'max:150'],
            'sujeto_descripcion' => ['nullable', 'string', 'max:1000'],

            // Items
            'items'                       => ['required', 'array', 'min:1'],
            'items.*.producto_id'         => ['required', 'integer', Rule::exists('productos', 'id')->where('empresa_id', $empresaId)],
            'items.*.producto_unidad_id'  => ['required', 'integer', 'exists:producto_unidades,id'],
            'items.*.cantidad'            => ['nullable', 'numeric', 'min:0.0001'],
            'items.*.duracion_min'        => ['nullable', 'integer', 'min:1', 'max:1440'],
            'items.*.observaciones'       => ['nullable', 'string', 'max:300'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $empresaId = $this->user()->empresa_id;
            foreach ($this->input('items', []) as $idx => $item) {
                if (empty($item['producto_id']) || empty($item['producto_unidad_id'])) continue;

                // Cada producto_unidad_id debe pertenecer al producto_id correspondiente
                $unidad = ProductoUnidad::find($item['producto_unidad_id']);
                if ($unidad && (int) $unidad->producto_id !== (int) $item['producto_id']) {
                    $v->errors()->add(
                        "items.{$idx}.producto_unidad_id",
                        'La presentación no pertenece al producto seleccionado.'
                    );
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'sujeto_nombre.required' => 'El nombre de ' . strtolower($this->sujetoLabel() ?: 'sujeto') . ' es obligatorio.',
            'items.required'         => 'Debes agregar al menos un servicio o producto a la cita.',
            'items.min'              => 'Debes agregar al menos un servicio o producto a la cita.',
        ];
    }

    private function sujetoEsRequerido(): bool
    {
        $empresa = Empresa::find($this->user()->empresa_id);
        return $empresa && (bool) $empresa->agenda_sujeto_requerido && !empty($empresa->agenda_sujeto_label);
    }

    private function sujetoLabel(): ?string
    {
        $empresa = Empresa::find($this->user()->empresa_id);
        return $empresa?->agenda_sujeto_label;
    }
}
