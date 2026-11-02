<?php

namespace App\Services;

use App\Models\Conductor;
use App\Models\Empresa;
use App\Models\FlotaLiquidacion;
use App\Models\FlotaTarifa;
use App\Models\Gasto;
use App\Models\GastoConcepto;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Pago a choferes y ayudantes por sus viajes (destajo).
 *
 *  - Reparto y flete: por cada N unidades ENTREGADAS de un producto, según su
 *    tarifa (o la general). Al chofer y a CADA ayudante que fue en el viaje.
 *  - Abastecimiento (vuelta de fábrica): un monto fijo por vuelta.
 *
 * ─── EL PAGO ES UN GASTO ───────────────────────────────────────────────────────
 *
 * Pagar una liquidación registra gastos con GastoService (el mismo camino de la
 * pantalla de Gastos): uno por placa en la que trabajó la persona, así la
 * utilidad por camión descuenta lo que se le pagó. Anularla los elimina igual
 * que eliminar un gasto (revierte tesorería, recuadra el turno).
 *
 * Un viaje ya liquidado a una persona no vuelve a contar para ella.
 */
class PagoChoferesService
{
    /**
     * Una vuelta de fábrica se paga si el viaje salió. En reparto y flete se
     * paga lo ENTREGADO, aunque el viaje siga "programado": la venta rápida del
     * POS con placa crea el viaje y nadie marca la salida.
     */
    private const ESTADOS_VUELTA = ['en_ruta', 'cerrado'];

    public function __construct(private GastoService $gastos) {}

    /** @return array{vuelta_chofer: float, vuelta_ayudante: float} */
    public static function config(?Empresa $empresa): array
    {
        $c = is_array($empresa?->flota_config) ? $empresa->flota_config : [];

        return [
            'vuelta_chofer'   => round((float) ($c['vuelta_chofer'] ?? 0), 2),
            'vuelta_ayudante' => round((float) ($c['vuelta_ayudante'] ?? 0), 2),
        ];
    }

