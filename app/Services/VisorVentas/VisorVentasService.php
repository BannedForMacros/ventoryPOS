<?php

namespace App\Services\VisorVentas;

use App\Models\Empresa;
use App\Models\User;
use App\Models\Venta;
use App\Models\VisorVentaCobrada;
use App\Models\VisorVentaSesion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Visor de ventas: la cajera fotografía su cuaderno y el sistema le dice qué
 * entendió (verde), qué duda (ámbar, con sugerencias de su catálogo) y qué no
 * (rojo). Nunca registra nada solo: cada venta leída se carga al carrito y se
 * cobra como siempre.
 *
 * Es una función del plan: la enciende el superadmin y trae un límite de
 * lecturas por día (cada foto gasta créditos de la API).
 *
 * Además aprende: lo que la cajera corrige ("guts" → Guantes quirúrgicos) se
 * recuerda al cobrar, y la próxima foto lo pone en verde solo. Y recuerda lo
 * cobrado, para avisar si una página se vuelve a cargar.
 */
class VisorVentasService
{
    /** Mensaje del plan al llegar al límite (lo pidió así el dueño del sistema). */
    public const MENSAJE_LIMITE = 'Su plan es solo para :n sesiones máximas por día.';

    public function __construct(private LectorCuaderno $lector) {}

    public function disponible(?Empresa $empresa): bool
    {
        return (bool) $empresa?->usa_visor_ventas;
    }

    /** Topes a lo que devuelve el lector: una foto rara (o con texto que intente manipularlo) no infla nada. */
    private const MAX_VENTAS = 200;
    private const MAX_RENGLONES = 50;
    private const MAX_TOTAL = 100000;
    private const MAX_CANTIDAD = 10000;

    /** Palabras que no identifican un producto: no se aprenden (pondrían en verde cualquier renglón). */
    private const GENERICAS = ['pastilla', 'pastillas', 'tableta', 'tabletas', 'unidad', 'unidades', 'caja', 'cajas',
        'sobre', 'sobres', 'frasco', 'frascos', 'blister', 'ampolla', 'ampollas', 'jarabe', 'crema', 'varios', 'otro', 'otros'];

    /**
     * Lecturas que ya cuentan hoy: las leídas, las que se están leyendo y las
     * que la API cobró aunque no sirvieran. Solo los fallos sin cobro (sin
     * conexión, sin clave) no gastan el cupo.
     */
    public function usadasHoy(Empresa $empresa): int
    {
        return VisorVentaSesion::where('empresa_id', $empresa->id)
            ->where('created_at', '>=', today())
            ->whereIn('estado', ['procesando', 'leida', 'fallida'])
            ->count();
    }

    public function restantesHoy(Empresa $empresa): int
    {
        return max(0, (int) $empresa->visor_ventas_limite_diario - $this->usadasHoy($empresa));
    }

