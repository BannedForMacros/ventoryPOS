<?php

namespace App\Services;

use App\Models\Conductor;
use App\Models\Gasto;
use App\Models\GastoConcepto;
use App\Models\Vehiculo;
use App\Models\VehiculoCombustible;
use App\Models\VehiculoDocumento;
use App\Models\VehiculoMantenimiento;
use App\Support\Flota;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Flota, Fase B: combustible, mantenimiento, alertas y utilidad por placa.
 *
 * ─── EL DINERO ES EL GASTO ─────────────────────────────────────────────────────
 *
 * Una carga de combustible o un mantenimiento SON gastos de siempre: el gasto
 * mueve caja y tesorería, sale en el balance y se elimina o reactiva como
 * cualquiera. Aquí solo se guarda su ficha técnica (km, galones, taller…), que
 * cuelga del gasto. Con el gasto eliminado la ficha deja de contar sola.
 *
 * ─── EL KM NO SE GUARDA APARTE ─────────────────────────────────────────────────
 *
 * El km de un camión es el mayor entre el que se escribió en su ficha y el de
 * sus últimas cargas y mantenimientos. Así el contador del preventivo avanza
 * con cada carga de combustible sin digitar el km dos veces, y no hay un
 * segundo número que se desincronice.
 */
class FlotaService
{
    /** Ámbar cuando falta esto o menos para el preventivo. */
    public const KM_AVISO_MANTENIMIENTO = 500;

    /** Una carga que rinde menos que esto del promedio de la placa se marca. */
    private const FACTOR_CONSUMO_ANORMAL = 0.8;

    // ── Ficha técnica del gasto ──────────────────────────────────────────────

    /**
     * Crea, actualiza o quita la ficha de combustible / mantenimiento del gasto
     * según su concepto. Llamar dentro de la transacción del gasto: un error
     * aquí revierte también el gasto.
     *
     * $data: vehiculo_id, combustible{km, galones, tanque_lleno, conductor_id, grifo},
     *        mantenimiento{tipo, km, taller, detalle, dias_parado}
     */
    public function sincronizarGasto(Gasto $gasto, array $data): void
    {
        $gasto->loadMissing('empresa');
        if (!Flota::activa($gasto->empresa)) {
            return;
        }

        $vehiculoId = !empty($data['vehiculo_id']) ? (int) $data['vehiculo_id'] : null;
        $uso = GastoConcepto::whereKey($gasto->gasto_concepto_id)->value('flota_uso');

        if ($uso && !$vehiculoId) {
            throw ValidationException::withMessages(['vehiculo_id' => $uso === 'combustible'
                ? 'Elige a qué vehículo se le cargó el combustible.'
                : 'Elige a qué vehículo se le hizo el mantenimiento.']);
        }

        if ((int) $gasto->vehiculo_id !== (int) $vehiculoId) {
            $gasto->forceFill(['vehiculo_id' => $vehiculoId])->save();
        }

        if ($uso === 'combustible') {
            $this->guardarCombustible($gasto, $vehiculoId, (array) ($data['combustible'] ?? []));
        } else {
            VehiculoCombustible::where('gasto_id', $gasto->id)->delete();
        }

        if ($uso === 'mantenimiento') {
            $this->guardarMantenimiento($gasto, $vehiculoId, (array) ($data['mantenimiento'] ?? []));
        } else {
            VehiculoMantenimiento::where('gasto_id', $gasto->id)->delete();
        }
    }

