<?php

namespace App\Services;

use App\Models\Producto;
use App\Models\Stock;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Inventario inicial (stock de apertura) cargado desde la pantalla: a mano,
 * producto por producto, o desde un Excel cuyas columnas elige el usuario.
 *
 * Vive en `stock_iniciales` (una fila por almacén + producto). El stock del
 * producto arranca de esa cantidad al CIERRE de la fecha del conteo y solo
 * suma los movimientos posteriores (KardexService::movimientos). Por eso,
 * al guardar o quitar, se reconstruye el producto.
 *
 * Cantidades y costos se guardan en la unidad BASE del producto: si el Excel
 * trae "2 cajas x 12", se guardan 24 unidades y el costo de la caja / 12.
 */
class InventarioInicialService
{
    /**
     * Guarda (o reemplaza) el inventario inicial de varios productos.
     *
     * @param  array<int, array{producto_id:int, cantidad:float|int|string, producto_unidad_id?:int|null, costo?:float|int|string|null}>  $items
     * @return array{guardados:int, sin_costo:int}
     */
    public function guardar(int $empresaId, int $almacenId, string $fecha, array $items): array
    {
        $productos = Producto::deEmpresa($empresaId)->productos()
            ->whereIn('id', collect($items)->pluck('producto_id')->unique())
            ->with('unidades')
            ->get()->keyBy('id');

        // Un producto puede venir en varias filas (dos estantes, dos hojas del
        // conteo): se suman sus cantidades y el costo se promedia por cantidad.
        $porProducto = [];
        foreach ($items as $it) {
            $producto = $productos->get((int) $it['producto_id']);
            if (!$producto) {
                continue;
            }

            $factor = 1.0;
            if (!empty($it['producto_unidad_id'])) {
                $unidad = $producto->unidades->firstWhere('id', (int) $it['producto_unidad_id']);
                $factor = $unidad ? max((float) $unidad->factor_conversion, 0.0001) : 1.0;
            }

            $cantidadBase = round(self::numero($it['cantidad']) * $factor, 4);
            $costo        = self::numero($it['costo'] ?? null, null);
            $costoBase    = $costo === null ? null : round($costo / $factor, 4);

            $acc = $porProducto[$producto->id] ?? ['cantidad' => 0.0, 'valor' => 0.0, 'con_costo' => 0.0];
            $acc['cantidad'] += $cantidadBase;
            if ($costoBase !== null) {
                $acc['valor']     += $cantidadBase * $costoBase;
                $acc['con_costo'] += $cantidadBase;
                $acc['costo_ultimo'] = $costoBase;
            }
            $porProducto[$producto->id] = $acc;
        }

        if (!$porProducto) {
            return ['guardados' => 0, 'sin_costo' => 0];
        }

        $sugeridos = $this->costosSugeridos($almacenId, array_keys($porProducto));
        $sinCosto  = 0;
        $ahora     = now();

        DB::transaction(function () use ($porProducto, $sugeridos, $empresaId, $almacenId, $fecha, $ahora, &$sinCosto) {
            foreach ($porProducto as $productoId => $acc) {
                $costo = match (true) {
                    $acc['con_costo'] > 0      => round($acc['valor'] / $acc['con_costo'], 4),
                    isset($acc['costo_ultimo']) => $acc['costo_ultimo'],   // costo dado con cantidad 0
                    default                    => $sugeridos[$productoId] ?? 0.0,
                };
                if ($costo <= 0) {
                    $sinCosto++;
                }

                DB::table('stock_iniciales')->updateOrInsert(
                    ['almacen_id' => $almacenId, 'producto_id' => $productoId],
                    [
                        'empresa_id' => $empresaId,
                        'fecha'      => $fecha,
                        'cantidad'   => round($acc['cantidad'], 4),
                        'costo'      => $costo,
                        'updated_at' => $ahora,
                        'created_at' => $ahora,
                    ],
                );
            }
        });

        foreach (array_keys($porProducto) as $productoId) {
            Stock::reconstruir($almacenId, (int) $productoId);
        }

        return ['guardados' => count($porProducto), 'sin_costo' => $sinCosto];
    }

