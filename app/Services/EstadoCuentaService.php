<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\Proveedor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Estado de cuenta unificado por TERCERO.
 *
 * El sistema guarda la situación de un mismo tercero repartida en cuatro sitios
 * que nadie cruzaba: lo que nos debe (ventas a crédito), lo que le debemos
 * (entradas), lo que tiene a favor (anticipos) y lo que le adelantamos
 * (adelantos). Este servicio los junta en una sola fila por tercero.
 *
 * UNIFICACIÓN CLIENTE ↔ PROVEEDOR: `clientes` y `proveedores` son tablas
 * separadas sin ninguna FK entre ellas, así que el vínculo se calcula al vuelo
 * por número de documento normalizado. Un tercero sin documento no se puede
 * cruzar y queda como fila propia — es un límite de los datos, no del diseño.
 */
class EstadoCuentaService
{
    /**
     * Clave de agrupación de un tercero. Si tiene documento, ese documento
     * une al cliente con el proveedor homónimo; si no, cae a su propio id.
     */
    public static function clave(?string $documento, string $fallbackTipo, int $fallbackId): string
    {
        $doc = strtoupper(trim((string) $documento));

        return $doc !== '' ? "doc:{$doc}" : "{$fallbackTipo}:{$fallbackId}";
    }

    /** Clave de una compra cuyo proveedor quedó como texto libre (sin proveedor_id). */
    public static function claveTextoLibre(string $nombre): string
    {
        return 'txt:' . mb_strtolower(trim($nombre));
    }