    private function guardarCombustible(Gasto $gasto, int $vehiculoId, array $c): void
    {
        $km      = isset($c['km']) && $c['km'] !== '' ? round((float) $c['km'], 1) : null;
        $galones = isset($c['galones']) && $c['galones'] !== '' ? round((float) $c['galones'], 3) : null;

        $errores = [];
        if ($km === null || $km < 0) $errores['combustible.km'] = 'Escribe el kilometraje del odómetro.';
        if ($galones === null || $galones <= 0) $errores['combustible.galones'] = 'Escribe los galones cargados.';
        if ($errores) throw ValidationException::withMessages($errores);

        // El km no puede retroceder: evita errores de digitación (lo pide JH).
        $fecha = $gasto->fecha->toDateString();
        // Tope: el mayor km registrado hasta ese día (el mismo día incluido).
        $previo = $this->combustiblesActivos($vehiculoId)
            ->where('vehiculo_combustibles.gasto_id', '<>', $gasto->id)
            ->where('gastos.fecha', '<=', $fecha)
            ->orderByDesc('vehiculo_combustibles.km')
            ->first(['vehiculo_combustibles.km', 'gastos.fecha']);
        if ($previo && $km < (float) $previo->km) {
            throw ValidationException::withMessages(['combustible.km' =>
                'El km no puede ser menor al de la carga anterior de este vehículo (' . number_format((float) $previo->km, 0, '.', ',')
                . ' km el ' . Carbon::parse($previo->fecha)->format('d/m') . '). Revisa el odómetro.']);
        }
        $siguiente = $this->combustiblesActivos($vehiculoId)
            ->where('vehiculo_combustibles.gasto_id', '<>', $gasto->id)
            ->where('gastos.fecha', '>', $fecha)
            ->orderBy('gastos.fecha')->orderBy('vehiculo_combustibles.km')
            ->first(['vehiculo_combustibles.km', 'gastos.fecha']);
        if ($siguiente && $km > (float) $siguiente->km) {
            throw ValidationException::withMessages(['combustible.km' =>
                'El km no puede ser mayor al de una carga posterior (' . number_format((float) $siguiente->km, 0, '.', ',')
                . ' km el ' . Carbon::parse($siguiente->fecha)->format('d/m') . ').']);
        }

        $conductorId = !empty($c['conductor_id'])
            ? Conductor::deEmpresa($gasto->empresa_id)->whereKey((int) $c['conductor_id'])->value('id')
            : null;

        VehiculoCombustible::updateOrCreate(['gasto_id' => $gasto->id], [
            'empresa_id'   => $gasto->empresa_id,
            'vehiculo_id'  => $vehiculoId,
            'conductor_id' => $conductorId,
            'km'           => $km,
            'galones'      => $galones,
            'tanque_lleno' => array_key_exists('tanque_lleno', $c) ? (bool) $c['tanque_lleno'] : true,
            'grifo'        => self::texto($c['grifo'] ?? null, 80),
        ]);
    }

    private function guardarMantenimiento(Gasto $gasto, int $vehiculoId, array $m): void
    {
        $tipo = in_array($m['tipo'] ?? null, VehiculoMantenimiento::TIPOS, true) ? $m['tipo'] : 'preventivo';
        $km   = isset($m['km']) && $m['km'] !== '' && $m['km'] !== null ? round((float) $m['km'], 1) : null;

        // El preventivo programa el siguiente desde su km: sin km no hay contador.
        if ($tipo === 'preventivo' && $km === null) {
            throw ValidationException::withMessages(['mantenimiento.km' => 'Escribe el km en que se hizo el preventivo: desde ahí se cuenta el siguiente.']);
        }

        VehiculoMantenimiento::updateOrCreate(['gasto_id' => $gasto->id], [
            'empresa_id'  => $gasto->empresa_id,
            'vehiculo_id' => $vehiculoId,
            'tipo'        => $tipo,
            'km'          => $km,
            'taller'      => self::texto($m['taller'] ?? null, 100),
            'detalle'     => self::texto($m['detalle'] ?? null, 500),
            'dias_parado' => isset($m['dias_parado']) && $m['dias_parado'] !== '' && $m['dias_parado'] !== null ? max(0, (int) $m['dias_parado']) : null,
        ]);
    }

    // ── Lecturas por vehículo ────────────────────────────────────────────────

    /** Cargas con su gasto activo, unidas a gastos para leer fecha y monto. */
    private function combustiblesActivos(int $vehiculoId)
    {
        return DB::table('vehiculo_combustibles')
            ->join('gastos', 'gastos.id', '=', 'vehiculo_combustibles.gasto_id')
            ->whereNull('gastos.deleted_at')
            ->where('vehiculo_combustibles.vehiculo_id', $vehiculoId);
    }