    /**
     * Fecha del inventario inicial de un producto que se crea hoy: la del
     * último conteo cargado en el almacén, para que quede junto a los demás.
     * Si nunca se cargó uno, o el último es de hoy, ayer: el conteo vale al
     * cierre de su fecha, y con fecha de hoy las ventas de hoy de este
     * producto no se descontarían.
     */
    public function fechaParaProductoNuevo(int $almacenId): string
    {
        $ayer   = now()->subDay()->toDateString();
        $ultima = DB::table('stock_iniciales')->where('almacen_id', $almacenId)->max('fecha');

        return $ultima ? min(substr((string) $ultima, 0, 10), $ayer) : $ayer;
    }

    /** Quita el inventario inicial: el stock vuelve a salir de todos sus movimientos. */
    public function quitar(int $almacenId, int $productoId): bool
    {
        $borrados = DB::table('stock_iniciales')
            ->where('almacen_id', $almacenId)->where('producto_id', $productoId)->delete();

        if ($borrados) {
            Stock::reconstruir($almacenId, $productoId);
        }

        return $borrados > 0;
    }

    /**
     * Costo a usar cuando no se indica uno: el que ya tenía su inventario
     * inicial, o si no el costo promedio actual del producto en ese almacén.
     *
     * @return array<int, float>
     */
    public function costosSugeridos(int $almacenId, array $productoIds): array
    {
        $iniciales = DB::table('stock_iniciales')->where('almacen_id', $almacenId)
            ->whereIn('producto_id', $productoIds)->where('costo', '>', 0)
            ->pluck('costo', 'producto_id');
        $promedios = DB::table('stock')->where('almacen_id', $almacenId)
            ->whereIn('producto_id', $productoIds)->where('costo_promedio', '>', 0)
            ->pluck('costo_promedio', 'producto_id');

        $res = [];
        foreach ($productoIds as $id) {
            $res[$id] = (float) ($iniciales[$id] ?? $promedios[$id] ?? 0);
        }

        return $res;
    }

