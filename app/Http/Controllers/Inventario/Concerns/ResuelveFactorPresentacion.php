<?php

namespace App\Http\Controllers\Inventario\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * El factor de conversión de cada línea (cuántas unidades base trae una
 * presentación) se toma del CATÁLOGO del producto (producto_unidades), nunca
 * del navegador.
 *
 * Antes el factor llegaba en el formulario y se guardaba tal cual: un factor
 * manipulado (o una presentación de OTRO producto) convertía "1 caja" en 1,000
 * unidades base y descuadraba stock, kardex y costo promedio.
 */
trait ResuelveFactorPresentacion
{
    /**
     * Devuelve las líneas con `factor_conversion` reemplazado por el del
     * catálogo. Si la presentación no pertenece al producto, falla con un
     * mensaje por línea.
     *
     * Al EDITAR un documento se pasan sus líneas guardadas (`$guardadas`): una
     * línea que conserva producto y presentación mantiene el factor con que se
     * registró. Si el catálogo cambió después (SACO de 50 a 25), reeditar el
     * documento solo para corregir la factura ya no cambia sus unidades base,
     * el stock ni el CPP; y si la presentación se borró, sigue guardándose.
     *
     * @param  array<int, array<string, mixed>>  $detalles
     * @param  iterable<object|array>|null  $guardadas  líneas actuales del documento (producto_id, unidad_medida_id, factor_conversion)
     * @return array<int, array<string, mixed>>
     */
    protected function resolverFactores(array $detalles, string $campo = 'detalles', ?iterable $guardadas = null): array
    {
        // Factor ya registrado por producto+presentación (y por id de línea).
        $previo = $previoPorId = [];
        foreach ($guardadas ?? [] as $g) {
            $g = (object) (is_object($g) && method_exists($g, 'getAttributes') ? $g->getAttributes() : $g);
            if ((float) ($g->factor_conversion ?? 0) <= 0) continue;
            $previo["{$g->producto_id}-{$g->unidad_medida_id}"] ??= (float) $g->factor_conversion;
            if (isset($g->id)) $previoPorId[$g->id] = $g;
        }

        $productoIds = collect($detalles)->pluck('producto_id')->filter()->unique()->values()->all();
        if (empty($productoIds)) {
            return $detalles;
        }

        // Una presentación activa gana sobre una desactivada (editar un documento
        // viejo cuya presentación se dio de baja sigue funcionando con su factor).
        $mapa = [];
        foreach (DB::table('producto_unidades')
            ->whereIn('producto_id', $productoIds)
            ->orderByDesc('activo')->orderByDesc('es_base')->orderBy('id')
            ->get(['producto_id', 'unidad_medida_id', 'factor_conversion']) as $u) {
            $mapa["{$u->producto_id}-{$u->unidad_medida_id}"] ??= (float) $u->factor_conversion;
        }

        $nombres = DB::table('productos')->whereIn('id', $productoIds)->pluck('nombre', 'id');
        $errores = [];

        foreach ($detalles as $i => $d) {
            $clave = "{$d['producto_id']}-{$d['unidad_medida_id']}";
            $linea = isset($d['id']) ? ($previoPorId[$d['id']] ?? null) : null;
            $factor = ($linea && "{$linea->producto_id}-{$linea->unidad_medida_id}" === $clave)
                ? (float) $linea->factor_conversion
                : ($previo[$clave] ?? $mapa[$clave] ?? null);
            if ($factor === null || $factor <= 0) {
                $nombre = $nombres[$d['producto_id']] ?? "el producto #{$d['producto_id']}";
                $errores["{$campo}.{$i}.unidad_medida_id"] = "La presentación elegida no pertenece a \"{$nombre}\". Vuelve a elegirla.";
                continue;
            }
            $detalles[$i]['factor_conversion'] = $factor;
        }

        if (!empty($errores)) {
            throw ValidationException::withMessages($errores);
        }

        return $detalles;
    }
}