    /** @param int[] $ids @return Collection<int, float> vehiculo_id => km */
    public function kmActual(array $ids): Collection
    {
        if (empty($ids)) return collect();

        $manual = DB::table('vehiculos')->whereIn('id', $ids)->pluck('km_actual', 'id');
        $comb = DB::table('vehiculo_combustibles as c')->join('gastos as g', 'g.id', '=', 'c.gasto_id')->whereNull('g.deleted_at')
            ->whereIn('c.vehiculo_id', $ids)->groupBy('c.vehiculo_id')->selectRaw('c.vehiculo_id, MAX(c.km) km')->pluck('km', 'vehiculo_id');
        $mant = DB::table('vehiculo_mantenimientos as m')->join('gastos as g', 'g.id', '=', 'm.gasto_id')->whereNull('g.deleted_at')
            ->whereIn('m.vehiculo_id', $ids)->groupBy('m.vehiculo_id')->selectRaw('m.vehiculo_id, MAX(m.km) km')->pluck('km', 'vehiculo_id');

        return collect($ids)->mapWithKeys(fn ($id) => [$id => max((float) ($manual[$id] ?? 0), (float) ($comb[$id] ?? 0), (float) ($mant[$id] ?? 0))]);
    }

    /**
     * Contador del preventivo: último preventivo (o el km base de la ficha) +
     * intervalo, contra el km actual.
     *
     * @return array{intervalo:int, ultimo_km:?float, proximo_km:?float, faltan:?float, estado:string}
     */
    public function mantenimiento(Vehiculo $v, ?float $kmActual = null): array
    {
        $kmActual ??= (float) ($this->kmActual([$v->id])[$v->id] ?? 0);
        $ultimo = DB::table('vehiculo_mantenimientos as m')->join('gastos as g', 'g.id', '=', 'm.gasto_id')->whereNull('g.deleted_at')
            ->where('m.vehiculo_id', $v->id)->where('m.tipo', 'preventivo')->whereNotNull('m.km')->max('m.km');
        $base = $ultimo !== null ? (float) $ultimo : ($v->mant_base_km !== null ? (float) $v->mant_base_km : null);
        $intervalo = max(1, (int) ($v->mant_intervalo_km ?: 8000));

        if ($base === null) {
            return ['intervalo' => $intervalo, 'ultimo_km' => null, 'proximo_km' => null, 'faltan' => null, 'estado' => 'sin_datos'];
        }

        $proximo = $base + $intervalo;
        $faltan  = round($proximo - $kmActual, 1);

        return [
            'intervalo'  => $intervalo,
            'ultimo_km'  => $base,
            'proximo_km' => $proximo,
            'faltan'     => $faltan,
            'estado'     => $faltan < 0 ? 'vencido' : ($faltan <= self::KM_AVISO_MANTENIMIENTO ? 'pronto' : 'ok'),
        ];
    }

