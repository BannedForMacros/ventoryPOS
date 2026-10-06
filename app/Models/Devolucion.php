<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use LogicException;

class Devolucion extends Model
{
    protected $table = 'devoluciones';

    protected $fillable = [
        'empresa_id',
        'local_id',
        'turno_id',
        'caja_id',
        'venta_id',
        'user_id',
        'user_aprobacion_id',
        'numero',
        'fecha',
        'motivo_id',
        'forma_reembolso',
        'monto_devolucion',
        'monto_reembolso',
        'requiere_aprobacion',
        'fue_aprobada',
        'estado',
        'observacion',
        'fecha_aprobacion',
        'observacion_aprobacion',
        'nota_credito_estado',
        'nota_credito_numero',
        'nota_credito_facturamac_id',
        'nota_credito_error',
        'nota_credito_at',
    ];

    /**
     * ─── Estado de la nota de crédito de esta devolución ─────────────────────
     *
     * La NC se emite en segundo plano y puede tardar (una boleta espera al
     * Resumen Diario de las 23:55) o fallar del todo. Antes eso vivía solo en el
     * log: la devolución quedaba hecha, SUNAT seguía viendo el importe original
     * y nadie se enteraba. Aquí queda el rastro para poder listarlo, avisarlo y
     * reintentarlo.
     *
     * NULL es un estado legítimo: devoluciones anteriores a este cambio, sobre
     * las que no se comprobó nada. No se rellenan hacia atrás.
     */
    public const NC_NO_APLICA = 'no_aplica';
    public const NC_PENDIENTE = 'pendiente';
    public const NC_ESPERANDO = 'esperando';
    public const NC_EMITIDA   = 'emitida';
    public const NC_FALLIDA   = 'fallida';

    /** Los que dejan trabajo sin terminar: son los que se listan y se avisan. */
    public const NC_SIN_CERRAR = [self::NC_PENDIENTE, self::NC_ESPERANDO, self::NC_FALLIDA];

    protected function casts(): array
    {
        return [
            'fecha'              => 'datetime',
            'fecha_aprobacion'   => 'datetime',
            'monto_devolucion'   => 'decimal:2',
            'monto_reembolso'    => 'decimal:2',
            'requiere_aprobacion' => 'boolean',
            'fue_aprobada'        => 'boolean',
            'nota_credito_at'     => 'datetime',
        ];
    }

    /**
     * Anota en qué quedó la nota de crédito. ÚNICO sitio que escribe estas
     * columnas: el job tiene siete desenlaces distintos y repartir el `update()`
     * por todos ellos es cómo se acaba con un estado que no corresponde.
     *
     * Escribe con `updateQuietly` y sin tocar el resto del modelo: esto corre
     * desde la cola, mucho después de que la devolución se cerrara, y no tiene
     * por qué mover su `updated_at` ni disparar observadores de la devolución.
     *
     * El error se limpia SIEMPRE que el desenlace no es un fallo: si un reintento
     * sale bien, dejar el mensaje viejo haría creer que sigue rota.
     */
    public function anotarNotaCredito(string $estado, ?string $error = null, array $datos = []): void
    {
        $this->forceFill([
            'nota_credito_estado' => $estado,
            'nota_credito_error'  => $estado === self::NC_FALLIDA ? $error : null,
            'nota_credito_at'     => now(),
        ] + $datos)->updateQuietly();
    }

    /** ¿Quedó trabajo sin terminar con SUNAT por esta devolución? */
    public function notaCreditoSinCerrar(): bool
    {
        return in_array($this->nota_credito_estado, self::NC_SIN_CERRAR, true);
    }

    /**
     * Estados de la NC con los que la devolución ya NO se puede anular: la nota
     * está emitida o va camino de SUNAT. Anular aquí dejaría el stock y el dinero
     * revertidos mientras SUNAT ve acreditado el importe (o lo verá en minutos).
     * `fallida` no está: esa nota nunca llegó a existir.
     */
    public const NC_BLOQUEAN_ANULACION = [self::NC_EMITIDA, self::NC_PENDIENTE, self::NC_ESPERANDO];