    /**
     * Lee una foto y la cruza con el catálogo de la empresa.
     *
     * @throws LecturaFallida
     */
    public function procesar(User $user, string $imagenBase64, string $mediaType): array
    {
        $empresa = $user->empresa;
        if (!$this->disponible($empresa)) {
            throw new LecturaFallida('El visor de ventas no está incluido en el plan de tu empresa.');
        }

        $hash   = hash('sha256', $imagenBase64);
        $limite = (int) $empresa->visor_ventas_limite_diario;
        $agotado = fn () => new LecturaFallida(str_replace(':n', (string) $limite, self::MENSAJE_LIMITE));

        // Todo con la fila de la empresa bloqueada: dos fotos a la vez no pasan
        // las dos el límite, ni la misma foto se paga dos veces.
        [$sesion, $previa] = DB::transaction(function () use ($empresa, $user, $hash, $limite, $agotado) {
            Empresa::whereKey($empresa->id)->lockForUpdate()->first();

            // La misma foto ya leída se devuelve gratis (se cortó la conexión,
            // recargó el POS, tocó "Leer otra foto" por error).
            $previa = VisorVentaSesion::where('empresa_id', $empresa->id)->where('foto_hash', $hash)
                ->whereIn('estado', ['leida', 'procesando'])->latest('id')->first();
            if ($previa?->estado === 'leida' && is_array($previa->lectura)) {
                // Otra cajera u otro día: se le anota una copia gratis, para que al
                // volver de cobrar su revisión siga ahí (ultimaLectura es por persona y día).
                if ($previa->user_id !== $user->id || $previa->created_at->lt(today())) {
                    $copia = VisorVentaSesion::create([
                        'empresa_id' => $empresa->id, 'user_id' => $user->id, 'estado' => 'reusada', 'foto_hash' => $hash,
                        'origen_id' => $previa->origen_id ?? $previa->id, 'ventas_leidas' => $previa->ventas_leidas,
                        'lectura' => $previa->lectura, 'cruce' => $previa->cruce,
                    ]);

                    return [null, [$previa, $copia]];
                }

                return [null, [$previa, $previa]];
            }
            if ($previa?->estado === 'procesando' && $previa->created_at->gt(now()->subMinutes(3))) {
                throw new LecturaFallida('Esta foto todavía se está leyendo. Espera un minuto y vuelve a tocar "Leer ventas": no gasta otra lectura.');
            }

            // Tope duro de intentos (también los fallidos sin cobro): nadie
            // puede llamar a la API sin fin. Las copias gratis no cuentan.
            $intentos = VisorVentaSesion::where('empresa_id', $empresa->id)->where('created_at', '>=', today())
                ->where('estado', '!=', 'reusada')->count();
            if ($intentos >= $limite * 3 || $this->restantesHoy($empresa) <= 0) {
                throw $agotado();
            }

            return [VisorVentaSesion::create(['empresa_id' => $empresa->id, 'user_id' => $user->id, 'estado' => 'procesando', 'foto_hash' => $hash]), null];
        });

        if ($previa) {
            [$original, $propia] = $previa;

            return $this->respuesta($empresa, $propia) + [
                'aviso' => 'Esta foto ya se leyó el ' . $original->created_at->format('d/m') . ' a las ' . $original->created_at->format('g:i a') . '. Te mostramos lo mismo, sin gastar otra lectura.',
            ];
        }

        try {
            $lectura = $this->lector->leer($imagenBase64, $mediaType);
        } catch (LecturaFallida $e) {
            $sesion->update([
                'estado' => $e->cobrada ? 'fallida' : 'error', 'error' => $e->getMessage(),
                'modelo' => $e->modelo, 'tokens_entrada' => $e->tokensEntrada, 'tokens_salida' => $e->tokensSalida,
            ]);
            throw $e;
        } catch (\Throwable $e) {
            Log::warning('Visor de ventas: error inesperado del lector', ['error' => $e->getMessage()]);
            $sesion->update(['estado' => 'error', 'error' => mb_substr($e->getMessage(), 0, 500)]);
            throw new LecturaFallida('No se pudo leer la foto en este momento. Intenta de nuevo en unos minutos.', 0, $e);
        }

        try {
            $limpias = self::normalizarLectura($lectura['ventas'] ?? []);
            $cruce   = $this->cruzarVentas($empresa->id, $limpias);
        } catch (\Throwable $e) {
            // La API ya cobró: gasta el cupo aunque lo devuelto no sirva.
            Log::warning('Visor de ventas: lectura con formato inesperado', ['error' => $e->getMessage()]);
            $sesion->update(['estado' => 'fallida', 'error' => mb_substr($e->getMessage(), 0, 500), 'modelo' => $lectura['modelo'] ?? null]);
            throw new LecturaFallida('No se entendió la foto. Toma otra con buena luz, de frente y con la página completa.', 0, $e);
        }

        $sesion->update([
            'estado'         => 'leida',
            'ventas_leidas'  => count($cruce),
            'modelo'         => $lectura['modelo'] ?? null,
            'tokens_entrada' => $lectura['tokens_entrada'] ?? null,
            'tokens_salida'  => $lectura['tokens_salida'] ?? null,
            'lectura'        => ['ventas' => $limpias],
            'cruce'          => $cruce,
        ]);

        return $this->respuesta($empresa, $sesion);
    }

