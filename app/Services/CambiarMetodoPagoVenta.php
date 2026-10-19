<?php

namespace App\Services;

use App\Models\CuentaMovimiento;
use App\Models\MetodoPago;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaPago;
use Illuminate\Support\Facades\DB;

/**
 * Corregir SOLO el medio de pago de una venta (efectivo ↔ Yape ↔ tarjeta…),
 * también cuando la venta ya tiene boleta/factura informada a SUNAT.
 *
 * Por qué se puede aunque la venta no se edite: el comprobante solo informa
 * "Contado" o "Crédito", nunca el medio. Aquí no se toca nada de lo declarado
 * (ítems, montos, cliente, contado/crédito): cada pago conserva su monto y su
 * vuelto; cambian su método, su cuenta y su referencia, y el ingreso pasa a la
 * cuenta correcta con la MISMA fecha y el mismo usuario que tenía.
 *
 * El cierre de un turno ya cerrado (esperado/diferencia) no se recalcula: es
 * el registro de lo que se contó ese día. El cambio queda en la auditoría.
 */
class CambiarMetodoPagoVenta
{
    public function __construct(private TesoreriaService $tesoreria) {}

    /** Por qué este usuario NO puede cambiar el pago de esta venta, o null si sí. */
    public function motivoBloqueo(Venta $venta, User $user): ?string
    {
        if ($venta->estado !== 'completada') {
            return 'Solo se cambia el método de pago de una venta completada.';
        }
        if (!$venta->pagos()->exists()) {
            return 'Esta venta no tiene pagos registrados.';
        }
        if (!$user->rol?->es_admin && $venta->turno?->estado !== 'abierto') {
            return 'El turno de esta venta ya se cerró: solo un administrador puede cambiar su método de pago.';
        }

        return null;
    }

    /** Lo que necesita el modal «Corregir método de pago» (lista o detalle de la venta). */
    public function datos(Venta $venta, User $user): array
    {
        $venta->loadMissing('pagos.metodoPago:id,nombre', 'turno:id,estado');

        return [
            'venta'   => ['id' => $venta->id, 'numero' => $venta->numero, 'tipo_comprobante' => $venta->tipo_comprobante],
            'bloqueo' => $this->motivoBloqueo($venta, $user),
            'pagos'   => $venta->pagos->sortBy('id')->map(fn ($p) => [
                'id' => $p->id, 'monto' => (float) $p->monto, 'vuelto' => (float) $p->vuelto,
                'metodo_pago_id' => (int) $p->metodo_pago_id,
                'cuenta_metodo_pago_id' => $p->cuenta_metodo_pago_id ? (int) $p->cuenta_metodo_pago_id : null,
                'referencia' => $p->referencia,
            ])->values(),
            'metodos' => MetodoPago::deEmpresa($venta->empresa_id)->activo()
                ->with(['tipo', 'cuentas' => fn ($q) => $q->where('cuentas.activo', true)])
                ->orderBy('nombre')->get()
                ->map(fn ($m) => [
                    'id' => $m->id, 'nombre' => $m->nombre, 'admite_vuelto' => $m->calcula_vuelto,
                    'requiere_referencia' => (bool) $m->tipo?->requiere_referencia,
                    'cuentas' => $m->cuentas->map(fn ($c) => ['pivot_id' => (int) $c->pivot->id, 'nombre' => $c->nombre])->values(),
                ])->values(),
        ];
    }