    /**
     * Historial de cargas con km recorridos, rendimiento (km/gal) y costo por km.
     * El rendimiento exacto sale entre dos tanques llenos: se suman los galones
     * de todas las cargas desde el lleno anterior.
     *
     * @return array{cargas: array<int, array>, promedio_km_gal: ?float, costo_km: ?float}
     */
    public function rendimiento(int $vehiculoId, ?string $desde = null, ?string $hasta = null): array
    {
        $filas = $this->combustiblesActivos($vehiculoId)
            ->leftJoin('conductores as co', 'co.id', '=', 'vehiculo_combustibles.conductor_id')
            ->orderBy('vehiculo_combustibles.km')->orderBy('gastos.fecha')
            ->get([
                'vehiculo_combustibles.id', 'vehiculo_combustibles.gasto_id', 'vehiculo_combustibles.km', 'vehiculo_combustibles.galones',
                'vehiculo_combustibles.tanque_lleno', 'vehiculo_combustibles.grifo', 'gastos.fecha', 'gastos.monto',
                DB::raw("TRIM(CONCAT(co.nombres, ' ', COALESCE(co.apellidos, ''))) as conductor"),
            ]);

        $cargas = [];
        $prevKm = null;
        $ultimoLlenoKm = null;
        $galDesdeLleno = 0.0;
        $montoDesdeLleno = 0.0;
        foreach ($filas as $f) {
            $km = (float) $f->km;
            $gal = (float) $f->galones;
            $galDesdeLleno += $gal;
            $montoDesdeLleno += (float) $f->monto;

            $rend = null;
            $costoKm = null;
            if ($f->tanque_lleno) {
                if ($ultimoLlenoKm !== null && $km > $ultimoLlenoKm && $galDesdeLleno > 0) {
                    $tramo   = $km - $ultimoLlenoKm;
                    $rend    = round($tramo / $galDesdeLleno, 2);
                    $costoKm = round($montoDesdeLleno / $tramo, 3);
                }
                $ultimoLlenoKm = $km;
                $galDesdeLleno = 0.0;
                $montoDesdeLleno = 0.0;
            }

            $cargas[] = [
                'id'             => (int) $f->id,
                'gasto_id'       => (int) $f->gasto_id,
                'fecha'          => substr((string) $f->fecha, 0, 10),
                'km'             => $km,
                'km_recorridos'  => $prevKm !== null ? round($km - $prevKm, 1) : null,
                'galones'        => $gal,
                'monto'          => (float) $f->monto,
                'tanque_lleno'   => (bool) $f->tanque_lleno,
                'grifo'          => $f->grifo,
                'conductor'      => $f->conductor ?: null,
                'rendimiento'    => $rend,
                'costo_km'       => $costoKm,
            ];
            $prevKm = $km;
        }

        $conRend = array_values(array_filter($cargas, fn ($c) => $c['rendimiento'] !== null));
        $promedio = $conRend ? round(array_sum(array_column($conRend, 'rendimiento')) / count($conRend), 2) : null;
        foreach ($cargas as &$c) {
            $c['anormal'] = $promedio !== null && $c['rendimiento'] !== null && $c['rendimiento'] < $promedio * self::FACTOR_CONSUMO_ANORMAL;
        }
        unset($c);

        $enRango = array_values(array_filter($cargas, fn ($c) => (!$desde || $c['fecha'] >= $desde) && (!$hasta || $c['fecha'] <= $hasta)));
        $costos  = array_values(array_filter($enRango, fn ($c) => $c['costo_km'] !== null));

        return [
            'cargas'          => array_reverse($enRango),
            'promedio_km_gal' => $promedio,
            'costo_km'        => $costos ? round(array_sum(array_column($costos, 'costo_km')) / count($costos), 3) : null,
        ];
    }

    // ── Alertas ──────────────────────────────────────────────────────────────

    /**
     * Lo que hay que atender: documentos y licencias vencidos o por vencer,
     * mantenimientos próximos o pasados. Más urgente primero.
     *
     * @return array<int, array{tipo:string, nivel:string, titulo:string, detalle:string, vehiculo_id:?int}>
     */
    public function alertas(int $empresaId): array
    {
        $hoy = Carbon::today();
        $alertas = [];

        $vehiculos = Vehiculo::deEmpresa($empresaId)->activo()->get();
        $km = $this->kmActual($vehiculos->pluck('id')->all());

        foreach ($vehiculos as $v) {
            $m = $this->mantenimiento($v, (float) ($km[$v->id] ?? 0));
            if (in_array($m['estado'], ['vencido', 'pronto'], true)) {
                $alertas[] = [
                    'tipo'        => 'mantenimiento',
                    'nivel'       => $m['estado'] === 'vencido' ? 'rojo' : 'ambar',
                    'titulo'      => "{$v->placa}: mantenimiento preventivo",
                    'detalle'     => $m['estado'] === 'vencido'
                        ? 'Pasó ' . number_format(abs($m['faltan']), 0, '.', ',') . ' km del servicio'
                        : 'Faltan ' . number_format($m['faltan'], 0, '.', ',') . ' km',
                    'vehiculo_id' => $v->id,
                    'orden'       => $m['faltan'],
                ];
            }
        }

        // Solo el documento vigente de cada tipo: al renovar el SOAT, el viejo
        // vencido deja de avisar.
        $documentos = VehiculoDocumento::where('empresa_id', $empresaId)
            ->whereNotNull('vence')
            ->whereHas('vehiculo', fn ($q) => $q->where('activo', true))
            ->with('vehiculo:id,placa')
            ->orderByDesc('vence')
            ->get()
            ->unique(fn ($d) => $d->vehiculo_id . '|' . mb_strtolower($d->tipo));
        foreach ($documentos as $d) {
            $estado = $d->estado($hoy);
            if ($estado === 'vigente') continue;
            $dias = $hoy->diffInDays($d->vence, false);
            $alertas[] = [
                'tipo'        => 'documento',
                'nivel'       => $estado === 'vencido' ? 'rojo' : 'ambar',
                'titulo'      => "{$d->vehiculo->placa}: {$d->tipo}",
                'detalle'     => $estado === 'vencido' ? 'Venció el ' . $d->vence->format('d/m/Y') : 'Vence en ' . (int) $dias . ' días (' . $d->vence->format('d/m/Y') . ')',
                'vehiculo_id' => $d->vehiculo_id,
                'orden'       => $dias,
            ];
        }

        foreach (Conductor::deEmpresa($empresaId)->activo()->where('tipo', Conductor::CHOFER)->whereNotNull('licencia_vence')->get() as $c) {
            $estado = $c->estadoLicencia($hoy);
            if ($estado === 'vigente') continue;
            $dias = $hoy->diffInDays($c->licencia_vence, false);
            $alertas[] = [
                'tipo'        => 'licencia',
                'nivel'       => $estado === 'vencida' ? 'rojo' : 'ambar',
                'titulo'      => "Licencia de {$c->nombre_completo}",
                'detalle'     => $estado === 'vencida' ? 'Venció el ' . $c->licencia_vence->format('d/m/Y') : 'Vence en ' . (int) $dias . ' días',
                'vehiculo_id' => null,
                'orden'       => $dias,
            ];
        }

        usort($alertas, fn ($a, $b) => [$a['nivel'] === 'rojo' ? 0 : 1, $a['orden']] <=> [$b['nivel'] === 'rojo' ? 0 : 1, $b['orden']]);

        return array_map(fn ($a) => array_diff_key($a, ['orden' => true]), $alertas);
    }