    /** Lo que recibe el POS de una lectura: lo cruzado (guardado) más qué ya se cobró, al día. */
    private function respuesta(Empresa $empresa, VisorVentaSesion $sesion): array
    {
        $lecturaId = $sesion->origen_id ?? $sesion->id;
        // Lecturas guardadas antes de existir `cruce`: se cruzan una vez.
        $cruce = is_array($sesion->cruce) ? $sesion->cruce : $this->cruzarVentas($empresa->id, $sesion->lectura['ventas'] ?? []);

        return [
            'sesion'    => $lecturaId,
            'ventas'    => $this->marcarCobradas($empresa->id, $cruce, $lecturaId),
            'restantes' => $this->restantesHoy($empresa),
        ];
    }

    /**
     * La última foto que la cajera leyó hoy: al cobrar se sale del POS y, al
     * volver, la revisión sigue donde estaba (las ya cobradas vienen marcadas)
     * sin gastar otra lectura ni volver a cruzar con el catálogo.
     */
    public function ultimaLectura(User $user): ?array
    {
        $sesion = VisorVentaSesion::where('empresa_id', $user->empresa_id)->where('user_id', $user->id)
            ->whereIn('estado', ['leida', 'reusada'])->whereNotNull('lectura')->where('created_at', '>=', today())
            ->latest('id')->first();
        if (!$sesion) return null;

        $r = $this->respuesta($user->empresa, $sesion);

        return $r['ventas'] ? ['sesion' => $r['sesion'], 'ventas' => $r['ventas'], 'leida' => $sesion->created_at->format('g:i a')] : null;
    }

    /** Ventas cruzadas con el catálogo (con su posición en la página); las que no traen renglones se descartan. */
    private function cruzarVentas(int $empresaId, array $ventas): array
    {
        $cruzadas = array_values(array_filter(
            array_map(fn ($v) => $this->cruzarVenta($empresaId, $v), $ventas),
            fn ($v) => count($v['items']) > 0,
        ));
        foreach ($cruzadas as $i => &$v) $v['indice'] = $i;

        return $cruzadas;
    }

    /**
     * Qué ventas de la página ya se cobraron.
     *
     * - `ya_cobrada` (seguro): ESA venta de ESTA lectura ya se cobró. El mismo
     *   producto vendido varias veces en el día da ventas iguales que son
     *   distintas: por eso se mira la posición, no el contenido.
     * - `posible_cobrada` (aviso, pide confirmar): otra foto de la MISMA página.
     *   Solo si la página entera coincide (2+ ventas iguales a las cobradas de
     *   otra lectura, o es una página de una sola venta): una venta suelta igual
     *   en otra hoja del día es otra venta.
     */
    private function marcarCobradas(int $empresaId, array $ventas, ?int $lecturaId): array
    {
        if (!$ventas) return [];
        $base = fn () => DB::table('visor_ventas_cobradas as c')
            ->join('ventas as v', 'v.id', '=', 'c.venta_id')
            ->leftJoin('users as u', 'u.id', '=', 'c.user_id')
            ->where('c.empresa_id', $empresaId)->where('v.estado', 'completada');
        $info = fn ($f) => ['venta' => $f->numero, 'cuando' => \Illuminate\Support\Carbon::parse($f->created_at)->format('d/m g:i a'), 'por' => $f->name];

        $exactas = $lecturaId
            ? $base()->where('c.sesion_id', $lecturaId)->orderBy('c.id')->get(['c.indice', 'v.numero', 'c.created_at', 'u.name'])->keyBy('indice')
            : collect();

        $otras = $base()
            ->whereIn('c.huella_texto', array_values(array_unique(array_column($ventas, 'huella'))))
            ->where(fn ($q) => $q->whereNull('c.sesion_id')->when($lecturaId, fn ($q2) => $q2->orWhere('c.sesion_id', '!=', $lecturaId)))
            ->where('c.created_at', '>=', now()->subDays(7))
            ->orderBy('c.id')
            ->get(['c.id', 'c.sesion_id', 'c.huella_texto', 'c.created_at', 'v.numero', 'u.name']);

        // Por cada lectura anterior: qué ventas de esta página le corresponden
        // (la k-ésima venta igual ↔ la k-ésima cobrada igual de esa lectura).
        $posibles = [];
        foreach ($otras->groupBy(fn ($f) => $f->sesion_id ?? 'c' . $f->id) as $filas) {
            $porHuella = $filas->groupBy('huella_texto');
            $usadas = [];
            $match = [];
            foreach ($ventas as $i => $v) {
                // Sin fecha en la columna, solo cuenta lo cobrado hoy.
                $k = $usadas[$v['huella']] = ($usadas[$v['huella']] ?? -1) + 1;
                $fila = $porHuella->get($v['huella'])?->values()->get($k);
                if ($fila && ($v['fecha'] !== null || \Illuminate\Support\Carbon::parse($fila->created_at)->gte(today()))) {
                    $match[$i] = $info($fila);
                }
            }
            if (count($match) >= 2 || (count($ventas) === 1 && count($match) === 1)) {
                $posibles += $match;
            }
        }

        foreach ($ventas as $i => &$v) {
            $exacta = $exactas->get($v['indice'] ?? $i);
            $v['ya_cobrada'] = $exacta ? $info($exacta) : null;
            $v['posible_cobrada'] = $exacta ? null : ($posibles[$i] ?? null);
        }

        return $ventas;
    }

