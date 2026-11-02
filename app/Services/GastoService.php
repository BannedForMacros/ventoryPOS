<?php

namespace App\Services;

use App\Models\Gasto;
use App\Models\Turno;
use App\Models\User;
use App\Services\AuditoriaService;
use Illuminate\Support\Facades\DB;

/**
 * Registrar y eliminar un gasto: la MISMA regla para la pantalla de Gastos y
 * para lo que crea gastos por su cuenta (p. ej. el pago de una liquidación de
 * choferes). Turno, cuenta, tesorería, ficha de flota y recuadre del turno
 * viven aquí; antes estaban dentro de GastoController.
 */
class GastoService
{
    public function __construct(
        private TesoreriaService $tesoreria,
        private FlotaService $flota,
    ) {}

    /**
     * $data: lo mismo que el formulario de gasto (gasto_tipo_id,
     * gasto_concepto_id, monto, fecha, comentario, turno_id, local_id,
     * metodo_pago_id, cuenta_metodo_pago_id, vehiculo_id, combustible…).
     * Ya validado por la forma (StoreGastoRequest o equivalente).
     */
    public function registrar(User $user, array $data): Gasto
    {
        $d = fn (string $k) => $data[$k] ?? null;

        // Solo admins pueden crear gastos administrativos (sin turno) — pero solo
        // cuando el módulo 'gastos' afecta caja. Si la empresa lo apagó, cualquiera
        // puede registrar gastos sin turno (no descuentan de ninguna caja).
        if (!$d('turno_id') && !$user->rol->es_admin
            && \App\Support\AfectaCaja::activo($user->empresa, 'gastos')) {
            abort(403, 'Solo administradores pueden registrar gastos administrativos.');
        }

        // Turno gateado por config (módulo 'gastos', modo forzado: el cajero se
        // imputa a su turno; el admin usa el elegido; apagado → null = no afecta caja).
        $localId = $user->local_id ?? $d('local_id');
        $turnoId = \App\Support\AfectaCaja::resolverTurno(
            $user, 'gastos',
            $d('turno_id') ? (int) $d('turno_id') : null,
            'forzado',
        );

        if ($turnoId) {
            $turno = Turno::find($turnoId);
            if ($turno) {
                $localId = $turno->local_id;
            }
        }

        $this->validarFechaEnTurno($turnoId, $d('fecha'));

        // Resolver la cuenta destino igual que el POS: se elige un método de pago
        // y (si tiene) su cuenta. TesoreriaService::resolverCuenta traduce el
        // método + cuenta_metodo_pago_id a una cuenta_id concreta (efectivo si el
        // método es efectivo o no tiene cuenta). Compat: si aún llega cuenta_id
        // directo (clientes viejos), se respeta.
        $metodoPagoId       = $d('metodo_pago_id');
        $cuentaMetodoPagoId = $d('cuenta_metodo_pago_id');
        $cuentaId = $metodoPagoId
            ? $this->tesoreria->resolverCuenta(
                $user->empresa_id,
                $cuentaMetodoPagoId ? (int) $cuentaMetodoPagoId : null,
                (int) $metodoPagoId,
              )
            : ($d('cuenta_id') ?: null);

        return DB::transaction(function () use ($data, $d, $user, $localId, $turnoId, $cuentaId) {
            $gasto = Gasto::create([
                'empresa_id'        => $user->empresa_id,
                'local_id'          => $localId,
                'user_id'           => $user->id,
                'turno_id'          => $turnoId,
                'gasto_tipo_id'     => $d('gasto_tipo_id'),
                'gasto_concepto_id' => $d('gasto_concepto_id'),
                'monto'             => $d('monto'),
                'cuenta_id'         => $cuentaId,
                'fecha'             => $d('fecha'),
                'comentario'        => $d('comentario'),
            ]);

            // F7 — Tesorería: el gasto sale de la cuenta resuelta (efectivo por defecto).
            $gasto->load('concepto');
            // Flota: placa y ficha de combustible / mantenimiento, en la misma
            // transacción (un km inválido revierte también el gasto).
            $this->flota->sincronizarGasto($gasto, $data);

            $this->tesoreria->registrar(
                $user->empresa_id,
                $gasto->cuenta_id, // null → efectivo
                $user,
                $d('fecha'),
                'egreso',
                (float) $d('monto'),
                'Gasto — ' . ($gasto->concepto?->nombre ?? 'operativo'),
                'gasto',
                $gasto->id,
            );

            return $gasto;
        });
    }

    /** Revierte su egreso, lo borra en suave y recuadra el turno si ya cerró. */
    public function eliminar(Gasto $gasto, User $user): void
    {
        $this->autorizarModificacion($gasto, $user);

        DB::transaction(function () use ($gasto, $user) {
            AuditoriaService::log('gasto.eliminado', $gasto, [
                'monto'    => (float) $gasto->monto,
                'fecha'    => optional($gasto->fecha)->toDateString(),
                'concepto' => $gasto->concepto?->nombre,
                'turno_id' => $gasto->turno_id,
            ], $user);

            // Revertir el egreso de tesorería y borrar en suave (queda en
            // "Eliminados" y se puede reactivar).
            $this->tesoreria->revertir('gasto', $gasto->id);
            $gasto->delete();
            $this->recuadrarTurno($gasto->turno_id, $user);
        });
    }

