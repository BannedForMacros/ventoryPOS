<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Turno extends Model
{
    protected $fillable = [
        'empresa_id', 'local_id', 'caja_id', 'user_id', 'user_cierre_id',
        'monto_apertura', 'monto_fondos_adicionales', 'monto_caja_chica',
        'monto_cierre_declarado', 'monto_cierre_esperado', 'diferencia',
        'estado', 'fecha_apertura', 'fecha_cierre',
        'observacion_apertura', 'observacion_cierre',
        'efectivo_arrastre', 'destino_efectivo',
    ];

    protected function casts(): array
    {
        return [
            'fecha_apertura'             => 'datetime',
            'fecha_cierre'               => 'datetime',
            'monto_apertura'             => 'decimal:2',
            'monto_fondos_adicionales'   => 'decimal:2',
            'monto_caja_chica'           => 'decimal:2',
            'monto_cierre_declarado'    => 'decimal:2',
            'monto_cierre_esperado'      => 'decimal:2',
            'diferencia'                 => 'decimal:2',
            'efectivo_arrastre'          => 'decimal:2',
        ];
    }

    public function empresa(): BelongsTo        { return $this->belongsTo(Empresa::class); }
    public function local(): BelongsTo          { return $this->belongsTo(Local::class); }
    public function caja(): BelongsTo           { return $this->belongsTo(Caja::class); }
    public function user(): BelongsTo           { return $this->belongsTo(User::class); }
    public function userCierre(): BelongsTo     { return $this->belongsTo(User::class, 'user_cierre_id'); }
    public function arqueo(): HasMany           { return $this->hasMany(TurnoArqueo::class); }
    public function arqueoMetodos(): HasMany    { return $this->hasMany(TurnoArqueoMetodo::class); }
    public function cierreProductos(): HasMany  { return $this->hasMany(TurnoCierreProducto::class); }
    public function consolidacion(): \Illuminate\Database\Eloquent\Relations\HasOne { return $this->hasOne(TurnoConsolidacion::class); }
    public function gastos(): HasMany           { return $this->hasMany(Gasto::class); }
    public function ventas(): HasMany           { return $this->hasMany(\App\Models\Venta::class); }
    public function retiros(): HasMany          { return $this->hasMany(TurnoRetiro::class); }
    public function cancelacionesAnticipo(): HasMany { return $this->hasMany(ClienteAnticipoCancelacion::class, 'turno_id'); }

    public function scopeAbierto($q)        { return $q->where('estado', 'abierto'); }
    public function scopeCerrado($q)        { return $q->where('estado', 'cerrado'); }
    public function scopeDeEmpresa($q, $id) { return $q->where('empresa_id', $id); }

    /**
     * Calcula el monto esperado en caja al cierre.
     *
     * Composición:
     *   monto_apertura
     * + ventas en efectivo del turno (pagos con método tipo='efectivo' en ventas completadas)
     * - gastos del turno (siempre se asume que se pagan con efectivo de la caja)
     * + monto_caja_chica  (solo si la empresa/local incluye fondos iniciales en la declaración)
     *
     * El último sumando depende de la configuración de la empresa/local: cuando los
     * fondos iniciales se incluyen en la declaración, el cajero al final cuenta también
     * los billetes que estaban como caja chica, así que el sistema espera ver ese monto.
     */
    public function calcularMontoEsperado(): float
    {
        // Gastos del turno pagados con EFECTIVO: solo estos descuentan el cajón.
        // Si el gasto no tiene cuenta_id, se asume efectivo (compatibilidad con
        // registros anteriores). Si tiene cuenta_id, debe marcar es_efectivo.
        $gastosEfectivo = (float) $this->gastos()
            ->where(fn($q) =>
                $q->whereNull('cuenta_id')
                  ->orWhereHas('cuenta', fn($c) => $c->where('es_efectivo', true))
            )->sum('monto');

        $ventasEfectivo = (float) \App\Models\VentaPago::whereHas('venta', fn($q) =>
            $q->where('turno_id', $this->id)->where('estado', 'completada')
        )->whereHas('metodoPago.tipo', fn($q) =>
            $q->where('slug', 'efectivo')
        )->sum('monto');

        // Abonos a cuentas por cobrar cobrados EN EFECTIVO durante el turno:
        // ese billete entra al cajón, así que el sistema debe esperarlo.
        $abonosEfectivo = (float) \App\Models\VentaAbono::where('turno_id', $this->id)
            ->whereHas('metodoPago.tipo', fn($q) => $q->where('slug', 'efectivo'))
            ->sum('monto');

        // Anticipos de clientes cobrados EN EFECTIVO durante el turno: ese
        // billete entra al cajón, así que el sistema debe esperarlo. Se cuenta
        // el MONTO recibido (aunque luego se aplique en mercadería, el efectivo
        // ya entró y quedó). Solo los ANULADOS se excluyen: anular revierte el
        // ingreso en tesorería, así que ese dinero nunca existió.
        //
        // Los DEVUELTOS sí cuentan aquí: el billete entró de verdad en ESTE turno
        // y se quedó. La devolución se descuenta abajo, en el turno por cuya caja
        // salió — que casi nunca es este (suele ser días después). Excluirlos aquí
        // descuadraba dos cajas de un golpe: la de origen perdía un ingreso real
        // y la de la devolución nunca veía el egreso.
        //
        // Efectivo = método efectivo, o sin método con cuenta de efectivo (mismo
        // criterio que las compras de abajo).
        $anticiposEfectivo = (float) \App\Models\ClienteAnticipo::where('turno_id', $this->id)
            ->where('estado', '<>', 'anulado')
            ->where(fn($q) =>
                $q->whereHas('metodoPago.tipo', fn($t) => $t->where('slug', 'efectivo'))
                  ->orWhere(fn($q2) => $q2->whereNull('metodo_pago_id')
                        ->whereHas('cuenta', fn($c) => $c->where('es_efectivo', true)))
            )->sum('monto');

        // Devoluciones de anticipo pagadas EN EFECTIVO desde ESTA caja: el billete
        // sale del cajón y el sistema no debe esperarlo.
        //
        // El anticipo NO dice de qué caja salió (su turno_id es el turno donde
        // ENTRÓ el dinero — normalmente de otro día, o NULL si nació de una venta
        // POS). La caja se DERIVA del movimiento de tesorería: quien registró la
        // devolución (user_id) y cuándo lo hizo de verdad (created_at, no `fecha`,
        // que puede venir retrofechada). Si ese usuario tenía este turno abierto
        // en ese instante, el billete salió de este cajón.
        //
        // Derivarlo en vez de guardarlo tiene una ventaja: arregla también las
        // devoluciones YA registradas, sin migrar ni corregir datos.
        //
        // Monto y "efectividad" se leen del movimiento (mismo patrón que deuda,
        // abajo): se devuelve el SALDO al momento de devolver, no el monto
        // original, y la cuenta de salida puede diferir de la de entrada.
        $devolucionAnticipoEfectivo = (float) \App\Models\CuentaMovimiento::where('empresa_id', $this->empresa_id)
            ->where('tipo', 'egreso')
            ->where('ref_tipo', 'cliente_anticipo_devolucion')
            ->where('user_id', $this->user_id)
            ->where('created_at', '>=', $this->fecha_apertura)
            ->when($this->fecha_cierre, fn ($q) => $q->where('created_at', '<=', $this->fecha_cierre))
            ->whereHas('cuenta', fn ($c) => $c->where('es_efectivo', true))
            ->sum('monto');

        // Cancelaciones de pendiente de anticipo pagadas EN EFECTIVO desde
        // esta caja: el billete sale del cajón y el sistema no debe esperarlo.
        $cancelacionPendienteEfectivo = (float) \App\Models\CuentaMovimiento::where('empresa_id', $this->empresa_id)
            ->where('tipo', 'egreso')
            ->where('ref_tipo', 'anticipo_cancelacion')
            ->where('user_id', $this->user_id)
            ->where('created_at', '>=', $this->fecha_apertura)
            ->when($this->fecha_cierre, fn ($q) => $q->where('created_at', '<=', $this->fecha_cierre))
            ->whereHas('cuenta', fn ($c) => $c->where('es_efectivo', true))
            ->sum('monto');

        // Reembolsos de devoluciones en efectivo del turno (descuentan caja)
        $reembolsosEfectivo = (float) \App\Models\DevolucionPago::whereHas('devolucion', fn($q) =>
            $q->where('turno_id', $this->id)
              ->whereIn('estado', ['aprobada', 'completada'])
        )->whereHas('metodoPago.tipo', fn($q) =>
            $q->where('slug', 'efectivo')
        )->sum('monto');

        // Pagos de entradas (compras) hechos en EFECTIVO desde esta caja: salen del
        // cajón. Sin esto, el sistema esperaba más efectivo del que había.
        $comprasEfectivo = (float) \App\Models\EntradaPago::where('turno_id', $this->id)
            ->where(fn($q) =>
                $q->whereHas('metodoPago.tipo', fn($t) => $t->where('slug', 'efectivo'))
                  ->orWhere(fn($q2) => $q2->whereNull('metodo_pago_id')
                        ->whereHas('cuenta', fn($c) => $c->where('es_efectivo', true)))
            )->sum('monto');

        // Retiros de efectivo del turno (sangrías / entrega a administración):
        // ese billete salió físicamente del cajón, el sistema no debe esperarlo.
        // Los retiros con momento='cierre' se generan DESPUÉS de calcular el
        // esperado (son la entrega del efectivo final), por eso no se restan.
        $retirosEfectivo = (float) $this->retiros()->where('momento', 'turno')->sum('monto');

        // Adelantos a proveedores pagados en EFECTIVO desde esta caja: el billete
        // sale del cajón. Los anulados se excluyen (su egreso fue revertido). Los
        // devueltos también (su devolución genera un ingreso separado).
        $adelantosProveedorEfectivo = (float) \App\Models\ProveedorAdelanto::where('turno_id', $this->id)
            ->whereNotIn('estado', ['anulado', 'devuelto'])
            ->where(fn($q) =>
                $q->whereHas('metodoPago.tipo', fn($t) => $t->where('slug', 'efectivo'))
                  ->orWhere(fn($q2) => $q2->whereNull('metodo_pago_id')
                        ->whereHas('cuenta', fn($c) => $c->where('es_efectivo', true)))
            )->sum('monto');

        // Deuda / préstamos (módulo "Afecta caja"): desembolsos iniciales y
        // cuotas/incrementos pagados en EFECTIVO desde esta caja. A diferencia
        // de los demás módulos, la "efectividad" y el signo se leen del
        // movimiento de tesorería (ref_tipo deuda/deuda_pago sobre cuenta
        // es_efectivo): ingreso entra al cajón (+), egreso sale (−). El turno se
        // toma del turno_id de la deuda / del pago (imputado vía AfectaCaja).
        // Solo cuentan los que aún tienen movimiento vivo: eliminar una deuda
        // revierte tesorería (desaparece de aquí); anular solo la oculta y NO
        // revierte caja, así que su efectivo sigue contando (correcto: el
        // billete ya entró/salió del cajón).
        $deudaEfectivo = function (string $tipoMov) {
            return (float) \App\Models\CuentaMovimiento::where('empresa_id', $this->empresa_id)
                ->where('tipo', $tipoMov)
                ->whereHas('cuenta', fn ($c) => $c->where('es_efectivo', true))
                ->where(function ($q) {
                    $q->where(fn ($s) => $s->where('ref_tipo', 'deuda')
                            ->whereIn('ref_id', \App\Models\Deuda::where('turno_id', $this->id)->select('id')))
                      ->orWhere(fn ($s) => $s->where('ref_tipo', 'deuda_pago')
                            ->whereIn('ref_id', \App\Models\DeudaPago::where('turno_id', $this->id)->select('id')));
                })
                ->sum('monto');
        };
        $deudaIngreso = $deudaEfectivo('ingreso');
        $deudaEgreso  = $deudaEfectivo('egreso');

        $apertura     = (float) $this->monto_apertura;
        $fondos       = (float) $this->monto_caja_chica;
        $sumaFondos   = $this->fondosEntranEnDeclaracion();

        return $apertura
             + $ventasEfectivo
             + $abonosEfectivo
             + $anticiposEfectivo
             + $deudaIngreso
             - $gastosEfectivo
             - $reembolsosEfectivo
             - $comprasEfectivo
             - $retirosEfectivo
             - $adelantosProveedorEfectivo
             - $deudaEgreso
             - $devolucionAnticipoEfectivo
             - $cancelacionPendienteEfectivo
             + ($sumaFondos ? $fondos : 0.0);
    }

    /**
     * Apertura sugerida para un nuevo turno de una caja según la configuración
     * de la empresa: arrastre del último cierre, fondo fijo, o null (modo libre
     * o sin historial → digitación manual).
     */
    public static function aperturaSugeridaParaCaja(Caja $caja): ?array
    {
        $empresa = $caja->empresa ?? Empresa::find($caja->empresa_id);
        $modo = $empresa?->modo_apertura_caja ?? 'libre';

        if ($modo === 'fondo_fijo') {
            return [
                'monto'  => (float) $caja->fondo_fijo_monto,
                'origen' => 'fondo_fijo',
                'detalle' => 'Fondo fijo configurado para esta caja',
            ];
        }

        if ($modo === 'arrastre') {
            $ultimo = static::where('caja_id', $caja->id)
                ->where('estado', 'cerrado')
                ->orderByDesc('fecha_cierre')
                ->first();
            if (!$ultimo) return null; // primera apertura de la caja: manual

            // Lo que quedó en el cajón: destino registrado al cierre; si el
            // cierre es anterior a esta función, lo físico contado (declarado)
            // o en su defecto el esperado.
            $monto = $ultimo->efectivo_arrastre
                ?? $ultimo->monto_cierre_declarado
                ?? $ultimo->monto_cierre_esperado;
            if ($monto === null) return null;

            return [
                'monto'   => (float) $monto,
                'origen'  => 'arrastre',
                'detalle' => 'Efectivo que quedó al cierre del ' .
                    $ultimo->fecha_cierre?->format('d/m/Y H:i') . ' (' . ($ultimo->user?->name ?? '—') . ')',
                'turno_anterior_id' => $ultimo->id,
            ];
        }

        return null; // modo libre
    }

    /**
     * Monto del turno que proviene del arrastre del cierre anterior.
     * Es el total de apertura menos los fondos adicionales inyectados.
     */
    public function getMontoArrastreAttribute(): float
    {
        return round((float) $this->monto_apertura - (float) ($this->monto_fondos_adicionales ?? 0), 2);
    }

    /**
     * true si para este turno los fondos iniciales se cuentan en el arqueo de cierre.
     * Resuelve la jerarquía local→empresa.
     */
    public function fondosEntranEnDeclaracion(): bool
    {
        $local = $this->local;
        if (!$local) return false;
        return app(\App\Services\ConfiguracionOperacionService::class)
            ->fondosInicialesEnDeclaracion($local);
    }

    public function calcularTotalArqueo(): float
    {
        return (float) $this->arqueo()->sum('subtotal');
    }

    /**
     * Recorre las ventas COMPLETADAS del turno y guarda un snapshot agregado por producto.
     * Idempotente: borra y recrea las filas para este turno.
     *
     * Además, captura el `stock_final` del producto en el almacén de ventas del turno
     * (foto al momento del cierre). Útil para auditoría incluso en modo 'por_venta':
     * permite reconstruir cuánto stock había al cierre sin depender de la tabla `stock`,
     * que sigue mutando en turnos posteriores.
     */
    public function poblarSnapshotProductos(): void
    {
        DB::transaction(function () {
            TurnoCierreProducto::where('turno_id', $this->id)->delete();

            // Resolver almacén de ventas del turno (igual que VentaService)
            $almacenId = $this->resolverAlmacenIdDeVentas();

            $ventas = \App\Models\VentaItem::whereHas('venta', fn($q) =>
                $q->where('turno_id', $this->id)->where('estado', 'completada')
            )
            ->select(
                'producto_id',
                DB::raw("MIN(producto_nombre) as producto_nombre"),
                DB::raw('SUM(cantidad) as cantidad_vendida'),
                DB::raw('SUM(subtotal) as total'),
                DB::raw('AVG(precio_unitario) as precio_unitario')
            )
            ->groupBy('producto_id')
            ->get();

            // Stock actual por producto en el almacén (un solo query)
            $stocks = $almacenId
                ? \App\Models\Stock::where('almacen_id', $almacenId)
                    ->whereIn('producto_id', $ventas->pluck('producto_id'))
                    ->pluck('cantidad', 'producto_id')
                : collect();

            foreach ($ventas as $row) {
                TurnoCierreProducto::create([
                    'turno_id'         => $this->id,
                    'producto_id'      => $row->producto_id,
                    'producto_nombre'  => $row->producto_nombre,
                    'cantidad_vendida' => $row->cantidad_vendida,
                    'precio_unitario'  => round((float) $row->precio_unitario, 2),
                    'total'            => round((float) $row->total, 2),
                    'stock_final'      => $stocks[$row->producto_id] ?? null,
                ]);
            }
        });
    }

    /**
     * Productos vendidos en este turno (ventas completadas) cuyo stock actual
     * en el almacén de ventas quedó NEGATIVO. Se usa para avisar al cajero al
     * cerrar la caja: "vendiste estos productos y su stock quedó en negativo,
     * ¿deseas cerrar de todas formas?".
     *
     * Devuelve arrays con: producto_id, producto_nombre, cantidad_vendida
     * (en unidad base) y stock_actual (negativo).
     */
    public function productosVendidosConStockNegativo(): array
    {
        $almacenId = $this->resolverAlmacenIdDeVentas();
        if (!$almacenId) return [];

        return \App\Models\VentaItem::query()
            ->join('ventas as v', 'v.id', '=', 'venta_items.venta_id')
            ->join('stock as s', function ($j) use ($almacenId) {
                $j->on('s.producto_id', '=', 'venta_items.producto_id')
                  ->where('s.almacen_id', '=', $almacenId);
            })
            ->where('v.turno_id', $this->id)
            ->where('v.estado', 'completada')
            ->where('s.cantidad', '<', 0)
            ->groupBy('venta_items.producto_id', 's.cantidad')
            ->select(
                'venta_items.producto_id',
                DB::raw('MIN(venta_items.producto_nombre) as producto_nombre'),
                DB::raw('SUM(venta_items.cantidad_base) as cantidad_vendida'),
                DB::raw('s.cantidad as stock_actual'),
            )
            ->orderBy('producto_nombre')
            ->get()
            ->map(fn ($r) => [
                'producto_id'      => (int) $r->producto_id,
                'producto_nombre'  => $r->producto_nombre,
                'cantidad_vendida' => (float) $r->cantidad_vendida,
                'stock_actual'     => (float) $r->stock_actual,
            ])
            ->all();
    }

    /**
     * Resuelve el almacén de ventas usado durante este turno.
     * En modo simple → el único almacén tipo='local'.
     * En central_y_local → el almacén local ligado al local del turno.
     */
    protected function resolverAlmacenIdDeVentas(): ?int
    {
        $almacen = \App\Models\Almacen::where('empresa_id', $this->empresa_id)
            ->where('local_id', $this->local_id)
            ->where('tipo', 'local')
            ->where('activo', true)
            ->first();

        if ($almacen) return $almacen->id;

        // Fallback (modo simple antiguo donde el almacén único era 'central')
        return \App\Models\Almacen::where('empresa_id', $this->empresa_id)
            ->where('activo', true)
            ->orderBy('id')
            ->value('id');
    }

    public static function turnoActivoDelUsuario(int $userId): ?self
    {
        return static::where('user_id', $userId)->where('estado', 'abierto')->first();
    }
}