    /**
     * Proporción del bruto que sobrevive al descuento GLOBAL de la venta.
     *
     * Es el MISMO reparto que hace el comprobante (VentaAComprobante) y la nota de
     * crédito (EmitirNotaCreditoElectronica): `ventas.descuento_total` se prorratea
     * por el bruto de cada línea. Sin esto la devolución reembolsaba el precio de
     * lista: sobre una venta de S/ 100 con S/ 10 de descuento, devolver todo
     * sacaba S/ 100 de caja cuando el cliente pagó S/ 90.
     *
     * No lanza: un descuento incoherente (mayor que el bruto) se trata como
     * descuento total. La NC conserva su propia guarda y se niega a emitir.
     */
    public static function factorDescuentoGlobal(Venta $venta): float
    {
        $descuento = round((float) $venta->descuento_total, 2);
        if ($descuento <= 0) {
            return 1.0;
        }

        $venta->loadMissing('items');
        $bruto = 0.0;
        foreach ($venta->items as $item) {
            $bruto += ((float) $item->precio_unitario - (float) $item->descuento_item) * (float) $item->cantidad;
        }

        if ($bruto <= 0) {
            return 1.0;
        }

        return max(0.0, 1 - ($descuento / $bruto));
    }

    /**
     * Importe neto de unas líneas devueltas: Σ subtotal bruto × factor del
     * descuento global, redondeado UNA vez al final (igual que el `total` que la
     * nota de crédito manda a FacturaMac).
     *
     * @param iterable<float> $subtotalesBrutos (precio − descuento_item) × cantidad
     */
    public static function montoNeto(iterable $subtotalesBrutos, float $factor): float
    {
        $suma = 0.0;
        foreach ($subtotalesBrutos as $s) {
            $suma += round((float) $s, 2) * $factor;
        }

        return round($suma, 2);
    }

    /**
     * Cuánto de un importe devuelto se descuenta de la cuenta por cobrar de la
     * venta y cuánto queda para reembolsar (dinero o vale).
     *
     * Una venta al crédito con saldo pendiente: lo devuelto cancela PRIMERO la
     * deuda del cliente. Solo lo que exceda lo que ya pagó se le reembolsa. Antes
     * se le devolvía el importe entero y la CxC seguía viva: se le pagaba por una
     * mercadería que todavía debía.
     *
     * @return array{cxc: float, reembolso: float}
     */
    public static function repartoContraCxc(Venta $venta, float $monto): array
    {
        $saldo = ($venta->es_credito && $venta->estado === 'completada')
            ? max(0.0, round((float) $venta->saldo_pendiente, 2))
            : 0.0;

        $cxc = round(min($monto, $saldo), 2);

        return ['cxc' => $cxc, 'reembolso' => round(max(0.0, $monto - $cxc), 2)];
    }