    /**
     * Una fila por tercero con sus cuatro componentes y el neto.
     *
     * neto > 0 → a nuestro favor (nos deben)
     * neto < 0 → en nuestra contra (le debemos)
     */
    public function resumen(int $empresaId): Collection
    {
        $terceros = [];

        /** Crea (o recupera) la fila del tercero para una clave dada. */
        $fila = function (string $clave) use (&$terceros): int {
            if (!isset($terceros[$clave])) {
                $terceros[$clave] = [
                    'clave'             => $clave,
                    'nombre'            => '—',
                    'documento'         => null,
                    'cliente_id'        => null,
                    'proveedor_id'      => null,
                    'nos_debe'          => 0.0,
                    'le_debemos'        => 0.0,
                    'su_anticipo'       => 0.0,
                    'nuestro_adelanto'  => 0.0,
                    'docs_por_cobrar'   => 0,
                    'docs_por_pagar'    => 0,
                    'mas_antiguo'       => null,
                    'sin_identificar'   => false,
                ];
            }

            return 1;
        };

        // ── Identidades: clientes y proveedores ──────────────────────────────
        $clientes = Cliente::deEmpresa($empresaId)
            ->get(['id', 'numero_documento', 'nombres', 'apellidos', 'razon_social']);

        $claveDeCliente = [];
        foreach ($clientes as $c) {
            $clave = self::clave($c->numero_documento, 'cli', $c->id);
            $claveDeCliente[$c->id] = $clave;
            $fila($clave);
            $terceros[$clave]['nombre']     = $c->nombre_completo;
            $terceros[$clave]['documento']  = $c->numero_documento ?: null;
            $terceros[$clave]['cliente_id'] = $c->id;
        }

        $proveedores = Proveedor::deEmpresa($empresaId)
            ->get(['id', 'numero_documento', 'razon_social', 'nombre_comercial']);

        $claveDeProveedor = [];
        foreach ($proveedores as $p) {
            $clave = self::clave($p->numero_documento, 'prov', $p->id);
            $claveDeProveedor[$p->id] = $clave;
            $fila($clave);
            // Si ya existía como cliente conservamos ese nombre: el cruce es el
            // mismo tercero, y el nombre del cliente suele ser el más usado.
            if ($terceros[$clave]['proveedor_id'] === null && $terceros[$clave]['cliente_id'] === null) {
                $terceros[$clave]['nombre'] = $p->nombre_mostrado;
            }
            $terceros[$clave]['documento']    = $terceros[$clave]['documento'] ?: ($p->numero_documento ?: null);
            $terceros[$clave]['proveedor_id'] = $p->id;
        }

        // ── 1. Nos debe: ventas a crédito con saldo ──────────────────────────
        DB::table('ventas')
            ->where('empresa_id', $empresaId)
            ->where('es_credito', true)
            ->where('estado', 'completada')
            ->where('saldo_pendiente', '>', 0)
            ->whereNotNull('cliente_id')
            ->groupBy('cliente_id')
            ->selectRaw('cliente_id, SUM(saldo_pendiente) AS total, COUNT(*) AS docs, MIN(fecha_venta) AS mas_antiguo')
            ->get()
            ->each(function ($r) use (&$terceros, $claveDeCliente, $fila) {
                $clave = $claveDeCliente[$r->cliente_id] ?? null;
                if (!$clave) {
                    return;
                }
                $fila($clave);
                $terceros[$clave]['nos_debe']        = round((float) $r->total, 2);
                $terceros[$clave]['docs_por_cobrar'] = (int) $r->docs;
                $terceros[$clave]['mas_antiguo']     = self::menorFecha($terceros[$clave]['mas_antiguo'], $r->mas_antiguo);
            });

        // ── 2. Le debemos: compras confirmadas no pagadas ────────────────────
        DB::table('entradas')
            ->where('empresa_id', $empresaId)
            ->where('estado', 'confirmado')
            ->whereRaw('COALESCE(total, 0) - COALESCE(monto_pagado, 0) > 0')
            ->get(['id', 'proveedor_id', 'proveedor', 'total', 'monto_pagado', 'fecha'])
            ->each(function ($e) use (&$terceros, $claveDeProveedor, $fila) {
                $saldo = round((float) $e->total - (float) $e->monto_pagado, 2);

                // Compras con el proveedor como texto libre (sin FK): se agrupan
                // por nombre y se marcan, para que el total no mienta por omisión.
                if ($e->proveedor_id && isset($claveDeProveedor[$e->proveedor_id])) {
                    $clave = $claveDeProveedor[$e->proveedor_id];
                } elseif (trim((string) $e->proveedor) !== '') {
                    $clave = self::claveTextoLibre($e->proveedor);
                    $fila($clave);
                    $terceros[$clave]['nombre']          = trim($e->proveedor);
                    $terceros[$clave]['sin_identificar'] = true;
                } else {
                    $clave = 'txt:sin-proveedor';
                    $fila($clave);
                    $terceros[$clave]['nombre']          = 'Compras sin proveedor';
                    $terceros[$clave]['sin_identificar'] = true;
                }

                $fila($clave);
                $terceros[$clave]['le_debemos']    += $saldo;
                $terceros[$clave]['docs_por_pagar'] += 1;
                $terceros[$clave]['mas_antiguo']    = self::menorFecha($terceros[$clave]['mas_antiguo'], $e->fecha);
            });

        // ── 3. Su anticipo: lo que el cliente pagó y aún no se lleva ─────────
        // Siempre al precio CONGELADO de la venta (el `saldo`), nunca al precio
        // del día: el cliente pagó un precio concreto y ese se le respeta.
        DB::table('cliente_anticipos')
            ->where('empresa_id', $empresaId)
            ->where('estado', 'activo')
            ->where('saldo', '>', 0)
            ->groupBy('cliente_id')
            ->selectRaw('cliente_id, SUM(saldo) AS total')
            ->get()
            ->each(function ($r) use (&$terceros, $claveDeCliente, $fila) {
                $clave = $claveDeCliente[$r->cliente_id] ?? null;
                if (!$clave) {
                    return;
                }
                $fila($clave);
                $terceros[$clave]['su_anticipo'] = round((float) $r->total, 2);
            });

        // ── 4. Nuestro adelanto: plata puesta al proveedor sin consumir ──────
        DB::table('proveedor_adelantos')
            ->where('empresa_id', $empresaId)
            ->where('estado', 'activo')
            ->where('saldo', '>', 0)
            ->groupBy('proveedor_id')
            ->selectRaw('proveedor_id, SUM(saldo) AS total')
            ->get()
            ->each(function ($r) use (&$terceros, $claveDeProveedor, $fila) {
                $clave = $claveDeProveedor[$r->proveedor_id] ?? null;
                if (!$clave) {
                    return;
                }
                $fila($clave);
                $terceros[$clave]['nuestro_adelanto'] = round((float) $r->total, 2);
            });

        // ── Neto y roles ─────────────────────────────────────────────────────
        return collect($terceros)
            ->map(function (array $t) {
                $t['nos_debe']         = round($t['nos_debe'], 2);
                $t['le_debemos']       = round($t['le_debemos'], 2);
                $t['neto']             = round(
                    ($t['nos_debe'] + $t['nuestro_adelanto']) - ($t['le_debemos'] + $t['su_anticipo']),
                    2,
                );
                $t['es_cliente']       = $t['cliente_id']   !== null;
                $t['es_proveedor']     = $t['proveedor_id'] !== null || $t['sin_identificar'];
                $t['rol']              = match (true) {
                    $t['es_cliente'] && $t['es_proveedor'] => 'ambos',
                    $t['es_proveedor']                     => 'proveedor',
                    default                                => 'cliente',
                };
                $t['dias_antiguedad']  = $t['mas_antiguo']
                    ? (int) now()->startOfDay()->diffInDays(\Carbon\Carbon::parse($t['mas_antiguo'])->startOfDay())
                    : null;

                return $t;
            })
            // Solo terceros con algo vivo: sin saldo no hay estado de cuenta.
            ->filter(fn (array $t) => $t['nos_debe'] > 0 || $t['le_debemos'] > 0
                || $t['su_anticipo'] > 0 || $t['nuestro_adelanto'] > 0)
            ->sortByDesc(fn (array $t) => abs($t['neto']))
            ->values();
    }

    /** Devuelve la menor de dos fechas (cualquiera puede ser null). */
    private static function menorFecha(?string $a, $b): ?string
    {
        $b = $b ? substr((string) $b, 0, 10) : null;
        if (!$a) {
            return $b;
        }
        if (!$b) {
            return $a;
        }

        return $b < $a ? $b : $a;
    }
}