    /**
     * Relaciona cada fila del Excel con un producto del catálogo.
     *
     * Por código (si la columna existe) y si no por nombre normalizado (sin
     * tildes, mayúsculas, espacios ni signos). Nunca adivina: lo que no calza
     * exacto vuelve como "sugerido" con los parecidos, para que el usuario
     * confirme.
     *
     * @param  array<int, array{fila:int, producto?:?string, codigo?:?string, cantidad?:mixed, unidad?:?string, costo?:mixed}>  $filas
     */
    public function emparejar(int $empresaId, int $almacenId, array $filas): array
    {
        $productos = Producto::deEmpresa($empresaId)->productos()->activo()
            ->with(['unidades' => fn ($q) => $q->where('activo', true)->with('unidadMedida:id,nombre,abreviatura')])
            ->get(['id', 'codigo', 'nombre']);

        $porCodigo = [];
        $porNombre = [];
        foreach ($productos as $p) {
            if ($p->codigo !== null && trim($p->codigo) !== '') {
                $porCodigo[mb_strtoupper(trim($p->codigo))][] = $p;
            }
            $porNombre[self::normalizar($p->nombre)][] = $p;
        }
        $tokens = $productos->mapWithKeys(fn ($p) => [$p->id => self::tokens($p->nombre)]);

        $yaCargados = DB::table('stock_iniciales')->where('almacen_id', $almacenId)
            ->whereIn('producto_id', $productos->pluck('id'))
            ->get(['producto_id', 'cantidad', 'fecha'])->keyBy('producto_id');

        $res = [];
        $vistos = [];
        foreach ($filas as $f) {
            $nombre = trim((string) ($f['producto'] ?? ''));
            $codigo = trim((string) ($f['codigo'] ?? ''));
            $fila = [
                'fila'     => (int) ($f['fila'] ?? 0),
                'texto'    => $nombre !== '' ? $nombre : $codigo,
                'codigo'   => $codigo,
                'unidad'   => trim((string) ($f['unidad'] ?? '')),
                'cantidad' => self::numero($f['cantidad'] ?? null, null),
                'costo'    => self::numero($f['costo'] ?? null, null),
                'estado'   => 'ok',
                'mensaje'  => null,
                'producto' => null,
                'sugerencias' => [],
            ];

            if ($nombre === '' && $codigo === '') {
                continue;   // fila vacía o de totales: no se muestra
            }

            // 1) Producto
            $candidatos = ($codigo !== '' ? ($porCodigo[mb_strtoupper($codigo)] ?? []) : [])
                ?: ($nombre !== '' ? ($porNombre[self::normalizar($nombre)] ?? []) : []);

            if (count($candidatos) === 1) {
                $fila['producto'] = $this->resumen($candidatos[0]);
            } elseif (count($candidatos) > 1) {
                $fila['estado']      = 'sugerido';
                $fila['mensaje']     = 'Hay ' . count($candidatos) . ' productos con ese nombre: elige cuál es.';
                $fila['sugerencias'] = array_map(fn ($p) => $this->resumen($p), $candidatos);
            } else {
                $fila['estado']      = 'sugerido';
                $fila['sugerencias'] = $nombre !== '' ? $this->parecidos($nombre, $tokens, $productos) : [];
                $fila['mensaje']     = $fila['sugerencias']
                    ? 'No está igual en el catálogo. ¿Es alguno de estos?'
                    : 'No se encontró en el catálogo.';
            }

            // 2) Cantidad
            if ($fila['cantidad'] === null) {
                $fila['estado']  = 'error';
                $fila['mensaje'] = 'La cantidad está vacía o no es un número.';
            } elseif ($fila['cantidad'] < 0) {
                $fila['estado']  = 'error';
                $fila['mensaje'] = 'La cantidad es negativa.';
            }

            // 3) Unidad (solo si ya sabemos el producto)
            if ($fila['producto'] && $fila['estado'] === 'ok') {
                $fila = $this->resolverUnidad($fila);
            }

            if ($fila['producto']) {
                $pid = $fila['producto']['id'];
                if (isset($vistos[$pid])) {
                    $fila['repetido_de'] = $vistos[$pid];
                } else {
                    $vistos[$pid] = $fila['fila'];
                }
                if ($ini = $yaCargados->get($pid)) {
                    $fila['ya_tenia'] = ['cantidad' => (float) $ini->cantidad, 'fecha' => substr((string) $ini->fecha, 0, 10)];
                }
            }

            $res[] = $fila;
        }

        return $res;
    }

    /** Busca productos por nombre o código (para elegir a mano en la revisión). */
    public function buscar(int $empresaId, string $q, int $limite = 12): Collection
    {
        $q = trim($q);

        return Producto::deEmpresa($empresaId)->productos()->activo()
            ->when($q !== '', fn ($qq) => $qq->where(fn ($w) => $w
                ->where('nombre', 'ilike', '%' . str_replace(' ', '%', $q) . '%')
                ->orWhere('codigo', 'ilike', "%{$q}%")))
            ->with(['unidades' => fn ($u) => $u->where('activo', true)->with('unidadMedida:id,nombre,abreviatura')])
            ->orderBy('nombre')->limit($limite)
            ->get(['id', 'codigo', 'nombre'])
            ->map(fn ($p) => $this->resumen($p));
    }

    // ── Auxiliares ──────────────────────────────────────────────────────────

    private function resumen(Producto $p): array
    {
        $unidades = $p->unidades
            ->sortByDesc('es_base')
            ->map(fn ($u) => [
                'id'     => $u->id,
                'nombre' => $u->unidadMedida?->nombre ?? 'Unidad',
                'abrev'  => $u->unidadMedida?->abreviatura,
                'factor' => (float) $u->factor_conversion,
                'base'   => (bool) $u->es_base,
            ])->values()->all();

        return ['id' => $p->id, 'nombre' => $p->nombre, 'codigo' => $p->codigo, 'unidades' => $unidades];
    }

