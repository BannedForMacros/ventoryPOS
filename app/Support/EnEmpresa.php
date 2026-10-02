<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Reglas de validación acotadas a la empresa del usuario: un id de OTRA
 * empresa no pasa (aislamiento multiempresa). Reemplazan a 'exists:tabla,id',
 * que acepta cualquier id de la tabla compartida.
 *
 *   'producto_id' => ['required', EnEmpresa::existe('productos')],
 */
final class EnEmpresa
{
    /** exists:tabla,id solo dentro de la empresa (la tabla tiene empresa_id). */
    public static function existe(string $tabla): Exists
    {
        return Rule::exists($tabla, 'id')->where('empresa_id', self::empresaId());
    }

    /** Presentación (producto_unidades) de un producto de la empresa. */
    public static function presentacion(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            if ($value === null || $value === '') return;
            $ok = DB::table('producto_unidades as u')
                ->join('productos as p', 'p.id', '=', 'u.producto_id')
                ->where('u.id', $value)->where('p.empresa_id', self::empresaId())
                ->exists();
            if (!$ok) $fail('La presentación seleccionada no existe.');
        };
    }

    /** Cuenta de un medio de pago (pivote cuenta_metodo_pago) de la empresa. */
    public static function cuentaDePago(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            if ($value === null || $value === '') return;
            $ok = DB::table('cuenta_metodo_pago as cmp')
                ->join('cuentas as c', 'c.id', '=', 'cmp.cuenta_id')
                ->where('cmp.id', $value)->where('c.empresa_id', self::empresaId())
                ->exists();
            if (!$ok) $fail('La cuenta seleccionada no existe.');
        };
    }

    private static function empresaId(): int
    {
        return (int) (auth()->user()?->empresa_id ?? 0);
    }
}
