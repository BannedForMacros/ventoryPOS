<?php

namespace App\Services;

use App\Jobs\ConsultarEstadoComprobante;
use App\Models\Devolucion;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaComprobante;
use App\Services\Facturacion\FacturacionEmpresa;
use App\Services\Facturacion\FacturaMacException;
use Illuminate\Support\Facades\Schema;

/**
 * Anular una venta con BOLETA sin nota de crédito: baja ante SUNAT por Resumen
 * Diario (estado 3).
 *
 * Es para la boleta que NO se entregó al cliente (error al cobrar, venta que no se
 * concretó). Si el cliente se llevó la boleta y devuelve, sigue siendo nota de
 * crédito (AnulacionConNotaCredito): quien anula lo elige en la pantalla.
 *
 * No hay que esperar a que la boleta llegue a SUNAT: FacturaMac la informa en el
 * momento si aún no la conocía y luego manda la baja, solo (BajaBoletaService en
 * FacturaMac). Aquí la venta se anula como cualquier otra (VentaService::anular:
 * vuelve el stock, se revierte el cobro de la caja, los abonos y los anticipos), y
 * la baja se pide DENTRO de esa misma transacción: si FacturaMac la rechaza, no se
 * revierte nada; si la acepta, la venta queda anulada. Nunca una cosa sin la otra.
 */
class AnulacionConBajaBoleta
{
    /** Igual que FacturaMac (BajaBoletaService::PLAZO_DIAS), que es quien decide al final. */
    public const PLAZO_DIAS = 7;

    /** Boleta que todavía se puede dar de baja. */
    private const ESTADOS_BAJA = ['pendiente_resumen', 'aceptado'];

    /**
     * Baja ya pedida en FacturaMac (desde aquí o desde su portal) con la venta aún
     * vigente: solo falta revertir stock y caja. Pedirla otra vez es idempotente.
     */
    private const ESTADOS_YA_EN_BAJA = ['pendiente_anulacion', 'anulado'];

    public function __construct(
        private VentaService $ventas,
        private FacturacionEmpresa $facturacion,
    ) {}

    /**
     * ¿La venta admite anularse por baja? Sin consultas a la base: la lista lo
     * pregunta por cada fila. El plan (y FacturaMac) vuelven a comprobarlo todo.
     */
    public static function admite(?VentaComprobante $ce, Venta $venta): bool
    {
        if (! $ce || $ce->tipo !== '03' || ! $ce->facturamac_id || $venta->estado !== 'completada') {
            return false;
        }

        if (in_array($ce->estado, self::ESTADOS_YA_EN_BAJA, true)) {
            return true;
        }

        return in_array($ce->estado, self::ESTADOS_BAJA, true) && ! self::fueraDePlazo($venta);
    }

    public static function fueraDePlazo(Venta $venta): bool
    {
        $fecha = $venta->fecha_venta ?? $venta->created_at;

        return $fecha !== null && $fecha->copy()->addDays(self::PLAZO_DIAS)->endOfDay()->isPast();
    }

    /**
     * Lo que haría, para mostrarlo ANTES de confirmar, o null si no aplica.
     *
     * @return array{comprobante: string, en_tramite: bool, total: float,
     *               pagos: list<array{metodo: string, monto: float}>, bloqueo: ?string}|null
     */
    public function plan(Venta $venta): ?array
    {
        if (! Schema::hasTable('venta_comprobantes')) {
            return null;
        }

        $ce = $venta->comprobanteElectronico()->first();
        if (! self::admite($ce, $venta)) {
            return null;
        }

        $venta->loadMissing('pagos.metodoPago');

        $plan = [
            'comprobante' => $ce->numero,
            'en_tramite'  => in_array($ce->estado, self::ESTADOS_YA_EN_BAJA, true),
            'total'       => round((float) $venta->total, 2),
            'pagos'       => $venta->pagos
                ->map(fn ($p) => ['metodo' => $p->metodoPago?->nombre ?? 'Pago', 'monto' => round((float) $p->monto - (float) $p->vuelto, 2)])
                ->filter(fn ($p) => $p['monto'] > 0.009)
                ->values()->all(),
            'bloqueo'     => null,
        ];

        // Una devolución ya movió stock y dinero (y quizá su propia nota de crédito):
        // dar de baja la boleta encima revertiría la operación dos veces.
        $devolucion = Devolucion::where('venta_id', $venta->id)
            ->whereIn('estado', ['pendiente', 'aprobada', 'completada'])
            ->first(['numero']);
        if ($devolucion) {
            $plan['bloqueo'] = 'Esta venta tiene la devolución ' . ($devolucion->numero ?: 'registrada')
                . ': la boleta ya no se puede dar de baja. Si hace falta revertir el resto, usa Devoluciones.';
        }

        if (! $this->facturacion->activa((int) $venta->empresa_id)) {
            $plan['bloqueo'] ??= 'La facturación electrónica está desactivada: no se puede comunicar la baja a SUNAT.';
        }

        return $plan;
    }

    /**
     * Anula la venta y da de baja su boleta, en un paso.
     *
     * @throws \RuntimeException con el motivo para la persona (incluido el rechazo de FacturaMac).
     */
    public function ejecutar(Venta $venta, User $user, string $motivo): void
    {
        $plan = $this->plan($venta);
        if (! $plan) {
            throw new \RuntimeException('Esta venta no tiene una boleta que se pueda dar de baja. Anúlala con nota de crédito.');
        }
        if ($plan['bloqueo']) {
            throw new \RuntimeException($plan['bloqueo']);
        }

        $ce      = $venta->comprobanteElectronico()->first();
        $cliente = $this->facturacion->cliente((int) $venta->empresa_id);

        $this->ventas->anular($venta, $user, $motivo, bajaBoleta: function () use ($ce, $cliente, $motivo): void {
            try {
                $respuesta = $cliente->baja((int) $ce->facturamac_id, $motivo);
            } catch (FacturaMacException $e) {
                // Lanzar deshace la transacción: el stock y la caja quedan como estaban.
                throw new \RuntimeException('FacturaMac no aceptó la baja de la boleta: ' . $e->paraUsuario());
            }

            $ce->update(['estado' => $respuesta->estado?->value ?? 'pendiente_anulacion']);
        });

        // Seguir el trámite hasta 'anulado' (lo lleva FacturaMac; aquí solo se refleja).
        if (! $ce->fresh()->esFinal()) {
            ConsultarEstadoComprobante::dispatch($ce->id, (int) $venta->empresa_id)->delay(now()->addMinutes(2));
        }
    }
}
