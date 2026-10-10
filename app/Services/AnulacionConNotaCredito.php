<?php

namespace App\Services;

use App\Models\Devolucion;
use App\Models\DevolucionMotivo;
use App\Models\Turno;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaComprobante;
use Illuminate\Support\Facades\DB;

/**
 * "Anular" una venta cuyo comprobante ya está informado a SUNAT.
 *
 * Una boleta o factura aceptada no se puede borrar: se revierte con una Nota de
 * Crédito. Para la cajera es lo mismo que anular, así que el botón Anular hace
 * todo en un paso: registra una devolución TOTAL (vuelve el stock), devuelve el
 * dinero por el mismo medio con que se pagó (sale de su caja) y la devolución
 * emite sola la Nota de Crédito. Es el mismo camino que Devoluciones: nada nuevo
 * que cuadrar en el balance.
 *
 * Lo que no es "anular simple" (pendientes por entregar, anticipos usados,
 * cobros de crédito ya hechos) no se adivina: se pide hacerlo por Devoluciones.
 */
class AnulacionConNotaCredito
{
    public function __construct(private DevolucionService $devoluciones) {}

    /** El comprobante informado a SUNAT, si la venta lo tiene (entonces anular = Nota de Crédito). */
    public static function comprobante(Venta $venta): ?VentaComprobante
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('venta_comprobantes')) {
            return null;
        }
        $ce = $venta->comprobanteElectronico()->first();

        // Solo lo que admite Nota de Crédito (aceptado, observado…). Uno ya dado
        // de baja ante SUNAT no se acredita: sigue bloqueado como antes.
        return $ce && in_array($ce->estado, VentaComprobante::estadosAcreditables(), true) ? $ce : null;
    }

    /**
     * Lo que haría, para mostrarlo ANTES de confirmar: cuánto se devuelve y por
     * qué medio, o por qué no se puede en un paso.
     *
     * @return array{comprobante: string, monto: float, cxc: float, reembolso: float,
     *               pagos: list<array{metodo_pago_id: int, cuenta_metodo_pago_id: ?int, monto: float, metodo: string}>,
     *               items: list<array{venta_item_id: int, cantidad: float}>, bloqueo: ?string}|null
     */
    public function plan(Venta $venta): ?array
    {
        $ce = self::comprobante($venta);
        if (! $ce) {
            return null;
        }
        $venta->loadMissing('items', 'pagos.metodoPago');

        $base = ['comprobante' => $ce->numero, 'monto' => 0.0, 'cxc' => 0.0, 'reembolso' => 0.0, 'pagos' => [], 'items' => [], 'bloqueo' => null];
        $porDevoluciones = ' Hazlo desde Devoluciones, eligiendo qué se devuelve y cómo.';

        if ($venta->estado !== 'completada') {
            return ['bloqueo' => 'La venta ya está anulada.'] + $base;
        }
        if (DB::table('cliente_anticipos')->where('venta_id', $venta->id)->where('estado', '<>', 'anulado')->exists()) {
            return ['bloqueo' => 'Esta venta tiene productos pendientes por entregar.' . $porDevoluciones] + $base;
        }
        if (DB::table('cliente_anticipo_aplicaciones')->where('venta_id', $venta->id)->exists()) {
            return ['bloqueo' => 'Esta venta se pagó en parte con un anticipo del cliente.' . $porDevoluciones] + $base;
        }
        if ($venta->es_credito && DB::table('venta_abonos')->where('venta_id', $venta->id)->exists()) {
            return ['bloqueo' => 'Esta venta a crédito ya tiene cobros registrados.' . $porDevoluciones] + $base;
        }

        // Todo lo que aún no se devolvió (puede haber devoluciones parciales previas).
        $devuelto = DB::table('devoluciones_detalle as dd')
            ->join('devoluciones as d', 'd.id', '=', 'dd.devolucion_id')
            ->where('d.venta_id', $venta->id)
            ->whereIn('d.estado', ['pendiente', 'aprobada', 'completada'])
            ->groupBy('dd.venta_item_id')
            ->selectRaw('dd.venta_item_id, SUM(dd.cantidad) AS total')
            ->pluck('total', 'venta_item_id');
        $items = $venta->items
            ->map(fn ($i) => ['venta_item_id' => $i->id, 'cantidad' => round((float) $i->cantidad - (float) ($devuelto[$i->id] ?? 0), 4)])
            ->filter(fn ($i) => $i['cantidad'] > 0.00001)
            ->values()->all();
        if (! $items) {
            return ['bloqueo' => 'Todo lo de esta venta ya se devolvió.'] + $base;
        }

        $monto  = $this->devoluciones->montoADevolver($venta, $items);
        $reparto = Devolucion::repartoContraCxc($venta, $monto);

        return [
            'comprobante' => $ce->numero,
            'monto'       => round($monto, 2),
            'cxc'         => $reparto['cxc'],
            'reembolso'   => $reparto['reembolso'],
            'pagos'       => $this->pagosDeReembolso($venta, $reparto['reembolso']),
            'items'       => $items,
            'bloqueo'     => null,
        ];
    }

    /**
     * Anula la venta con su Nota de Crédito (vía una devolución total).
     *
     * @throws \Illuminate\Validation\ValidationException|\RuntimeException
     */
    public function ejecutar(Venta $venta, User $user, string $motivo): Devolucion
    {
        $plan = $this->plan($venta);
        if (! $plan) {
            throw new \RuntimeException('Esta venta no tiene un comprobante informado a SUNAT: se anula normalmente.');
        }
        if ($plan['bloqueo']) {
            throw new \RuntimeException($plan['bloqueo']);
        }
        if ($plan['reembolso'] > 0.009 && ! $plan['pagos']) {
            throw new \RuntimeException('No se encontró cómo se pagó esta venta para devolver el dinero. Hazlo desde Devoluciones.');
        }

        // El dinero sale de la caja: la de quien anula o, si no tiene turno, la de
        // la venta si sigue abierta (así "ya no se cuenta" en esa caja). Misma
        // regla de "Afecta caja" que una devolución; aquí no hay formulario para
        // que el admin elija, así que se le propone ese turno.
        $propio = Turno::turnoActivoDelUsuario($user->id)?->id;
        $deLaVenta = $venta->turno_id
            ? Turno::whereKey($venta->turno_id)->where('estado', 'abierto')->value('id')
            : null;
        $turnoId = \App\Support\AfectaCaja::resolverTurno($user, 'devoluciones', $propio ?? $deLaVenta, 'forzado');
        $turno = $turnoId
            ? Turno::where('id', $turnoId)->where('empresa_id', $user->empresa_id)->where('estado', 'abierto')->first()
            : null;

        return $this->devoluciones->crear([
            'venta_id'        => $venta->id,
            'motivo_id'       => $this->motivo($user->empresa_id)->id,
            'forma_reembolso' => $plan['reembolso'] > 0.009 ? 'mismo_metodo' : 'sin_reembolso',
            'observacion'     => "Anulación de {$venta->numero} ({$plan['comprobante']}) con nota de crédito: {$motivo}",
            'items'           => array_map(fn ($i) => $i + ['estado_producto' => 'bueno', 'restock' => true], $plan['items']),
            'pagos'           => array_map(fn ($p) => [
                'metodo_pago_id' => $p['metodo_pago_id'], 'cuenta_metodo_pago_id' => $p['cuenta_metodo_pago_id'], 'monto' => $p['monto'],
            ], $plan['pagos']),
        ], $user, $turno);
    }

    /**
     * El reembolso repartido entre los medios con que se pagó la venta, en la
     * misma proporción (lo cobrado neto de vuelto); el redondeo va al último.
     */
    private function pagosDeReembolso(Venta $venta, float $reembolso): array
    {
        if ($reembolso <= 0.009) {
            return [];
        }
        $pagos = $venta->pagos->map(fn ($p) => [
            'metodo_pago_id'        => (int) $p->metodo_pago_id,
            'cuenta_metodo_pago_id' => $p->cuenta_metodo_pago_id ? (int) $p->cuenta_metodo_pago_id : null,
            'neto'                  => round((float) $p->monto - (float) $p->vuelto, 2),
            'metodo'                => $p->metodoPago?->nombre ?? 'Pago',
        ])->filter(fn ($p) => $p['neto'] > 0.009)->values();
        $cobrado = $pagos->sum('neto');
        if ($cobrado <= 0.009) {
            return [];
        }

        $salida = [];
        $resto  = $reembolso;
        foreach ($pagos as $k => $p) {
            $monto = $k === $pagos->count() - 1 ? round($resto, 2) : round($reembolso * $p['neto'] / $cobrado, 2);
            $resto = round($resto - $monto, 2);
            if ($monto > 0.009) {
                $salida[] = ['metodo_pago_id' => $p['metodo_pago_id'], 'cuenta_metodo_pago_id' => $p['cuenta_metodo_pago_id'], 'monto' => $monto, 'metodo' => $p['metodo']];
            }
        }

        return $salida;
    }

    /** Motivo propio "Anulación de venta" (lo crea la primera vez; vuelve al stock). */
    private function motivo(int $empresaId): DevolucionMotivo
    {
        return DevolucionMotivo::firstOrCreate(
            ['empresa_id' => $empresaId, 'slug' => 'anulacion_venta'],
            ['nombre' => 'Anulación de venta', 'afecta_restock_default' => 'permite', 'es_sistema' => true, 'activo' => true, 'orden' => 0],
        );
    }
}