    public function empresa(): BelongsTo        { return $this->belongsTo(Empresa::class); }
    public function local(): BelongsTo          { return $this->belongsTo(Local::class); }
    public function turno(): BelongsTo          { return $this->belongsTo(Turno::class); }
    public function caja(): BelongsTo           { return $this->belongsTo(Caja::class); }
    public function venta(): BelongsTo          { return $this->belongsTo(Venta::class); }
    public function user(): BelongsTo           { return $this->belongsTo(User::class); }
    public function userAprobacion(): BelongsTo { return $this->belongsTo(User::class, 'user_aprobacion_id'); }
    public function motivo(): BelongsTo         { return $this->belongsTo(DevolucionMotivo::class, 'motivo_id'); }
    public function detalles(): HasMany         { return $this->hasMany(DevolucionDetalle::class); }
    public function pagos(): HasMany            { return $this->hasMany(DevolucionPago::class); }
    /** Anticipo creado automáticamente cuando el reembolso fue "vale/crédito a favor". */
    public function valeAnticipo(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ClienteAnticipo::class, 'devolucion_id');
    }

    public function scopePendiente(Builder $q): Builder   { return $q->where('estado', 'pendiente'); }
    public function scopeCompletada(Builder $q): Builder  { return $q->where('estado', 'completada'); }
    public function scopeAnulada(Builder $q): Builder     { return $q->where('estado', 'anulada'); }
    public function scopeDeEmpresa(Builder $q, int $id): Builder { return $q->where('empresa_id', $id); }

    public function esPendiente(): bool  { return $this->estado === 'pendiente'; }
    public function esAprobada(): bool   { return $this->estado === 'aprobada'; }
    public function esCompletada(): bool { return $this->estado === 'completada'; }
    public function esAnulada(): bool    { return $this->estado === 'anulada'; }

    /**
     * Aprueba la devolución (solo cuando requiere_aprobacion). No aplica stock todavía.
     * Pasa de 'pendiente' a 'aprobada', lista para ser confirmada/completada.
     */
    public function aprobar(int $userId, ?string $observacion = null): void
    {
        if (!$this->esPendiente()) {
            throw new LogicException('Solo se pueden aprobar devoluciones pendientes.');
        }

        $this->update([
            'estado'                 => 'aprobada',
            'fue_aprobada'           => true,
            'user_aprobacion_id'     => $userId,
            'fecha_aprobacion'       => now(),
            'observacion_aprobacion' => $observacion,
        ]);
    }

    public function rechazar(int $userId, ?string $observacion = null): void
    {
        if (!$this->esPendiente()) {
            throw new LogicException('Solo se pueden rechazar devoluciones pendientes.');
        }

        $this->update([
            'estado'                 => 'rechazada',
            'fue_aprobada'           => false,
            'user_aprobacion_id'     => $userId,
            'fecha_aprobacion'       => now(),
            'observacion_aprobacion' => $observacion,
        ]);
    }

    /**
     * Completa la devolución: aplica restock por línea, genera salidas automáticas
     * para productos defectuosos sin restock, y deja la devolución en estado 'completada'.
     */
    public function completar(): void
    {
        if (!in_array($this->estado, ['pendiente', 'aprobada'], true)) {
            throw new LogicException('Solo se pueden completar devoluciones pendientes o aprobadas.');
        }

        if ($this->requiere_aprobacion && !$this->fue_aprobada) {
            throw new LogicException('Esta devolución requiere aprobación antes de completarse.');
        }

        DB::transaction(function () {
            // La venta se BLOQUEA: entre registrar la devolución y aprobarla pudo
            // anularse, y su saldo por cobrar es lo que se va a tocar más abajo.
            $venta = Venta::whereKey($this->venta_id)->lockForUpdate()->first();
            if (!$venta || $venta->estado !== 'completada') {
                throw new LogicException('La venta de esta devolución ya no está vigente (fue anulada). Rechaza la devolución.');
            }
            $this->setRelation('venta', $venta);

            $this->loadMissing(['detalles.producto', 'detalles.motivo', 'venta.local']);

            // Venta al crédito: lo devuelto cancela PRIMERO la deuda del cliente.
            // Solo el resto (lo que ya había pagado) se reembolsa o va al vale.
            $reparto = self::repartoContraCxc($venta, (float) $this->monto_devolucion);

            if ($reparto['cxc'] > 0.009 && in_array($this->forma_reembolso, ['efectivo', 'mismo_metodo'], true)) {
                $this->loadMissing('pagos');
                $pagado = round((float) $this->pagos->sum('monto'), 2);
                if (abs($pagado - $reparto['reembolso']) > 0.01) {
                    throw new LogicException(sprintf(
                        'El saldo por cobrar de la venta cambió desde que se registró la devolución: ahora se '
                        . 'descuentan S/ %.2f de su deuda y solo corresponde reembolsar S/ %.2f (la devolución '
                        . 'registró S/ %.2f). Rechaza esta devolución y regístrala de nuevo.',
                        $reparto['cxc'], $reparto['reembolso'], $pagado,
                    ));
                }
            }

            $almacenId = $this->resolverAlmacenLocal();

            foreach ($this->detalles as $d) {
                if ($d->restock && $almacenId) {
                    Stock::ajustar($almacenId, $d->producto_id, (float) $d->cantidad_base, contexto: [
                        'tipo'            => 'devolucion',
                        'referencia_tipo' => 'devolucion',
                        'referencia_id'   => $this->id,
                        'fecha'           => now(),
                        'user_id'         => optional(auth()->user())->id,
                        'empresa_id'      => $this->empresa_id ?? optional($this->venta)->empresa_id,
                    ]);
                }
                // Si no restockea, no hacemos nada con stock (el producto físicamente no
                // vuelve al inventario; queda en una zona aparte o se descarta).
                // Si la empresa quiere registrar la merma, puede hacerlo manualmente
                // desde el módulo Salidas referenciando la devolución.
            }

            // Vale/crédito a favor → se crea un ANTICIPO de dinero real del
            // cliente (aparece en Finanzas → Anticipos y sirve para pagar en
            // el POS o cobrar sus cuentas por cobrar). SIN tesorería: la plata
            // ya entró con la venta original, aquí solo cambia de "venta" a
            // "saldo a favor del cliente". Antes esto era solo una etiqueta y
            // el vale vivía en la memoria de la cajera.
            //
            // Venta al crédito: la parte que cancela la deuda viaja por el MISMO
            // camino que "cobrar CxC con anticipo" (abono sin dinero enlazado a un
            // anticipo): así la CxC, el balance y la anulación del abono ya saben
            // tratarla, y la devolución queda trazada (anticipo.devolucion_id →
            // abono.cliente_anticipo_id). Por eso el anticipo existe aunque la
            // forma no sea vale: nace y se consume en el acto.
            $montoAnticipo = $this->forma_reembolso === 'vale_credito'
                ? (float) $this->monto_devolucion
                : $reparto['cxc'];

            if ($montoAnticipo > 0.009) {
                $anticipo = ClienteAnticipo::create([
                    'empresa_id'        => $this->empresa_id,
                    'cliente_id'        => $venta->cliente_id,
                    'user_id'           => $this->user_id,
                    'devolucion_id'     => $this->id,
                    'fecha'             => now()->toDateString(),
                    'monto'             => $montoAnticipo,
                    'saldo'             => $montoAnticipo,
                    'tipo_valorizacion' => 'monto',
                    'estado'            => 'activo',
                    'observacion'       => $this->forma_reembolso === 'vale_credito'
                        ? "Vale por devolución {$this->numero} — venta {$venta->numero}"
                        : "Crédito por devolución {$this->numero} — descontado de la deuda de la venta {$venta->numero}",
                ]);

                \App\Services\AuditoriaService::log('anticipo_cliente.creado', $anticipo, [
                    'origen'        => $this->forma_reembolso === 'vale_credito' ? 'devolucion_vale_credito' : 'devolucion_credito_cxc',
                    'devolucion_id' => $this->id,
                    'venta_id'      => $this->venta_id,
                    'monto'         => $montoAnticipo,
                ], auth()->user());

                if ($reparto['cxc'] > 0.009) {
                    $this->aplicarContraCxc($venta, $anticipo, $reparto['cxc']);
                }
            }

            // F7 — Tesorería: si el reembolso devuelve dinero (efectivo o al
            // mismo método), el egreso sale de la cuenta correspondiente.
            // vale_credito / cambio_producto / sin_reembolso no mueven caja.
            if (in_array($this->forma_reembolso, ['efectivo', 'mismo_metodo'], true)) {
                $tesoreria = app(\App\Services\TesoreriaService::class);
                $this->loadMissing('pagos');
                foreach ($this->pagos as $pago) {
                    $tesoreria->registrar(
                        $this->empresa_id,
                        $tesoreria->resolverCuenta($this->empresa_id, $pago->cuenta_metodo_pago_id, $pago->metodo_pago_id),
                        $this->user_id,
                        now()->toDateString(),
                        'egreso',
                        (float) $pago->monto,
                        "Reembolso devolución {$this->numero}",
                        'devolucion',
                        $this->id,
                    );
                }
            }

            $this->update(['estado' => 'completada']);
        });
    }

    /**
     * Descuenta `$monto` de la cuenta por cobrar de la venta consumiendo el
     * anticipo de la devolución. Espejo exacto del cobro de CxC con anticipo
     * (CuentasPorCobrarController::abonar): abono SIN tesorería —no entra dinero—
     * más la aplicación del anticipo enlazada al abono.
     */
    protected function aplicarContraCxc(Venta $venta, ClienteAnticipo $anticipo, float $monto): void
    {
        $fecha = now()->toDateString();

        $abono = VentaAbono::create([
            'venta_id'            => $venta->id,
            'user_id'             => $this->user_id,
            'fecha'               => $fecha,
            'monto'               => $monto,
            'observacion'         => "Descontado por la devolución {$this->numero} (anticipo #{$anticipo->id}).",
            'cliente_anticipo_id' => $anticipo->id,
        ]);

        $anticipo->aplicaciones()->create([
            'empresa_id'     => $venta->empresa_id,
            'numero'         => ClienteAnticipoAplicacion::generarNumero($venta->empresa_id),
            'venta_id'       => $venta->id,
            'venta_abono_id' => $abono->id,
            'user_id'        => $this->user_id,
            'fecha'          => $fecha,
            'monto'          => $monto,
            'observacion'    => "Devolución {$this->numero} — descontada de la deuda de la venta {$venta->numero}",
        ]);

        $saldoAnticipo = round((float) $anticipo->saldo - $monto, 2);
        $anticipo->update([
            'saldo'  => max(0, $saldoAnticipo),
            'estado' => $saldoAnticipo <= 0.01 ? 'aplicado' : 'activo',
        ]);

        $pagado = round((float) $venta->monto_pagado + $monto, 2);
        $venta->update([
            'monto_pagado'    => $pagado,
            'saldo_pendiente' => max(0, round((float) $venta->total - $pagado, 2)),
        ]);

        \App\Services\AuditoriaService::log('cxc.abono_por_devolucion', $venta, [
            'numero'        => $venta->numero,
            'devolucion_id' => $this->id,
            'anticipo_id'   => $anticipo->id,
            'monto'         => $monto,
            'saldo'         => (float) $venta->saldo_pendiente,
        ], auth()->user());
    }

    public function anular(): void
    {
        if ($this->esAnulada()) return;

        // Una nota de crédito emitida (o camino de SUNAT) ya acreditó el importe
        // ante SUNAT. Anular la devolución devolvería el stock y el dinero a como
        // estaban mientras el documento fiscal sigue diciendo que se devolvió: el
        // descuadre solo se corrige con otro comprobante.
        if (in_array($this->nota_credito_estado, self::NC_BLOQUEAN_ANULACION, true)) {
            throw new LogicException($this->nota_credito_estado === self::NC_EMITIDA
                ? 'No se puede anular: esta devolución ya tiene su nota de crédito '
                    . ($this->nota_credito_numero ? "{$this->nota_credito_numero} " : '')
                    . 'emitida ante SUNAT. Si fue un error, regularízalo con un nuevo comprobante (venta) por lo devuelto.'
                : 'No se puede anular: la nota de crédito de esta devolución ya está en camino a SUNAT. '
                    . 'Espera a que se emita y regularízalo con un nuevo comprobante por lo devuelto.');
        }

        // Si el vale ya se gastó (total o parcialmente) no se puede anular la
        // devolución: el cliente ya usó ese crédito. Chequeo ANTES de la
        // transacción para dar un mensaje claro sin efectos a medias.
        $vale = $this->valeAnticipo()->first();

        // Lo que la PROPIA devolución consumió del anticipo para bajar la deuda de
        // su venta al crédito no es "uso" del cliente: se revierte aquí mismo.
        $abonosPropios = $vale
            ? VentaAbono::where('cliente_anticipo_id', $vale->id)->where('venta_id', $this->venta_id)->get()
            : collect();
        $consumoPropio = round((float) $abonosPropios->sum('monto'), 2);
        $usadoPorCliente = $vale
            ? round((float) $vale->monto - (float) $vale->saldo - $consumoPropio, 2)
            : 0.0;

        if ($vale && $vale->estado === 'activo' && $usadoPorCliente > 0.009) {
            throw new LogicException(
                "No se puede anular: el vale de esta devolución ya fue usado en parte "
                . '(quedan S/ ' . number_format((float) $vale->saldo, 2) . ' de S/ ' . number_format((float) $vale->monto, 2) . '). '
                . 'Anula primero los consumos del anticipo en Finanzas → Anticipos.'
            );
        }
        if ($vale && $vale->estado === 'aplicado' && $usadoPorCliente > 0.009) {
            throw new LogicException(
                'No se puede anular: el vale de esta devolución ya fue consumido por completo. '
                . 'Anula primero los consumos del anticipo en Finanzas → Anticipos.'
            );
        }

        DB::transaction(function () use ($vale, $abonosPropios) {
            $estadoPrevio = $this->estado;

            // La deuda que la devolución descontó vuelve a la venta.
            if ($abonosPropios->isNotEmpty()) {
                $venta = Venta::whereKey($this->venta_id)->lockForUpdate()->first();
                foreach ($abonosPropios as $abono) {
                    ClienteAnticipoAplicacion::where('venta_abono_id', $abono->id)->delete();
                    if ($venta) {
                        $pagado = max(0, round((float) $venta->monto_pagado - (float) $abono->monto, 2));
                        $venta->update([
                            'monto_pagado'    => $pagado,
                            'saldo_pendiente' => max(0, round((float) $venta->total - $pagado, 2)),
                        ]);
                    }
                    \App\Services\AuditoriaService::log('cxc.abono_anulado', $venta ?? $this, [
                        'abono_id'      => $abono->id,
                        'monto'         => (float) $abono->monto,
                        'motivo'        => "Anulación de la devolución {$this->numero}",
                        'devolucion_id' => $this->id,
                        'saldo'         => (float) ($venta?->saldo_pendiente ?? 0),
                    ]);
                    $abono->delete();
                }
            }

            // El vale sin usar (o solo consumido por esta devolución) se anula junto con ella.
            if ($vale && in_array($vale->estado, ['activo', 'aplicado'], true)) {
                $vale->update(['estado' => 'anulado']);
                \App\Services\AuditoriaService::log('anticipo_cliente.anulado', $vale, [
                    'origen'        => 'devolucion_anulada',
                    'devolucion_id' => $this->id,
                ], auth()->user());
            }

            // F7 — Revertir el egreso de tesorería del reembolso (si lo hubo).
            app(\App\Services\TesoreriaService::class)->revertir('devolucion', $this->id);

            // Productos que quedan en stock negativo tras revertir el restock.
            // Se incluyen en el contexto de la auditoria para que el admin
            // pueda rastrear inconsistencias generadas por la anulacion.
            $negativosResultantes = [];

            // Si ya estaba completada, revertir el restock aplicado
            if ($this->esCompletada()) {
                $almacenId = $this->resolverAlmacenLocal();
                $this->loadMissing('detalles.producto');

                foreach ($this->detalles as $d) {
                    if ($d->restock && $almacenId) {
                        // Reverso administrativo: si entre tanto se vendieron las unidades restockeadas,
                        // el stock puede quedar transitoriamente negativo. Es responsabilidad del admin
                        // ajustarlo despues. No bloqueamos la anulacion por consistencia contable.
                        $stock = Stock::ajustar(
                            $almacenId,
                            $d->producto_id,
                            -1 * (float) $d->cantidad_base,
                            permitirNegativo: true,
                            contexto: [
                                'tipo'            => 'devolucion_reverso',
                                'referencia_tipo' => 'devolucion',
                                'referencia_id'   => $this->id,
                                'fecha'           => now(),
                                'user_id'         => optional(auth()->user())->id,
                                'empresa_id'      => $this->empresa_id ?? optional($this->venta)->empresa_id,
                            ],
                        );

                        if ((float) $stock->cantidad < 0) {
                            $negativosResultantes[] = [
                                'producto_id'     => $d->producto_id,
                                'producto_nombre' => $d->producto?->nombre,
                                'cantidad_final'  => (float) $stock->cantidad,
                                'cantidad_revertida' => (float) $d->cantidad_base,
                            ];
                        }
                    }
                }
            }

            $this->update(['estado' => 'anulada']);

            \App\Services\AuditoriaService::log('devolucion.anulada', $this, [
                'numero'                  => $this->numero,
                'venta_id'                => $this->venta_id,
                'monto'                   => (float) $this->monto_devolucion,
                'estado_previo'           => $estadoPrevio,
                'forma_reembolso'         => $this->forma_reembolso,
                'genero_stock_negativo'   => !empty($negativosResultantes),
                'stocks_negativos'        => $negativosResultantes ?: null,
            ]);
        });
    }

    /**
     * Almacén local del turno/local de la devolución (donde el producto vuelve al stock).
     */
    protected function resolverAlmacenLocal(): ?int
    {
        $almacen = Almacen::where('empresa_id', $this->empresa_id)
            ->where('local_id', $this->local_id)
            ->where('tipo', 'local')
            ->where('activo', true)
            ->first();

        if ($almacen) return $almacen->id;

        // Fallback: cualquier almacén activo de la empresa (modo simple legacy)
        return Almacen::where('empresa_id', $this->empresa_id)
            ->where('activo', true)
            ->orderBy('id')
            ->value('id');
    }

    public static function generarNumero(int $turnoId): string
    {
        $count = self::where('turno_id', $turnoId)->count() + 1;
        return 'DEV-' . str_pad($turnoId, 4, '0', STR_PAD_LEFT) . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
    }
}
