<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Carbon;

/**
 * La fecha de un movimiento de dinero o de stock no puede ser futura.
 *
 * Un gasto, pago o compra con fecha de mañana descuadra el balance de hoy
 * (entra en un día que aún no existe). Con mensaje propio: la regla de Laravel
 * `before_or_equal:today` le muestra "today" a la cajera.
 */
class NoFutura implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') return;

        try {
            $fecha = Carbon::parse($value)->startOfDay();
        } catch (\Throwable) {
            return; // la regla `date` ya avisa del formato
        }

        if ($fecha->gt(today())) {
            $fail('La fecha no puede ser posterior a hoy (' . today()->format('d/m/Y') . ').');
        }
    }
}
