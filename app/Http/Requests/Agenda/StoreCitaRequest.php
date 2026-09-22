<?php

namespace App\Http\Requests\Agenda;

use App\Models\Cita;
use App\Models\Empresa;
use App\Models\ProductoUnidad;
use App\Models\User;
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
            // after_or_equal:now evita citas agendadas en el pasado por error
            // (typo en fecha, zona horaria, etc.). Si se necesita backfill
            // historico, hacerlo via comando admin, no via formulario.
            'fecha_hora'         => ['required', 'date', 'after_or_equal:now'],
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

            // Anti-colision: no permitir solape contra citas activas del mismo
            // profesional (o del mismo local si no se asigno profesional).
            // Considera la duracion estimada de la cita nueva (suma de items).
            $this->validarSolape($v);
        });
    }

    /**
     * Comprueba que la nueva cita no se solape con otra activa del mismo
     * profesional (o del mismo local si no hay profesional). Solo agrega
     * error si encuentra colision; no es estricto con timezone (usa Carbon
     * parseando la cadena tal cual la mando el cliente).
     */
    private function validarSolape($validator): void
    {
        $fechaHoraStr = $this->input('fecha_hora');
        if (!$fechaHoraStr) return;

        try {
            $inicio = \Carbon\Carbon::parse($fechaHoraStr);
        } catch (\Throwable $e) {
            return; // ya falla por la regla 'date'
        }

        // Calcular la duracion total de la cita nueva sumando items
        // (mismo algoritmo que CitaService::crear).
        $duracionNueva = 0;
        foreach ($this->input('items', []) as $item) {
            $dur = (int) ($item['duracion_min'] ?? 30);
            $cnt = (int) ($item['cantidad'] ?? 1);
            $duracionNueva += $dur * $cnt;
        }
        if ($duracionNueva <= 0) $duracionNueva = 30;
        $fin = $inicio->copy()->addMinutes($duracionNueva);

        $empresaId      = $this->user()->empresa_id;
        $profesionalId  = $this->input('profesional_id');
        $localId        = $this->input('local_id');
        $citaIdActual   = $this->route('cita')?->id; // en update, ignorar la cita misma

        $query = Cita::query()
            ->where('empresa_id', $empresaId)
            ->activas()
            ->when($citaIdActual, fn ($q) => $q->where('id', '!=', $citaIdActual));

        // Dos citas a la misma hora son NORMALES mientras las atiendan personas
        // distintas: es lo que hace un salón con tres sillas. Por eso el choque
        // se busca SOLO dentro de la agenda de esa persona. Sin profesional
        // asignado, la cita es del local y compite con las demás del local.
        if ($profesionalId) {
            $query->where('profesional_id', $profesionalId);
            $quien = User::whereKey($profesionalId)->value('name');
            $contexto = $quien ? "la agenda de {$quien}" : 'la agenda de esa persona';
            $salida   = ' Si la atiende otra persona, asígnala y no hay choque.';
        } else {
            $query->where('local_id', $localId)->whereNull('profesional_id');
            $contexto = 'el local';
            $salida   = ' Si la atiende alguien en concreto, asígnale un profesional y no hay choque.';
        }

        // Postgres: solape <=> existing.inicio < nueva.fin
        //                    AND existing.inicio + interval '1 minute' * existing.duracion_min > nueva.inicio
        $colision = $query
            ->whereRaw('fecha_hora < ?', [$fin])
            ->whereRaw("(fecha_hora + (duracion_min * interval '1 minute')) > ?", [$inicio])
            ->orderBy('fecha_hora')
            ->first();

        if ($colision) {
            $finColision = $colision->fecha_hora->copy()->addMinutes((int) $colision->duracion_min);

            $validator->errors()->add(
                'fecha_hora',
                "Ese horario ya está ocupado en {$contexto}: la cita {$colision->numero} va de "
                . $colision->fecha_hora->format('H:i') . ' a ' . $finColision->format('H:i') . '.'
                . $salida
            );
        }
    }

    /**
     * Mensajes EN CASTELLANO DE PERSONA, no de programador.
     *
     * Los de Laravel por defecto filtran la regla tal cual: "debe ser una fecha
     * posterior o igual a now" es literalmente lo que veía el cliente final, con
     * el `now` del código incluido. Quien está agendando una cita no tiene por
     * qué descifrar eso.
     *
     * Regla al escribir uno nuevo: decir qué pasó y qué hacer, sin nombrar
     * columnas ni reglas de validación.
     */
    public function messages(): array
    {
        return [
            'cliente_id.required'     => 'Elige a qué cliente pertenece la cita.',
            'cliente_id.exists'       => 'Ese cliente ya no existe. Elige otro o créalo con el botón «Nuevo».',
            'local_id.required'       => 'Elige el local donde se atenderá.',
            'profesional_id.exists'   => 'Esa persona ya no está disponible. Elige otra o deja la cita sin asignar.',

            'fecha_hora.required'       => 'Indica el día y la hora de la cita.',
            'fecha_hora.date'           => 'El día y la hora no se entienden. Revísalos.',
            'fecha_hora.after_or_equal' => 'La cita no puede quedar en el pasado. Elige una fecha y hora de ahora en adelante.',

            'observaciones.max'      => 'Las observaciones son muy largas (máximo 500 caracteres).',
            'sujeto_nombre.required' => 'El nombre de ' . strtolower($this->sujetoLabel() ?: 'sujeto') . ' es obligatorio.',
            'sujeto_nombre.max'      => 'Ese nombre es muy largo (máximo 150 caracteres).',
            'sujeto_descripcion.max' => 'La descripción es muy larga (máximo 1000 caracteres).',

            'items.required' => 'Debes agregar al menos un servicio o producto a la cita.',
            'items.min'      => 'Debes agregar al menos un servicio o producto a la cita.',

            'items.*.producto_id.required'        => 'Falta elegir el servicio.',
            'items.*.producto_id.exists'          => 'Ese servicio ya no está disponible.',
            'items.*.producto_unidad_id.required' => 'Falta elegir la presentación del servicio.',
            'items.*.cantidad.numeric'            => 'La cantidad debe ser un número.',
            'items.*.cantidad.min'                => 'La cantidad tiene que ser mayor que cero.',
            'items.*.duracion_min.integer'        => 'La duración debe ser un número de minutos.',
            'items.*.duracion_min.min'            => 'La duración tiene que ser de al menos 1 minuto.',
            'items.*.duracion_min.max'            => 'La duración no puede pasar de 24 horas (1440 minutos).',
            'items.*.observaciones.max'           => 'Esa nota es muy larga (máximo 300 caracteres).',
        ];
    }

    /**
     * Cómo se nombran los campos si algún mensaje se escapa al de por defecto.
     * Sin esto sale "El campo fecha hora", que es el nombre de la columna.
     */
    public function attributes(): array
    {
        return [
            'cliente_id'         => 'cliente',
            'local_id'           => 'local',
            'profesional_id'     => 'profesional',
            'fecha_hora'         => 'día y hora',
            'observaciones'      => 'observaciones',
            'sujeto_nombre'      => strtolower($this->sujetoLabel() ?: 'sujeto'),
            'items'              => 'servicios',
            'items.*.producto_id'        => 'servicio',
            'items.*.producto_unidad_id' => 'presentación',
            'items.*.cantidad'           => 'cantidad',
            'items.*.duracion_min'       => 'duración',
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
