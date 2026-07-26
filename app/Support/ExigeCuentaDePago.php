<?php

namespace App\Support;

use Closure;
use Illuminate\Http\Request;

/**
 * Regla de validación reutilizable: la cuenta es OBLIGATORIA cuando el método
 * de pago elegido tiene cuentas vinculadas. Se agrega a la regla de `cuenta_id`
 * (o `cuenta_metodo_pago_id`) en los formularios de pago de una sola línea.
 *
 * Uso:
 *   'cuenta_id' => ['nullable', 'integer', Rule::exists(...), $this->reglaCuentaObligatoria($request)],
 */
trait ExigeCuentaDePago
{
    protected function reglaCuentaObligatoria(Request $request, string $campoMetodo = 'metodo_pago_id'): Closure
    {
        return function ($attr, $value, $fail) use ($request, $campoMetodo) {
            $metodoId = $request->input($campoMetodo);
            if (! $value && PagoCuenta::requiere($metodoId ? (int) $metodoId : null)) {
                $fail('Debes seleccionar la cuenta para este método de pago.');
            }
        };
    }
}