    /**
     * Lo que devuelve el lector, con tipos y tamaños acotados. Nada de lo leído
     * se toma tal cual: cantidades y totales absurdos quedan en "no se leyó".
     */
    public static function normalizarLectura(mixed $ventas): array
    {
        if (!is_array($ventas)) return [];

        $limpias = [];
        foreach (array_slice(array_values($ventas), 0, self::MAX_VENTAS) as $v) {
            if (!is_array($v)) continue;
            $items = [];
            foreach (array_slice(array_values(is_array($v['items'] ?? null) ? $v['items'] : []), 0, self::MAX_RENGLONES) as $i) {
                if (!is_array($i) || !is_scalar($i['texto'] ?? null)) continue;
                $texto = mb_substr(trim((string) $i['texto']), 0, 120);
                if ($texto === '') continue;
                $cant = $i['cantidad'] ?? null;
                $items[] = [
                    'cantidad'       => is_numeric($cant) && $cant > 0 && $cant <= self::MAX_CANTIDAD ? (float) $cant : null,
                    'texto'          => $texto,
                    'interpretacion' => is_scalar($i['interpretacion'] ?? null) && trim((string) $i['interpretacion']) !== ''
                        ? mb_substr(trim((string) $i['interpretacion']), 0, 120) : null,
                    'seguro'         => ($i['seguro'] ?? false) === true,
                ];
            }
            $total = $v['total'] ?? null;
            $limpias[] = [
                'fecha' => self::fecha($v['fecha'] ?? null),
                'total' => is_numeric($total) && $total >= 0 && $total <= self::MAX_TOTAL ? round((float) $total, 2) : null,
                'items' => $items,
            ];
        }

        return $limpias;
    }

    /** La fecha tal como se leyó, recortada igual al leer, al verificar y al cobrar (si no, las huellas no coinciden). */
    public static function fecha(mixed $fecha): ?string
    {
        if (!is_scalar($fecha)) return null;
        $f = mb_substr(trim(preg_replace('/\s+/', ' ', (string) $fecha)), 0, 20);

        return $f === '' ? null : $f;
    }

    /**
     * ¿Ya se cobró una venta igual (misma fecha, total y productos)? Lo pregunta
     * el POS justo antes de cargarla al carrito.
     */
    /**
     * Justo antes de cargar: ¿esa venta de esa lectura ya se cobró? (otra cajera
     * pudo cobrarla mientras tanto).
     */
    public function yaCobrada(int $empresaId, int $lecturaId, int $indice): ?array
    {
        $f = DB::table('visor_ventas_cobradas as c')
            ->join('ventas as v', 'v.id', '=', 'c.venta_id')
            ->leftJoin('users as u', 'u.id', '=', 'c.user_id')
            ->where('c.empresa_id', $empresaId)->where('v.estado', 'completada')
            ->where('c.sesion_id', $lecturaId)->where('c.indice', $indice)
            ->first(['v.numero', 'c.created_at', 'u.name']);

        return $f ? ['venta' => $f->numero, 'cuando' => \Illuminate\Support\Carbon::parse($f->created_at)->format('d/m g:i a'), 'por' => $f->name] : null;
    }