    /**
     * @param array<int, array{id:int, metodo_pago_id:int, cuenta_metodo_pago_id:?int, referencia:?string}> $pagos
     */
    public function aplicar(Venta $venta, array $pagos, User $user, ?string $motivo = null): Venta
    {
        return DB::transaction(function () use ($venta, $pagos, $user, $motivo) {
            $venta = Venta::whereKey($venta->id)->lockForUpdate()->firstOrFail();
            if ($bloqueo = $this->motivoBloqueo($venta, $user)) {
                abort(422, $bloqueo);
            }

            $actuales = $venta->pagos()->with('metodoPago:id,nombre')->orderBy('id')->get()->keyBy('id');
            $nuevos   = collect($pagos)->keyBy(fn ($p) => (int) $p['id']);
            if ($nuevos->keys()->sort()->values()->all() !== $actuales->keys()->sort()->values()->all()) {
                abort(422, 'Los pagos enviados no son los de esta venta. Recarga la página e inténtalo de nuevo.');
            }

            $metodos = MetodoPago::deEmpresa($venta->empresa_id)->activo()
                ->with(['tipo', 'cuentas' => fn ($q) => $q->where('cuentas.activo', true)])
                ->whereIn('id', $nuevos->pluck('metodo_pago_id')->map(fn ($id) => (int) $id)->unique())
                ->get()->keyBy('id');

            // Validar cada pago y armar el cambio. El monto y el vuelto NO cambian.
            $cambios = [];
            foreach ($actuales as $id => $pago) {
                $n      = $nuevos[$id];
                $metodo = $metodos->get((int) $n['metodo_pago_id'])
                    ?? abort(422, 'El medio de pago elegido no existe o está inactivo.');

                $pivotes = $metodo->cuentas->pluck('pivot.id')->map(fn ($v) => (int) $v);
                $cuenta  = isset($n['cuenta_metodo_pago_id']) && $n['cuenta_metodo_pago_id'] ? (int) $n['cuenta_metodo_pago_id'] : null;
                if ($cuenta === null && $pivotes->count() === 1) {
                    $cuenta = $pivotes->first();
                }
                if ($pivotes->count() > 1 && $cuenta === null) {
                    abort(422, "Elige a qué cuenta entró el pago de S/ " . number_format((float) $pago->monto, 2) . " con {$metodo->nombre}.");
                }
                if ($cuenta !== null && !$pivotes->contains($cuenta)) {
                    abort(422, "La cuenta elegida no corresponde a {$metodo->nombre}.");
                }
                if ((float) $pago->vuelto > 0.009 && !$metodo->calcula_vuelto) {
                    abort(422, 'Este pago dio vuelto (S/ ' . number_format((float) $pago->vuelto, 2) . "): {$metodo->nombre} no admite vuelto.");
                }

                $referencia = isset($n['referencia']) ? (trim((string) $n['referencia']) ?: null) : null;
                $cambios[$id] = [
                    'pago'   => $pago,
                    'metodo' => $metodo,
                    'nuevo'  => ['metodo_pago_id' => $metodo->id, 'cuenta_metodo_pago_id' => $cuenta, 'referencia' => $referencia],
                    'cambia' => (int) $pago->metodo_pago_id !== $metodo->id
                        || ($pago->cuenta_metodo_pago_id ? (int) $pago->cuenta_metodo_pago_id : null) !== $cuenta
                        || ($pago->referencia ?: null) !== $referencia,
                ];
            }
            if (!collect($cambios)->contains('cambia', true)) {
                abort(422, 'No cambiaste nada: elige el método de pago correcto.');
            }

            // El dinero de la venta en tesorería debe ser exactamente el de sus
            // pagos; si no (ajustes a mano, datos antiguos), no se adivina.
            $movs = CuentaMovimiento::where('ref_tipo', 'venta')->where('ref_id', $venta->id)->orderBy('id')->get();
            $enCaja = round($movs->sum(fn ($m) => $m->tipo === 'ingreso' ? (float) $m->monto : -(float) $m->monto), 2);
            $deLosPagos = round($actuales->sum(fn ($p) => max(0, (float) $p->monto - (float) $p->vuelto)), 2);
            if (abs($enCaja - $deLosPagos) > 0.009) {
                abort(422, "El dinero registrado en caja para esta venta (S/ " . number_format($enCaja, 2)
                    . ") no coincide con sus pagos (S/ " . number_format($deLosPagos, 2) . '). Revísalo en Tesorería antes de cambiar el método.');
            }
            $fecha  = $movs->first()?->fecha?->toDateString() ?? $venta->fecha_venta?->toDateString() ?? now()->toDateString();
            $autor  = $movs->first()?->user_id ?? $venta->user_id;

            $antes = $actuales->map(fn ($p) => [
                'pago_id' => $p->id, 'metodo' => $p->metodoPago?->nombre, 'metodo_pago_id' => $p->metodo_pago_id,
                'cuenta_metodo_pago_id' => $p->cuenta_metodo_pago_id, 'referencia' => $p->referencia,
                'monto' => (float) $p->monto, 'vuelto' => (float) $p->vuelto,
            ])->values()->all();

            $this->tesoreria->revertir('venta', $venta->id);
            foreach ($cambios as $c) {
                /** @var VentaPago $pago */
                $pago = $c['pago'];
                $pago->update($c['nuevo']);

                $neto = round((float) $pago->monto - (float) $pago->vuelto, 2);
                $tc   = $pago->tipo_cambio !== null ? (float) $pago->tipo_cambio : null;
                $this->tesoreria->registrar(
                    $venta->empresa_id,
                    $this->tesoreria->resolverCuenta($venta->empresa_id, $pago->cuenta_metodo_pago_id, $pago->metodo_pago_id),
                    $autor,
                    $fecha,
                    'ingreso',
                    $neto,
                    "Venta {$venta->numero} — {$c['metodo']->nombre}",
                    'venta',
                    $venta->id,
                    $pago->moneda ?? 'PEN',
                    $tc,
                    ($pago->moneda ?? 'PEN') !== 'PEN' && $tc > 0 ? round($neto / $tc, 2) : null,
                );
            }

            AuditoriaService::log('venta.metodo_pago_cambiado', $venta, [
                'motivo'  => $motivo,
                'antes'   => $antes,
                'despues' => collect($cambios)->map(fn ($c) => ['pago_id' => $c['pago']->id, 'metodo' => $c['metodo']->nombre] + $c['nuevo'])->values()->all(),
                'turno_cerrado' => $venta->turno?->estado !== 'abierto',
            ], $user);

            return $venta->fresh(['pagos.metodoPago', 'pagos.cuentaMetodoPago']);
        });
    }
}