    // ── Utilidad por placa ───────────────────────────────────────────────────

    /**
     * Por vehículo en el período: lo que despachó (venta y margen de las
     * paradas entregadas de sus viajes) menos lo que costó (sus gastos:
     * combustible, mantenimiento y otros). El margen usa el costo congelado de
     * la venta (CostoVentaService), nunca el costo de hoy.
     *
     * @return array<int, array>
     */
    public function utilidad(int $empresaId, string $desde, string $hasta): array
    {
        $vehiculos = Vehiculo::deEmpresa($empresaId)->orderBy('placa')->get();
        if ($vehiculos->isEmpty()) return [];

        $costo = CostoVentaService::sql('vi', 'p', 'v');
        // Lo cobrado por unidad base: la línea neta (con su parte del descuento
        // global), igual que el Reporte de utilidad.
        $precio = '(' . UtilidadService::lineaNeta('vi') . ' / NULLIF(vi.cantidad_base, 0))';

        // Lo despachado: cada ítem de parada entregada, al precio y costo por
        // unidad base de su línea de venta. En pedidos, lo que de verdad se
        // entregó (la entrega puede ser parcial); en directas, lo cargado.
        $lineas = DB::table('viaje_parada_items as pi')
            ->join('viaje_paradas as vp', 'vp.id', '=', 'pi.viaje_parada_id')
            ->join('viajes as vj', 'vj.id', '=', 'vp.viaje_id')
            ->join('ventas as v', 'v.id', '=', 'vp.venta_id')
            ->join('productos as p', 'p.id', '=', 'pi.producto_id')
            ->join(DB::raw('LATERAL (SELECT * FROM venta_items x WHERE x.venta_id = v.id AND x.producto_id = pi.producto_id ORDER BY x.id LIMIT 1) vi'), DB::raw('true'), '=', DB::raw('true'))
            ->leftJoin('cliente_anticipo_aplicacion_items as ai', fn ($j) => $j
                ->on('ai.cliente_anticipo_aplicacion_id', '=', 'vp.cliente_anticipo_aplicacion_id')
                ->on('ai.cliente_anticipo_item_id', '=', 'pi.cliente_anticipo_item_id'))
            ->where('vj.empresa_id', $empresaId)
            ->where('vj.estado', '<>', 'anulado')
            ->where('v.estado', 'completada')
            ->where('vp.estado', 'entregado')
            ->whereBetween('vj.fecha', [$desde, $hasta])
            ->groupBy('vj.vehiculo_id')
            ->selectRaw("vj.vehiculo_id,
                SUM(CASE WHEN vp.modo = 'pendiente' THEN COALESCE(ai.cantidad, 0) ELSE pi.cantidad END * pi.factor_conversion
                    * {$precio}) as venta,
                SUM(CASE WHEN vp.modo = 'pendiente' THEN COALESCE(ai.cantidad, 0) ELSE pi.cantidad END * pi.factor_conversion
                    * ({$precio} - COALESCE({$costo}, 0))) as margen")
            ->get()->keyBy('vehiculo_id');

        $viajes = DB::table('viajes')->where('empresa_id', $empresaId)->where('estado', '<>', 'anulado')
            ->whereBetween('fecha', [$desde, $hasta])->groupBy('vehiculo_id')
            ->selectRaw("vehiculo_id, COUNT(*) n, SUM(CASE WHEN tipo = 'abastecimiento' THEN 1 ELSE 0 END) vueltas")->get()->keyBy('vehiculo_id');

        $gastos = DB::table('gastos as g')
            ->leftJoin('gasto_conceptos as gc', 'gc.id', '=', 'g.gasto_concepto_id')
            ->where('g.empresa_id', $empresaId)->whereNull('g.deleted_at')->whereNotNull('g.vehiculo_id')
            ->whereBetween('g.fecha', [$desde, $hasta])
            ->groupBy('g.vehiculo_id')
            ->selectRaw("g.vehiculo_id,
                SUM(CASE WHEN gc.flota_uso = 'combustible' THEN g.monto ELSE 0 END) combustible,
                SUM(CASE WHEN gc.flota_uso = 'mantenimiento' THEN g.monto ELSE 0 END) mantenimiento,
                SUM(CASE WHEN g.flota_liquidacion_id IS NOT NULL THEN g.monto ELSE 0 END) personal,
                SUM(CASE WHEN gc.flota_uso IS NULL AND g.flota_liquidacion_id IS NULL THEN g.monto ELSE 0 END) otros")
            ->get()->keyBy('vehiculo_id');

        $kmRango = DB::table('vehiculo_combustibles as c')->join('gastos as g', 'g.id', '=', 'c.gasto_id')->whereNull('g.deleted_at')
            ->where('c.empresa_id', $empresaId)->whereBetween('g.fecha', [$desde, $hasta])
            ->groupBy('c.vehiculo_id')->selectRaw('c.vehiculo_id, MAX(c.km) - MIN(c.km) km, SUM(c.galones) galones')->get()->keyBy('vehiculo_id');

        return $vehiculos->map(function (Vehiculo $v) use ($lineas, $viajes, $gastos, $kmRango) {
            $l = $lineas[$v->id] ?? null;
            $g = $gastos[$v->id] ?? null;
            $k = $kmRango[$v->id] ?? null;
            $comb = round((float) ($g->combustible ?? 0), 2);
            $mant = round((float) ($g->mantenimiento ?? 0), 2);
            $otros = round((float) ($g->otros ?? 0), 2);
            // Pago a choferes y ayudantes (liquidaciones), separado de los demás gastos.
            $personal = round((float) ($g->personal ?? 0), 2);
            $margen = round((float) ($l->margen ?? 0), 2);
            $km = (float) ($k->km ?? 0);

            return [
                'vehiculo_id'   => $v->id,
                'placa'         => $v->placa,
                'descripcion'   => trim(($v->marca ?? '') . ' ' . ($v->modelo ?? '')),
                'activo'        => $v->activo,
                'viajes'        => (int) ($viajes[$v->id]->n ?? 0),
                'vueltas'       => (int) ($viajes[$v->id]->vueltas ?? 0),
                'venta'         => round((float) ($l->venta ?? 0), 2),
                'margen'        => $margen,
                'combustible'   => $comb,
                'mantenimiento' => $mant,
                'otros'         => $otros,
                'personal'      => $personal,
                'gastos'        => round($comb + $mant + $otros + $personal, 2),
                'utilidad'      => round($margen - $comb - $mant - $otros - $personal, 2),
                'km'            => $km,
                'galones'       => round((float) ($k->galones ?? 0), 3),
                'costo_km'      => $km > 0 ? round(($comb + $mant + $otros + $personal) / $km, 3) : null,
                'mant_km'       => $km > 0 ? round($mant / $km, 3) : null,
            ];
        })->filter(fn ($r) => $r['activo'] || $r['viajes'] > 0 || $r['gastos'] > 0)->sortByDesc('utilidad')->values()->all();
    }

    private static function texto($v, int $max): ?string
    {
        $v = is_string($v) ? trim($v) : null;

        return $v !== null && $v !== '' ? mb_substr($v, 0, $max) : null;
    }
}