    /**
     * Al cobrar una venta que vino del cuaderno: la recuerda (para no cobrarla
     * dos veces) y aprende lo que la cajera eligió para cada texto escrito.
     * Lo manda el navegador: se acota todo y solo se aprende lo que de verdad
     * quedó en la venta.
     */
    public function registrarCobro(Venta $venta, User $user, array $visor): void
    {
        // Un reintento con la misma idempotency_key devuelve la venta existente: no se registra dos veces.
        if (VisorVentaCobrada::where('venta_id', $venta->id)->exists()) return;

        $enVenta = DB::table('venta_items')->where('venta_id', $venta->id)->pluck('producto_id')->map(fn ($id) => (int) $id)->all();

        $items = collect(array_slice(is_array($visor['items'] ?? null) ? array_values($visor['items']) : [], 0, self::MAX_RENGLONES))
            ->filter(fn ($i) => is_array($i) && is_scalar($i['texto'] ?? null) && is_numeric($i['producto_id'] ?? null))
            ->map(fn ($i) => [
                'texto'       => mb_substr(trim((string) $i['texto']), 0, 120),
                'producto_id' => (int) $i['producto_id'],
                'cantidad'    => is_numeric($i['cantidad'] ?? null) ? (float) $i['cantidad'] : null,
            ])
            ->filter(fn ($i) => $i['texto'] !== '' && in_array($i['producto_id'], $enVenta, true))
            ->values();
        if ($items->isEmpty()) return;

        $fecha = self::fecha($visor['fecha'] ?? null);
        $total = is_numeric($visor['total'] ?? null) ? round((float) $visor['total'], 2) : null;
        // La huella de lo LEÍDO (antes de que la cajera corrigiera): la calcula
        // la lectura y vuelve tal cual, para reconocer la misma página otra vez.
        $huellaTexto = is_string($visor['huella'] ?? null) && preg_match('/^[a-f0-9]{64}$/', $visor['huella'])
            ? $visor['huella']
            : self::huellaTexto($fecha, $total, $items->all());

        // De qué lectura y en qué posición venía (solo si la lectura es de la empresa).
        $sesionId = is_numeric($visor['sesion'] ?? null)
            ? VisorVentaSesion::where('empresa_id', $venta->empresa_id)->whereKey((int) $visor['sesion'])->value('id')
            : null;
        $indice = $sesionId && is_numeric($visor['indice'] ?? null) ? max(0, (int) $visor['indice']) : null;

        DB::transaction(function () use ($venta, $user, $items, $fecha, $total, $huellaTexto, $sesionId, $indice) {
            VisorVentaCobrada::create([
                'empresa_id'       => $venta->empresa_id,
                'venta_id'         => $venta->id,
                'sesion_id'        => $sesionId,
                'indice'           => $indice,
                'user_id'          => $user->id,
                'fecha_cuaderno'   => $fecha,
                'total_cuaderno'   => $total,
                'huella_texto'     => $huellaTexto,
                'huella_productos' => self::huellaProductos($fecha, $total, $items->all()),
            ]);

            foreach ($items as $i) {
                $clave = self::clave($i['texto']);
                if (!self::aprendible($clave)) continue;
                DB::table('visor_ventas_aprendizaje')->upsert(
                    [['empresa_id' => $venta->empresa_id, 'texto' => $clave, 'producto_id' => $i['producto_id'], 'veces' => 1, 'created_at' => now(), 'updated_at' => now()]],
                    ['empresa_id', 'texto'],
                    ['producto_id' => $i['producto_id'], 'veces' => DB::raw('visor_ventas_aprendizaje.veces + 1'), 'updated_at' => now()],
                );
            }
        });
    }