    /**
     * Reglas para editar / eliminar / reactivar un gasto: un administrador
     * puede con cualquiera (si el turno está cerrado, se recuadra al guardar);
     * un no-admin solo con SUS gastos de turno mientras el turno siga abierto
     * (nunca con gastos administrativos).
     */
    public function autorizarModificacion(Gasto $gasto, User $user): void
    {
        $turno = $gasto->turno;

        // Un turno cerrado ya cuadró su caja con este gasto. Solo el admin lo
        // corrige, y el cuadre del turno se recalcula en el acto (recuadrarTurno).
        // Exigir reabrir lo dejaba sin salida: reabrir se bloquea si la cajera
        // o la caja ya tienen otro turno abierto, que es lo normal al día siguiente.
        if ($turno && $turno->estado !== 'abierto') {
            abort_unless($user->rol?->es_admin, 422,
                'El turno de este gasto ya está cerrado: solo un administrador puede corregirlo.');
            // Consolidado: el conteo del consolidador ya se asentó contra el
            // esperado viejo; recalcularlo aquí lo dejaría a medias.
            abort_if($turno->consolidacion()->exists(), 422,
                'El turno de este gasto ya fue consolidado: reabre el turno para modificarlo.');
            // Cierre rápido con destino del efectivo: la entrega y lo que quedó
            // en el cajón salieron del esperado viejo; se corrigen reabriendo.
            abort_if($turno->monto_cierre_declarado === null && $turno->destino_efectivo !== null, 422,
                'El turno de este gasto se cerró entregando el efectivo según el sistema: reabre el turno para modificarlo.');
        }

        if ($user->rol?->es_admin) return;

        abort_if($gasto->esAdministrativo(), 403, 'Solo un administrador puede modificar gastos administrativos.');

        abort_if(!$turno, 403, 'Solo puedes modificar gastos de un turno abierto.');
        abort_if($turno->user_id !== $user->id, 403, 'Este gasto es del turno de otra persona: solo ella o un administrador pueden modificarlo.');
    }

    /**
     * Tras corregir un gasto de un turno CERRADO: recalcula el esperado y la
     * diferencia con el conteo que ya declaró la cajera, y reasienta el
     * sobrante/faltante en tesorería. Sin esto el gasto viejo seguía
     * "explicado" por un sobrante/faltante que ya no existe y la caja quedaba
     * contada dos veces. Turno abierto: no hace nada (se cuadra al cerrar).
     */
    public function recuadrarTurno(?int $turnoId, User $user): void
    {
        if (!$turnoId) return;

        $turno = Turno::whereKey($turnoId)->lockForUpdate()->first();
        if (!$turno || $turno->estado === 'abierto') return;

        $antes = [
            'esperado'   => $turno->monto_cierre_esperado !== null ? (float) $turno->monto_cierre_esperado : null,
            'diferencia' => $turno->diferencia !== null ? (float) $turno->diferencia : null,
        ];

        $esperado   = round($turno->calcularMontoEsperado(), 2);
        $declarado  = $turno->monto_cierre_declarado !== null ? (float) $turno->monto_cierre_declarado : null;
        $diferencia = $declarado === null ? null : round($declarado - $esperado, 2);

        $turno->update(['monto_cierre_esperado' => $esperado, 'diferencia' => $diferencia]);

        // Mismo criterio que TurnoController::cerrar: el asiento de
        // sobrante/faltante solo existe si la empresa no exige consolidación.
        // Conserva la fecha del asiento original (el día del cierre).
        $fechaAsiento = \App\Models\CuentaMovimiento::where('ref_tipo', 'cierre_turno')->where('ref_id', $turno->id)->value('fecha')
            ?? $turno->fecha_cierre?->toDateString() ?? now()->toDateString();
        $this->tesoreria->revertir('cierre_turno', $turno->id);
        if (!$turno->empresa?->requiere_consolidacion_caja && $diferencia !== null && abs($diferencia) >= 0.01) {
            $this->tesoreria->registrar(
                $turno->empresa_id,
                null, // efectivo
                $turno->user_cierre_id ?? $user,
                substr((string) $fechaAsiento, 0, 10),
                $diferencia > 0 ? 'ingreso' : 'egreso',
                abs($diferencia),
                ($diferencia > 0 ? 'Sobrante' : 'Faltante') . " de caja — cierre turno #{$turno->id} ({$turno->user?->name})",
                'cierre_turno',
                $turno->id,
            );
        }

        AuditoriaService::log('turno.cuadre_recalculado', $turno, [
            'motivo'  => 'Corrección de un gasto del turno cerrado',
            'antes'   => $antes,
            'despues' => ['esperado' => $esperado, 'diferencia' => $diferencia],
        ], $user);
    }

    /**
     * Un gasto de turno sale de ESE cajón: su fecha tiene que caer dentro del
     * turno (del día de apertura al de cierre, o a hoy si sigue abierto). Si
     * no, el balance lo pondría en un día y la caja en otro.
     */
    public function validarFechaEnTurno(?int $turnoId, ?string $fecha): void
    {
        if (!$turnoId || !$fecha) return;

        $turno = Turno::find($turnoId);
        if (!$turno || !$turno->fecha_apertura) return;

        $dia   = substr($fecha, 0, 10);
        $desde = $turno->fecha_apertura->toDateString();
        $hasta = ($turno->fecha_cierre ?? now())->toDateString();

        if ($dia < $desde || $dia > $hasta) {
            $rango = $desde === $hasta
                ? \Illuminate\Support\Carbon::parse($desde)->format('d/m/Y')
                : \Illuminate\Support\Carbon::parse($desde)->format('d/m/Y') . ' al ' . \Illuminate\Support\Carbon::parse($hasta)->format('d/m/Y');
            throw \Illuminate\Validation\ValidationException::withMessages([
                'fecha' => "La fecha del gasto debe ser la del turno ({$rango}). Si es de otro día, regístralo como gasto administrativo o en el turno de ese día.",
            ]);
        }
    }
}
