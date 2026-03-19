<?php

namespace App\Http\Requests\Turnos;

use App\Models\Caja;
use App\Models\Turno;
use Illuminate\Foundation\Http\FormRequest;

class AbrirTurnoRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'caja_id'              => ['required', 'integer', 'exists:cajas,id'],
            'monto_apertura'       => ['required', 'numeric', 'min:0'],
            'monto_caja_chica'     => ['nullable', 'numeric', 'min:0'],
            'observacion_apertura' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $user = $this->user();

            // Verificar que el usuario no tenga ya un turno abierto
            if (Turno::where('user_id', $user->id)->where('estado', 'abierto')->exists()) {
                $validator->errors()->add('caja_id', 'Ya tienes un turno abierto.');
                return;
            }

            $caja = Caja::find($this->input('caja_id'));
            if (!$caja) return;

            // Verificar que la caja pertenezca al local del usuario
            if ($user->local_id && $caja->local_id !== $user->local_id) {
                $validator->errors()->add('caja_id', 'Esta caja no pertenece a tu local.');
                return;
            }

            // Verificar que la caja no tenga ya un turno abierto
            if (Turno::where('caja_id', $caja->id)->where('estado', 'abierto')->exists()) {
                $validator->errors()->add('caja_id', 'Esta caja ya tiene un turno abierto.');
            }
        });
    }
}