    /** "pastillas", "1" o "x" no dicen qué producto es: aprenderlos pondría en verde cualquier renglón. */
    private static function aprendible(string $clave): bool
    {
        return mb_strlen($clave) >= 4 && preg_match('/[a-z]{3}/', $clave) && !in_array($clave, self::GENERICAS, true);
    }

    /** Una venta leída, con cada renglón cruzado contra el catálogo y su aviso si ya se cobró. */
    private function cruzarVenta(int $empresaId, array $venta): array
    {
        $items = [];
        foreach ($venta['items'] ?? [] as $item) {
            $texto = trim((string) ($item['texto'] ?? ''));
            if ($texto === '') continue;
            $items[] = $this->cruzarItem($empresaId, $item, $texto);
        }

        $fecha  = self::fecha($venta['fecha'] ?? null);
        $total  = isset($venta['total']) ? round((float) $venta['total'], 2) : null;
        $huella = self::huellaTexto($fecha, $total, $items);

        return [
            'fecha'      => $fecha,
            'total'      => $total,
            'items'      => $items,
            'huella'     => $huella,
            // Misma página leída otra vez (otra foto): lo dice antes de cargar
            // (lo resuelve cruzarVentas, que cuenta las ventas iguales).
            'ya_cobrada' => null,
        ];
    }

    /**
     * Verde: un solo producto claro del catálogo (o uno que la cajera ya eligió
     * antes para ese mismo texto) y cantidad leída.
     * Ámbar: hay candidatos pero dudosos o varios parecidos (elige la cajera).
     * Rojo: nada parecido en el catálogo.
     */
    private function cruzarItem(int $empresaId, array $item, string $texto): array
    {
        $cantidad = is_numeric($item['cantidad'] ?? null) && $item['cantidad'] > 0 ? (float) $item['cantidad'] : null;
        $base = ['texto' => $texto, 'interpretacion' => $item['interpretacion'] ?? null, 'cantidad' => $cantidad];

        // Lo aprendido manda: si ya lo corrigió antes, es ese producto.
        $aprendido = $this->aprendido($empresaId, $texto);
        if ($aprendido) {
            return $base + ['estado' => $cantidad !== null ? 'verde' : 'ambar', 'aprendido' => true, 'candidatos' => [$aprendido]];
        }

        $candidatos = $this->candidatos($empresaId, array_filter([$item['interpretacion'] ?? null, $texto]));
        $mejor   = $candidatos[0]['puntaje'] ?? 0;
        $segundo = $candidatos[1]['puntaje'] ?? 0;

        $estado = 'rojo';
        if ($mejor >= 0.45) {
            $claro  = $mejor >= 0.75 && ($mejor - $segundo) >= 0.08;
            $estado = $claro && $cantidad !== null && ($item['seguro'] ?? false) ? 'verde' : 'ambar';
        }

        return $base + ['estado' => $estado, 'aprendido' => false, 'candidatos' => $estado === 'rojo' ? [] : $candidatos];
    }

    private function aprendido(int $empresaId, string $texto): ?array
    {
        $clave = self::clave($texto);
        if (!self::aprendible($clave)) return null;

        $id = DB::table('visor_ventas_aprendizaje as a')
            ->join('productos as p', 'p.id', '=', 'a.producto_id')
            ->where('a.empresa_id', $empresaId)->where('a.texto', $clave)->where('p.activo', true)
            ->value('a.producto_id');

        return $id ? ($this->fichas($empresaId, [(int) $id => 1.0])[0] ?? null) : null;
    }