    /**
     * Lo que corresponde pagar en el período, por persona, de los viajes que
     * todavía no se le liquidaron.
     *
     * @return array<int, array{conductor_id:int, nombre:string, tipo:string, viajes:int, vueltas:int, unidades:float, monto:float, detalle:array}>
     */
    public function calcular(int $empresaId, string $desde, string $hasta, ?int $soloConductor = null): array
    {
        $empresa = Empresa::find($empresaId);
        $cfg = self::config($empresa);
        $tarifas = FlotaTarifa::where('empresa_id', $empresaId)->get();
        $general = $tarifas->firstWhere('producto_id', null);
        $porProducto = $tarifas->whereNotNull('producto_id')->keyBy('producto_id');

        $viajes = DB::table('viajes as v')
            ->leftJoin('vehiculos as ve', 've.id', '=', 'v.vehiculo_id')
            ->where('v.empresa_id', $empresaId)
            ->where('v.estado', '<>', 'anulado')
            ->where(fn ($q) => $q->where('v.tipo', '<>', 'abastecimiento')->orWhereIn('v.estado', self::ESTADOS_VUELTA))
            ->whereBetween('v.fecha', [$desde, $hasta])
            ->orderBy('v.fecha')->orderBy('v.id')
            ->get(['v.id', 'v.numero', 'v.fecha', 'v.tipo', 'v.conductor_id', 'v.vehiculo_id', 've.placa'])
            ->keyBy('id');
        if ($viajes->isEmpty()) return [];
        $ids = $viajes->keys()->all();

        $ayudantes = DB::table('viaje_ayudantes')->whereIn('viaje_id', $ids)->get()->groupBy('viaje_id');

        // Unidades base ENTREGADAS por viaje y producto (pedido: lo que de verdad
        // recibió el cliente; directa: lo que llevó).
        $entregado = DB::table('viaje_parada_items as pi')
            ->join('viaje_paradas as vp', 'vp.id', '=', 'pi.viaje_parada_id')
            ->join('ventas as ve', 've.id', '=', 'vp.venta_id')
            ->leftJoin('cliente_anticipo_aplicacion_items as ai', fn ($j) => $j
                ->on('ai.cliente_anticipo_aplicacion_id', '=', 'vp.cliente_anticipo_aplicacion_id')
                ->on('ai.cliente_anticipo_item_id', '=', 'pi.cliente_anticipo_item_id'))
            ->whereIn('vp.viaje_id', $ids)
            ->where('vp.estado', 'entregado')
            ->where('ve.estado', 'completada')
            ->groupBy('vp.viaje_id', 'pi.producto_id', 'pi.producto_nombre')
            ->selectRaw("vp.viaje_id, pi.producto_id, pi.producto_nombre,
                SUM(CASE WHEN vp.modo = 'pendiente' THEN COALESCE(ai.cantidad, 0) ELSE pi.cantidad END * pi.factor_conversion) as unidades")
            ->get()
            ->groupBy('viaje_id');

        // Lo ya liquidado (viaje + persona) no se vuelve a pagar.
        $pagado = DB::table('flota_liquidacion_viajes as lv')
            ->join('flota_liquidaciones as l', 'l.id', '=', 'lv.flota_liquidacion_id')
            ->where('l.estado', 'pagada')->whereIn('lv.viaje_id', $ids)
            ->get(['lv.viaje_id', 'l.conductor_id'])
            ->map(fn ($r) => $r->viaje_id . '|' . $r->conductor_id)->flip();

        $personas = [];
        foreach ($viajes as $v) {
            $roles = [];
            if ($v->conductor_id) $roles[(int) $v->conductor_id] = 'chofer';
            foreach ($ayudantes[$v->id] ?? [] as $a) {
                $roles[(int) $a->conductor_id] ??= 'ayudante';
            }

            foreach ($roles as $conductorId => $rol) {
                if ($soloConductor && $conductorId !== $soloConductor) continue;
                if (isset($pagado[$v->id . '|' . $conductorId])) continue;

                $unidades = 0.0;
                $monto = 0.0;
                $productos = [];
                if ($v->tipo === 'abastecimiento') {
                    $monto = $rol === 'chofer' ? $cfg['vuelta_chofer'] : $cfg['vuelta_ayudante'];
                } else {
                    foreach ($entregado[$v->id] ?? [] as $e) {
                        $t = $porProducto[$e->producto_id] ?? $general;
                        $u = (float) $e->unidades;
                        $m = $t && (float) $t->por_cantidad > 0
                            ? $u / (float) $t->por_cantidad * (float) ($rol === 'chofer' ? $t->monto_chofer : $t->monto_ayudante)
                            : 0.0;
                        $unidades += $u;
                        $monto += $m;
                        $productos[] = ['producto' => $e->producto_nombre, 'unidades' => round($u, 2), 'monto' => round($m, 2), 'sin_tarifa' => !$t];
                    }
                }

                if ($v->tipo !== 'abastecimiento' && $unidades <= 0) continue;

                $personas[$conductorId] ??= ['viajes' => 0, 'vueltas' => 0, 'unidades' => 0.0, 'monto' => 0.0, 'detalle' => []];
                $p = &$personas[$conductorId];
                $p['viajes']++;
                if ($v->tipo === 'abastecimiento') $p['vueltas']++;
                $p['unidades'] += $unidades;
                $p['monto'] += $monto;
                $p['detalle'][] = [
                    'viaje_id'    => (int) $v->id,
                    'numero'      => $v->numero,
                    'fecha'       => substr((string) $v->fecha, 0, 10),
                    'tipo'        => $v->tipo,
                    'rol'         => $rol,
                    'vehiculo_id' => $v->vehiculo_id ? (int) $v->vehiculo_id : null,
                    'placa'       => $v->placa,
                    'unidades'    => round($unidades, 2),
                    'monto'       => round($monto, 2),
                    'productos'   => $productos,
                ];
                unset($p);
            }
        }

        if (empty($personas)) return [];
        $nombres = Conductor::whereIn('id', array_keys($personas))->get()->keyBy('id');

        return collect($personas)->map(fn ($p, $id) => [
            'conductor_id' => (int) $id,
            'nombre'       => $nombres[$id]?->nombre_completo ?? '—',
            'tipo'         => $nombres[$id]?->tipo ?? 'chofer',
            'viajes'       => $p['viajes'],
            'vueltas'      => $p['vueltas'],
            'unidades'     => round($p['unidades'], 2),
            'monto'        => round($p['monto'], 2),
            'detalle'      => $p['detalle'],
        ])->sortByDesc('monto')->values()->all();
    }

    /**
     * Paga lo pendiente de una persona en el período.
     *
     * $pago: ajuste (+ bono / − adelanto o descuento), ajuste_motivo, fecha,
     *        gasto_tipo_id, gasto_concepto_id, metodo_pago_id,
     *        cuenta_metodo_pago_id, turno_id, local_id
     */
    public function pagar(User $user, int $conductorId, string $desde, string $hasta, array $pago): FlotaLiquidacion
    {
        return DB::transaction(function () use ($user, $conductorId, $desde, $hasta, $pago) {
            $empresaId = (int) $user->empresa_id;
            $conductor = Conductor::deEmpresa($empresaId)->lockForUpdate()->find($conductorId)
                ?? throw ValidationException::withMessages(['conductor_id' => 'Esa persona no es de tu empresa.']);

            $concepto = GastoConcepto::where('empresa_id', $empresaId)->find($pago['gasto_concepto_id'] ?? 0);
            if (!$concepto || (int) $concepto->gasto_tipo_id !== (int) ($pago['gasto_tipo_id'] ?? 0)) {
                throw ValidationException::withMessages(['gasto_concepto_id' => 'Elige el tipo y el concepto de gasto con que se registra el pago.']);
            }
            if ($concepto->flota_uso) {
                throw ValidationException::withMessages(['gasto_concepto_id' => 'Ese concepto es de ' . $concepto->flota_uso . ': elige uno de pago al personal.']);
            }

            $calc = collect($this->calcular($empresaId, $desde, $hasta, $conductorId))->first();
            if (!$calc || empty($calc['detalle'])) {
                throw ValidationException::withMessages(['conductor_id' => "{$conductor->nombre_completo} no tiene viajes por pagar en esas fechas."]);
            }

            $ajuste = round((float) ($pago['ajuste'] ?? 0), 2);
            $total  = round($calc['monto'] + $ajuste, 2);
            if ($total < 0.01) {
                throw ValidationException::withMessages(['ajuste' => 'El total a pagar quedaría en cero o negativo: revisa el ajuste.']);
            }
            if (abs($ajuste) >= 0.01 && trim((string) ($pago['ajuste_motivo'] ?? '')) === '') {
                throw ValidationException::withMessages(['ajuste_motivo' => 'Indica el motivo del ajuste (adelanto, descuento, bono…).']);
            }

            $liq = FlotaLiquidacion::create([
                'empresa_id'      => $empresaId,
                'numero'          => FlotaLiquidacion::generarNumero($empresaId),
                'conductor_id'    => $conductor->id,
                'desde'           => $desde,
                'hasta'           => $hasta,
                'viajes'          => $calc['viajes'],
                'vueltas'         => $calc['vueltas'],
                'unidades'        => $calc['unidades'],
                'monto_calculado' => $calc['monto'],
                'ajuste'          => $ajuste,
                'ajuste_motivo'   => mb_substr(trim((string) ($pago['ajuste_motivo'] ?? '')), 0, 255) ?: null,
                'total'           => $total,
                'detalle'         => $calc['detalle'],
                'estado'          => 'pagada',
                'fecha_pago'      => $pago['fecha'],
                'user_id'         => $user->id,
            ]);
            foreach ($calc['detalle'] as $d) {
                $liq->viajes()->create([
                    'viaje_id' => $d['viaje_id'], 'vehiculo_id' => $d['vehiculo_id'], 'rol' => $d['rol'],
                    'unidades' => $d['unidades'], 'monto' => $d['monto'],
                ]);
            }

            // Un gasto por placa, en proporción a lo ganado en cada una (el
            // ajuste se reparte igual). Sin monto calculado, todo a la primera.
            $porPlaca = collect($calc['detalle'])->groupBy(fn ($d) => $d['vehiculo_id'] ?? 0)
                ->map(fn ($g) => round($g->sum('monto'), 2));
            $base = $porPlaca->sum();
            $partes = $this->repartir($porPlaca, $base, $total);

            foreach ($partes as $vehiculoId => $monto) {
                if ($monto < 0.01) continue;
                $gasto = $this->gastos->registrar($user, [
                    'gasto_tipo_id'         => $concepto->gasto_tipo_id,
                    'gasto_concepto_id'     => $concepto->id,
                    'monto'                 => $monto,
                    'fecha'                 => $pago['fecha'],
                    'comentario'            => "{$liq->numero} · {$conductor->nombre_completo} · " . self::rango($desde, $hasta),
                    'turno_id'              => $pago['turno_id'] ?? null,
                    'local_id'              => $pago['local_id'] ?? null,
                    'metodo_pago_id'        => $pago['metodo_pago_id'] ?? null,
                    'cuenta_metodo_pago_id' => $pago['cuenta_metodo_pago_id'] ?? null,
                    'vehiculo_id'           => $vehiculoId ?: null,
                ]);
                $gasto->forceFill(['flota_liquidacion_id' => $liq->id])->save();
            }

            AuditoriaService::log('flota.liquidacion_pagada', $liq, [
                'numero' => $liq->numero, 'persona' => $conductor->nombre_completo,
                'calculado' => $calc['monto'], 'ajuste' => $ajuste, 'total' => $total,
            ], $user);

            return $liq;
        });
    }

    /** Anula la liquidación: elimina sus gastos (revierte el dinero) y libera los viajes. */
    public function anular(FlotaLiquidacion $liq, string $motivo, User $user): void
    {
        DB::transaction(function () use ($liq, $motivo, $user) {
            $liq = FlotaLiquidacion::whereKey($liq->id)->lockForUpdate()->firstOrFail();
            if ($liq->estado !== 'pagada') {
                throw ValidationException::withMessages(['liquidacion' => "La liquidación {$liq->numero} ya está anulada."]);
            }
            foreach (Gasto::where('flota_liquidacion_id', $liq->id)->get() as $g) {
                $this->gastos->eliminar($g, $user);
            }
            $liq->update(['estado' => 'anulada', 'motivo_anulacion' => mb_substr(trim($motivo), 0, 255)]);
            AuditoriaService::log('flota.liquidacion_anulada', $liq, ['numero' => $liq->numero, 'motivo' => $motivo], $user);
        });
    }

    /**
     * Reparte $total en proporción a $partes (centavos exactos: el último
     * absorbe el redondeo). @return Collection<int, float>
     */
    private function repartir(Collection $partes, float $base, float $total): Collection
    {
        if ($partes->isEmpty()) return collect([0 => $total]);
        if ($base < 0.01) {
            return $partes->keys()->mapWithKeys(fn ($k, $i) => [$k => $i === 0 ? $total : 0.0]);
        }

        $out = collect();
        $acum = 0.0;
        $claves = $partes->keys()->values();
        foreach ($claves as $i => $k) {
            $m = $i === $claves->count() - 1 ? round($total - $acum, 2) : round($total * $partes[$k] / $base, 2);
            $out[$k] = $m;
            $acum += $m;
        }

        return $out;
    }

    public static function rango(string $desde, string $hasta): string
    {
        $f = fn ($d) => \Illuminate\Support\Carbon::parse($d)->format('d/m');

        return $desde === $hasta ? $f($desde) : $f($desde) . ' al ' . $f($hasta);
    }
}