    /** Unidad del Excel → unidad del producto. Vacía = unidad base. */
    private function resolverUnidad(array $fila): array
    {
        $unidades = $fila['producto']['unidades'];
        $base     = collect($unidades)->firstWhere('base', true) ?? ($unidades[0] ?? null);

        if ($fila['unidad'] === '') {
            $fila['producto_unidad_id'] = $base['id'] ?? null;
            return $fila;
        }

        $buscada = self::singular(self::normalizar($fila['unidad']));
        foreach ($unidades as $u) {
            foreach ([$u['nombre'], $u['abrev']] as $alias) {
                if ($alias !== null && self::singular(self::normalizar($alias)) === $buscada) {
                    $fila['producto_unidad_id'] = $u['id'];
                    return $fila;
                }
            }
        }

        $fila['estado']             = 'unidad';
        $fila['producto_unidad_id'] = $base['id'] ?? null;
        $fila['mensaje']            = "Este producto no tiene la unidad \"{$fila['unidad']}\": elige en qué unidad está contado.";

        return $fila;
    }

    /** Hasta 3 productos con más palabras en común (mínimo la mitad). */
    private function parecidos(string $nombre, Collection $tokens, Collection $productos): array
    {
        $buscado = self::tokens($nombre);
        if (!$buscado) {
            return [];
        }

        $puntajes = [];
        foreach ($tokens as $id => $t) {
            if (!$t) continue;
            $comunes = count(array_intersect($buscado, $t));
            if ($comunes === 0) continue;
            $score = $comunes / max(count($buscado), count($t));
            if ($score >= 0.5) {
                $puntajes[$id] = $score;
            }
        }
        arsort($puntajes);

        $porId = $productos->keyBy('id');

        return collect(array_slice(array_keys($puntajes), 0, 3, true))
            ->map(fn ($id) => $this->resumen($porId[$id]))->values()->all();
    }

    public static function normalizar(?string $s): string
    {
        $s = mb_strtoupper(Str::ascii((string) $s));
        $s = preg_replace('/[^A-Z0-9]+/', ' ', $s);
        // "90ML" = "90 ML", "1/2PULG" = "1 2 PULG": número y unidad pegados o no, es lo mismo.
        $s = preg_replace('/(?<=\d)(?=[A-Z])|(?<=[A-Z])(?=\d)/', ' ', $s);

        return trim(preg_replace('/\s+/', ' ', $s));
    }

    private static function tokens(string $s): array
    {
        return array_values(array_unique(array_filter(explode(' ', self::normalizar($s)), fn ($t) => $t !== '')));
    }

    private static function singular(string $s): string
    {
        return preg_replace('/(ES|S)$/', '', $s) ?: $s;
    }

    /**
     * "1,234.50", "1.234,50", "12,5", " 40 " → número. Vacío o texto → $vacio.
     * Una sola coma con 3 dígitos detrás es separador de miles ("1,500").
     */
    public static function numero(mixed $v, ?float $vacio = 0.0): ?float
    {
        if ($v === null) return $vacio;
        if (is_int($v) || is_float($v)) return (float) $v;

        $s = preg_replace('/[^\d,.\-]/', '', (string) $v);
        if ($s === '' || $s === '-') return $vacio;

        $coma  = strrpos($s, ',');
        $punto = strrpos($s, '.');
        if ($coma !== false && $punto !== false) {
            $s = $coma > $punto
                ? str_replace(['.', ','], ['', '.'], $s)   // 1.234,50
                : str_replace(',', '', $s);                // 1,234.50
        } elseif ($coma !== false) {
            $s = (substr_count($s, ',') === 1 && strlen($s) - $coma - 1 !== 3)
                ? str_replace(',', '.', $s)                // 12,5
                : str_replace(',', '', $s);                // 1,500
        }

        return is_numeric($s) ? (float) $s : $vacio;
    }
}