    /**
     * Hasta 3 productos del catálogo que más se parecen. word_similarity premia
     * que "paracetamol" esté DENTRO de "PARACETAMOL 500MG X 100 TAB" aunque el
     * nombre del catálogo sea más largo.
     *
     * @param list<string> $textos
     */
    private function candidatos(int $empresaId, array $textos): array
    {
        $porProducto = [];
        foreach (array_unique($textos) as $t) {
            $filas = DB::select(
                "SELECT p.id,
                        GREATEST(
                            similarity(public.unaccent_immutable(lower(p.nombre)), public.unaccent_immutable(lower(?))),
                            word_similarity(public.unaccent_immutable(lower(?)), public.unaccent_immutable(lower(p.nombre)))
                        ) AS puntaje
                   FROM productos p
                  WHERE p.empresa_id = ? AND p.activo = true
               ORDER BY puntaje DESC
                  LIMIT 3",
                [$t, $t, $empresaId],
            );
            foreach ($filas as $f) {
                $porProducto[$f->id] = max($porProducto[$f->id] ?? 0, (float) $f->puntaje);
            }
        }

        // Fuera lo que apenas se parece: "Llave para amoladora" no es una sugerencia
        // para "paracetamol" aunque compartan letras.
        $porProducto = array_filter($porProducto, fn ($p) => $p >= 0.3);
        arsort($porProducto);

        return $this->fichas($empresaId, array_slice($porProducto, 0, 3, true));
    }

    /**
     * Nombre, precio y costo (de la unidad base: el cuaderno cuenta tabletas,
     * sobres, frascos) para que la revisión compare con el total anotado.
     *
     * @param array<int, float> $puntajes producto_id => puntaje
     */
    private function fichas(int $empresaId, array $puntajes): array
    {
        if (!$puntajes) return [];

        $filas = DB::table('productos as p')
            ->leftJoin('producto_unidades as u', fn ($j) => $j->on('u.producto_id', '=', 'p.id')->where('u.es_base', true))
            ->where('p.empresa_id', $empresaId)
            ->whereIn('p.id', array_keys($puntajes))
            ->get(['p.id', 'p.nombre', 'u.precio_venta', 'u.precio_costo', 'p.precio_costo as costo_producto',
                DB::raw('(SELECT MAX(s.costo_promedio) FROM stock s WHERE s.producto_id = p.id) AS costo_stock')])
            ->keyBy('id');

        $fichas = [];
        foreach ($puntajes as $id => $puntaje) {
            $f = $filas[$id] ?? null;
            if (!$f) continue;
            $fichas[] = [
                'producto_id' => (int) $id,
                'nombre'      => $f->nombre,
                'precio'      => round((float) $f->precio_venta, 2),
                // Mismo piso que el POS: costo de la unidad, si no el promedio del
                // stock, si no el del producto.
                'costo'       => round(match (true) {
                    (float) $f->precio_costo > 0 => (float) $f->precio_costo,
                    (float) $f->costo_stock > 0  => (float) $f->costo_stock,
                    default                      => (float) $f->costo_producto,
                }, 2),
                'puntaje'     => round($puntaje, 2),
            ];
        }

        return $fichas;
    }

    /** "10 Paracetól" → "paracetol": sin cantidad, tildes ni signos. */
    public static function clave(string $texto): string
    {
        $t = Str::lower(Str::ascii($texto));
        $t = preg_replace('/^[\d\s.,x*]+/', '', $t);
        $t = preg_replace('/[^a-z0-9 ]+/', ' ', $t);

        return mb_substr(trim(preg_replace('/\s+/', ' ', $t)), 0, 200);
    }

    /** @param list<array{texto: string, cantidad: ?float}> $items */
    public static function huellaTexto(?string $fecha, ?float $total, array $items): string
    {
        $renglones = array_map(fn ($i) => self::clave((string) $i['texto']) . ':' . (float) ($i['cantidad'] ?? 0), $items);
        sort($renglones);

        return hash('sha256', ($fecha ?? '') . '|' . number_format((float) $total, 2, '.', '') . '|' . implode(',', $renglones));
    }

    /** @param list<array{producto_id: int, cantidad: ?float}> $items */
    public static function huellaProductos(?string $fecha, ?float $total, array $items): string
    {
        $porProducto = [];
        foreach ($items as $i) {
            $porProducto[(int) $i['producto_id']] = ($porProducto[(int) $i['producto_id']] ?? 0) + (float) ($i['cantidad'] ?? 0);
        }
        ksort($porProducto);
        $renglones = array_map(fn ($id, $c) => "{$id}:{$c}", array_keys($porProducto), $porProducto);

        return hash('sha256', ($fecha ?? '') . '|' . number_format((float) $total, 2, '.', '') . '|' . implode(',', $renglones));
    }
}
