<?php

namespace App\Services;

use App\Models\BalanceDiario;
use App\Models\BalanceDiarioItem;
use App\Models\ClienteAnticipo;
use App\Models\Cuenta;
use App\Models\Deuda;
use App\Models\Gasto;
use App\Models\ProveedorAdelanto;
use App\Models\User;
use App\Models\Venta;
use Illuminate\Support\Facades\DB;

/**
 * Arma el balance diario replicando el Excel del cliente:
 *
 *   A FAVOR  : cuentas bancarias + efectivo (manuales, conciliadas contra el
 *              banco real), stock valorizado a costo del día, deudas por
 *              cobrar (ventas a crédito + préstamos otorgados), adelantos
 *              a proveedores.
 *   EN CONTRA: proveedores por pagar, anticipos de clientes (a precio del
 *              día), deudas bancarias/personales/del personal.
 *
 *   BALANCE HOY   = Σ favor − Σ contra
 *   UTILIDAD REAL = (hoy − ayer) + gastos del día
 */
class BalanceDiarioService
{
    public function __construct(private TesoreriaService $tesoreria) {}

    /**
     * Obtiene (o crea en borrador) el balance de una fecha y regenera sus
     * líneas automáticas. Las líneas manuales (saldos de cuentas, ajustes)
     * se preservan entre regeneraciones.
     */
    public function generar(User $user, string $fecha): BalanceDiario
    {
        return DB::transaction(function () use ($user, $fecha) {
            $empresaId = $user->empresa_id;

            $balance = BalanceDiario::firstOrCreate(
                ['empresa_id' => $empresaId, 'fecha' => $fecha],
                ['user_id' => $user->id, 'estado' => 'borrador'],
            );

            if (!$balance->esBorrador()) {
                return $balance; // confirmado = snapshot inmutable
            }

            $this->reconstruir($balance);

            return $balance->fresh('items');
        });
    }

    /**
     * Recalcula las métricas del día (balance anterior, gastos, y la UTILIDAD
     * OPERATIVA = ventas − costo de lo vendido − gastos) y regenera las líneas
     * automáticas + totales. La utilidad operativa es la ganancia REAL del día
     * ("cuánto vendí, cuánto gané"): NO se usa el Δpatrimonio, porque pagar
     * proveedores o comprar mercadería baja el saldo sin ser pérdida. El costo
     * usa el snapshot congelado del ítem (mismo criterio del Reporte de Utilidad).
     */
    private function reconstruir(BalanceDiario $balance): void
    {
        $empresaId = $balance->empresa_id;
        $fecha     = $balance->fecha->toDateString();

        $anterior = BalanceDiario::deEmpresa($empresaId)
            ->confirmado()
            ->where('fecha', '<', $fecha)
            ->orderByDesc('fecha')
            ->first();

        $balance->update(['balance_anterior' => $anterior?->balance_neto] + $this->calcularMetricas($empresaId, $fecha));

        $this->regenerarItemsAutomaticos($balance);
        $balance->recalcularTotales();
    }

    /**
     * Ventas, costo de lo vendido, gastos y utilidad operativa de un día.
     * Solo LEE: la usan la regeneración del balance y la verificación de días
     * cerrados (BalanceVerificacionService).
     */
    public function calcularMetricas(int $empresaId, string $fecha): array
    {
        $gastosDia = (float) Gasto::deEmpresa($empresaId)
            ->where('fecha', $fecha)
            ->sum('monto');

        $ventasDia = (float) Venta::deEmpresa($empresaId)
            ->where('estado', 'completada')
            ->whereBetween('fecha_venta', [$fecha . ' 00:00:00', $fecha . ' 23:59:59'])
            ->sum('total');

        $costoSql = "COALESCE(NULLIF(vi.costo_unitario_base, 0), NULLIF(p.precio_costo, 0),
            (SELECT s.costo_promedio FROM stock s WHERE s.producto_id = p.id AND s.costo_promedio > 0 ORDER BY s.id LIMIT 1), 0)";
        $costoDia = (float) DB::table('venta_items as vi')
            ->join('ventas as v', 'v.id', '=', 'vi.venta_id')
            ->join('productos as p', 'p.id', '=', 'vi.producto_id')
            ->where('v.empresa_id', $empresaId)->where('v.estado', 'completada')
            ->whereBetween('v.fecha_venta', [$fecha . ' 00:00:00', $fecha . ' 23:59:59'])
            ->selectRaw("COALESCE(SUM(vi.cantidad_base * {$costoSql}), 0) as c")->value('c');

        return [
            'gastos_dia'   => round($gastosDia, 2),
            'ventas_dia'   => round($ventasDia, 2),
            'costo_dia'    => round($costoDia, 2),
            'utilidad_dia' => round($ventasDia - $costoDia - $gastosDia, 2),
        ];
    }

    /**
     * Regenera un balance a la estructura actual SIN cambiar su estado. Sirve
     * para poner al día balances YA confirmados tras un cambio de modelo, sin
     * reabrirlos uno por uno (comando balance:regenerar). El patrimonio
     * (balance_neto) no cambia; solo se limpian las líneas y se completan
     * ventas/costo/utilidad del día.
     */
    public function regenerarForzado(BalanceDiario $balance): void
    {
        DB::transaction(fn () => $this->reconstruir($balance));
    }

    /**
     * Borra y recalcula las líneas automáticas (es_manual = false).
     */
    private function regenerarItemsAutomaticos(BalanceDiario $balance): void
    {
        $empresaId = $balance->empresa_id;

        $balance->items()->where('es_manual', false)->delete();

        // Legado F7: antes las cuentas/efectivo eran líneas manuales; ahora
        // son automáticas desde tesorería. Purgar las viejas para no duplicar.
        $balance->items()->where('es_manual', true)
            ->whereIn('categoria', ['cuenta_bancaria', 'efectivo'])->delete();

        foreach ($this->calcularLineas($empresaId, $balance->fecha->toDateString()) as $item) {
            $balance->items()->create($item + ['es_manual' => false, 'conciliado' => false]);
        }
    }

    /**
     * Las líneas automáticas del balance de una fecha, calculadas con los datos
     * de HOY. Solo LEE, no escribe nada: la regeneración las guarda, y la
     * verificación de días cerrados las compara contra lo que quedó grabado al
     * confirmar para saber qué cambió después del cierre.
     */
    public function calcularLineas(int $empresaId, string $fechaCorte): array
    {
        $orden = 0;
        $items = [];

        // ── A FAVOR ──────────────────────────────────────────────────────

        // F11 — Efectivo y bancos al SALDO REAL NETO (ingresos − egresos),
        // AGRUPADO POR ENTIDAD. Antes se mostraba el ingreso BRUTO a favor y las
        // salidas como "gastos emitidos" en contra: inflaba ambos lados y, al
        // pagar proveedores, parecía pérdida. Ahora una línea por entidad con lo
        // que REALMENTE hay. Agrupar por entidad resuelve el caso de dos cuentas
        // del mismo banco (BCP Soles + Yape): una puede salir negativa y otra
        // positiva, pero la entidad muestra el saldo único real (como el banco).
        Cuenta::deEmpresa($empresaId)->activo()
            ->orderByDesc('es_efectivo')->orderBy('nombre')->get()
            ->groupBy(fn (Cuenta $c) => $c->es_efectivo ? 'efectivo' : ('banco:' . ($c->banco ?: $c->nombre)))
            ->each(function ($grupo, $clave) use (&$items, &$orden, $fechaCorte) {
                $esEfectivo = str_starts_with((string) $clave, 'efectivo');
                $saldo = round($grupo->sum(fn (Cuenta $c) => $this->tesoreria->saldo($c->id, $fechaCorte)), 2);
                $entidad = $esEfectivo ? 'Efectivo' : substr((string) $clave, 6); // quita "banco:"
                $items[] = [
                    'seccion'     => 'favor',
                    'categoria'   => $esEfectivo ? 'efectivo' : 'cuenta_bancaria',
                    'descripcion' => $entidad,
                    'ref_tipo'    => 'entidad', 'ref_id' => null,
                    'monto'       => $saldo,
                    'orden'       => ++$orden,
                ];
            });

        // Stock valorizado A LA FECHA DEL BALANCE (no el actual): si el balance
        // del sábado se cuadra el domingo, la línea de stock debe ser la del
        // sábado — se reconstruye desde el stock actual revirtiendo los
        // movimientos posteriores al corte (ventas/entradas/salidas/etc.).
        // La línea es la SUMA de su desglose por producto (auditoría de punta a punta).
        $items[] = [
            'seccion' => 'favor', 'categoria' => 'stock',
            'descripcion' => 'Stock (inventario valorizado)',
            'monto' => $this->sumaDesglose($this->desgloseStock($empresaId, $fechaCorte)), 'orden' => ++$orden,
        ];

        // Deudas por cobrar A LA FECHA: ventas a crédito nacidas hasta el corte,
        // con el saldo QUE TENÍAN ese día (los abonos posteriores se devuelven:
        // un abono del 12 no puede borrar la deuda del balance del 11).
        $items[] = [
            'seccion' => 'favor', 'categoria' => 'cxc',
            'descripcion' => 'Deudas por cobrar (ventas a crédito)',
            'monto' => $this->sumaDesglose($this->desgloseCxc($empresaId, $fechaCorte)), 'orden' => ++$orden,
        ];

        // Ajuste por deuda: pagos POSTERIORES al corte (amortización devuelve
        // saldo; incremento lo resta). Sirve para ambas direcciones.
        $ajusteDeudaPost = DB::table('deuda_pagos as dp')
            ->join('deudas as d', 'd.id', '=', 'dp.deuda_id')
            ->where('d.empresa_id', $empresaId)
            // DeudaPago usa BORRADO LÓGICO y esta consulta es cruda (DB::table),
            // así que no hereda el scope de Eloquent: sin este filtro, un
            // movimiento eliminado seguía ajustando el saldo al corte e inflaba
            // la línea de la deuda en TODOS los balances anteriores a él.
            ->whereNull('dp.deleted_at')
            ->where('dp.fecha', '>', $fechaCorte)
            ->selectRaw("dp.deuda_id, SUM(CASE WHEN dp.tipo = 'amortizacion' THEN dp.monto ELSE -dp.monto END) as ajuste")
            ->groupBy('dp.deuda_id')
            ->pluck('ajuste', 'deuda_id');
        $saldoDeudaAlCorte = fn (Deuda $d) => round((float) $d->saldo + (float) ($ajusteDeudaPost[$d->id] ?? 0), 2);

        // Préstamos otorgados a terceros (una línea por deuda, como el Excel).
        // Se incluyen las pagadas DESPUÉS del corte (ese día aún tenían saldo).
        Deuda::deEmpresa($empresaId)->porCobrar()
            ->whereIn('estado', ['activa', 'pagada'])
            ->where(fn ($q) => $q->whereDate('fecha_inicio', '<=', $fechaCorte)->orWhereNull('fecha_inicio'))
            ->orderBy('nombre')->get()
            ->each(function (Deuda $d) use (&$items, &$orden, $saldoDeudaAlCorte) {
                $saldo = $saldoDeudaAlCorte($d);
                if ($saldo <= 0.01) return;
                $items[] = [
                    'seccion' => 'favor', 'categoria' => 'prestamo_otorgado',
                    'descripcion' => $d->nombre,
                    'ref_tipo' => 'deuda', 'ref_id' => $d->id,
                    'monto' => $saldo, 'orden' => ++$orden,
                ];
            });

        // Adelantos a proveedores al corte: consumos posteriores se devuelven.
        $consumoAdelantoPost = DB::table('proveedor_adelanto_aplicaciones as pa')
            ->join('proveedor_adelantos as p', 'p.id', '=', 'pa.proveedor_adelanto_id')
            ->where('p.empresa_id', $empresaId)
            ->where('pa.fecha', '>', $fechaCorte)
            ->selectRaw('pa.proveedor_adelanto_id as aid, SUM(pa.monto) as t')
            ->groupBy('pa.proveedor_adelanto_id')
            ->pluck('t', 'aid');

        // 'devuelto' es un evento REAL posterior (el proveedor devolvió la plata), no
        // un registro erróneo como 'anulado': hasta el día de la devolución el
        // adelanto existía y era un activo. Excluirlo siempre lo borraba de TODOS
        // los balances pasados (FERRONOR, −33,963 del 02 al 05/09).
        $devueltoAntes = $this->devueltoHasta($empresaId, 'proveedor_adelanto_devolucion', 'adelanto_proveedor.devuelto');

        ProveedorAdelanto::deEmpresa($empresaId)
            ->whereIn('estado', ['activo', 'aplicado', 'devuelto'])
            ->whereDate('fecha', '<=', $fechaCorte)
            ->with('proveedor')->get()
            ->reject(fn (ProveedorAdelanto $a) => $a->estado === 'devuelto' && $devueltoAntes($a, $fechaCorte))
            ->each(function (ProveedorAdelanto $a) use (&$items, &$orden, $consumoAdelantoPost) {
                $saldo = round((float) $a->saldo + (float) ($consumoAdelantoPost[$a->id] ?? 0), 2);
                if ($saldo <= 0.01) return;
                $prov = $a->proveedor?->razon_social ?? $a->proveedor?->nombre_comercial ?? 'Proveedor';
                $items[] = [
                    'seccion' => 'favor', 'categoria' => 'adelanto_proveedor',
                    'descripcion' => "Adelanto a {$prov}",
                    'ref_tipo' => 'proveedor_adelanto', 'ref_id' => $a->id,
                    'monto' => $saldo, 'orden' => ++$orden,
                ];
            });

        // Descuentos de planilla PENDIENTES A LA FECHA: registrados hasta el
        // corte y aún sin aplicar ese día (los aplicados después cuentan).
        $items[] = [
            'seccion' => 'favor', 'categoria' => 'planilla_descuento',
            'descripcion' => 'Por descontar en planilla (faltantes y cargos)',
            'monto' => $this->sumaDesglose($this->desglosePlanilla($empresaId, $fechaCorte)), 'orden' => ++$orden,
        ];

        // ── EN CONTRA ────────────────────────────────────────────────────
        $orden = 0;

        // Proveedores por pagar A LA FECHA: compras hasta el corte con el saldo
        // que tenían ese día (pagos posteriores se devuelven — pagar el 12 no
        // borra la deuda del balance del 11).
        $items[] = [
            'seccion' => 'contra', 'categoria' => 'cxp',
            'descripcion' => 'Proveedores por pagar',
            'monto' => $this->sumaDesglose($this->desgloseCxp($empresaId, $fechaCorte)), 'orden' => ++$orden,
        ];

        // NOTA (F11): la línea "Gastos emitidos" (egresos brutos por cuenta) se
        // eliminó. Antes era la contraparte del efectivo/banco en BRUTO; ahora
        // que A FAVOR muestra el saldo NETO real, sumar los egresos aquí sería
        // contarlos dos veces y hacer aparecer el patrimonio como pérdida. El
        // detalle de salidas de cada cuenta se ve en el desplegable de la
        // entidad y en "movimientos del día". EN CONTRA = solo lo que DEBEMOS.

        // Anticipos de clientes A LA FECHA: nacidos hasta el corte, con el
        // saldo/pendiente que tenían ese día (las entregas posteriores se
        // devuelven). Material clásico se valoriza a precio del día; los
        // "pendiente por entregar" del POS valen su saldo pagado congelado.
        $items[] = [
            'seccion' => 'contra', 'categoria' => 'anticipo_cliente',
            'descripcion' => 'Clientes anticipos (al precio pagado)',
            'monto' => $this->sumaDesglose($this->desgloseAnticipos($empresaId, $fechaCorte)), 'orden' => ++$orden,
        ];

        // Deudas por pagar A LA FECHA: bancarias, personales, al personal
        // (línea por deuda; cuotas pagadas después del corte se devuelven).
        Deuda::deEmpresa($empresaId)->porPagar()
            ->whereIn('estado', ['activa', 'pagada'])
            ->where(fn ($q) => $q->whereDate('fecha_inicio', '<=', $fechaCorte)->orWhereNull('fecha_inicio'))
            ->orderBy('tipo')->orderBy('nombre')->get()
            ->each(function (Deuda $d) use (&$items, &$orden, $saldoDeudaAlCorte) {
                $saldo = $saldoDeudaAlCorte($d);
                if ($saldo <= 0.01) return;
                $items[] = [
                    'seccion' => 'contra',
                    'categoria' => $d->tipo === 'trabajador' ? 'personal' : 'deuda',
                    'descripcion' => $d->nombre,
                    'ref_tipo' => 'deuda', 'ref_id' => $d->id,
                    'monto' => $saldo, 'orden' => ++$orden,
                ];
            });

        return $items;
    }

    // ── DESGLOSE: de qué se compone cada línea ─────────────────────────────
    //
    // Cada línea del balance es la SUMA de su desglose (calcularLineas usa estos
    // métodos), así el modal de auditoría de una línea siempre suma exactamente lo
    // mismo que la línea. Cada entidad: clave estable => [descripcion, monto, detalle].

    /**
     * Desglose de una categoría del balance a una fecha. Solo lee.
     *
     * @return array<string, array{descripcion: string, monto: float, detalle: ?string}>
     */
    public function desglose(int $empresaId, string $fechaCorte, string $categoria): array
    {
        return match ($categoria) {
            'stock'              => $this->desgloseStock($empresaId, $fechaCorte),
            'cxc'                => $this->desgloseCxc($empresaId, $fechaCorte),
            'cxp'                => $this->desgloseCxp($empresaId, $fechaCorte),
            'anticipo_cliente'   => $this->desgloseAnticipos($empresaId, $fechaCorte),
            'planilla_descuento' => $this->desglosePlanilla($empresaId, $fechaCorte),
            'efectivo'           => $this->desgloseCuentas($empresaId, $fechaCorte, true),
            'cuenta_bancaria'    => $this->desgloseCuentas($empresaId, $fechaCorte, false),
            // Deudas, préstamos y adelantos ya son una línea por registro.
            default => collect($this->calcularLineas($empresaId, $fechaCorte))
                ->where('categoria', $categoria)
                ->mapWithKeys(fn ($l) => [($l['ref_id'] ?? $l['descripcion']) . '' => [
                    'descripcion' => $l['descripcion'], 'monto' => (float) $l['monto'], 'detalle' => null,
                ]])->all(),
        };
    }

    private function sumaDesglose(array $desglose): float
    {
        return round(array_sum(array_column($desglose, 'monto')), 2);
    }

    /** Por producto: saldo a la fecha × costo conocido a esa fecha (kardex). */
    public function desgloseStock(int $empresaId, string $fechaCorte): array
    {
        if (!$this->tieneKardex($empresaId, $fechaCorte)) {
            // Sin kardex no hay historia por producto: una sola partida con el total.
            return ['inventario' => [
                'descripcion' => 'Inventario (sin kardex a esta fecha)',
                'monto'       => $this->stockValorizadoA($empresaId, $fechaCorte),
                'detalle'     => null,
            ]];
        }

        $corte = $fechaCorte . ' 23:59:59';
        $filas = DB::select(
            'WITH saldos AS (
                SELECT DISTINCT ON (mi.almacen_id, mi.producto_id) mi.producto_id, mi.saldo_cantidad
                FROM movimientos_inventario mi
                WHERE mi.empresa_id = ? AND mi.fecha <= ?
                ORDER BY mi.almacen_id, mi.producto_id, mi.fecha DESC, mi.id DESC
            ),
            compra_dia AS (
                SELECT DISTINCT ON (mi.producto_id) mi.producto_id, mi.costo_unitario
                FROM movimientos_inventario mi
                WHERE mi.empresa_id = ? AND mi.fecha <= ?
                  AND mi.tipo IN (\'entrada\', \'transferencia_recepcion\') AND mi.costo_unitario > 0
                ORDER BY mi.producto_id, mi.fecha DESC, mi.id DESC
            ),
            cpp AS (
                SELECT DISTINCT ON (mi.producto_id) mi.producto_id, mi.costo_promedio
                FROM movimientos_inventario mi
                WHERE mi.empresa_id = ? AND mi.fecha <= ?
                ORDER BY mi.producto_id, mi.fecha DESC, mi.id DESC
            )
            SELECT s.producto_id, p.nombre,
                   SUM(s.saldo_cantidad) AS cantidad,
                   MAX(COALESCE(cd.costo_unitario, NULLIF(p.precio_costo, 0), cp.costo_promedio, 0)) AS costo,
                   SUM(GREATEST(s.saldo_cantidad, 0) * COALESCE(cd.costo_unitario, NULLIF(p.precio_costo, 0), cp.costo_promedio, 0)) AS valor
            FROM saldos s
            LEFT JOIN compra_dia cd ON cd.producto_id = s.producto_id
            LEFT JOIN cpp cp ON cp.producto_id = s.producto_id
            JOIN productos p ON p.id = s.producto_id AND p.activo = true
            GROUP BY s.producto_id, p.nombre',
            [$empresaId, $corte, $empresaId, $corte, $empresaId, $corte],
        );

        $out = [];
        foreach ($filas as $f) {
            if (abs((float) $f->cantidad) < 0.00005 && abs((float) $f->valor) < 0.005) continue;
            $out['p' . $f->producto_id] = [
                'descripcion' => $f->nombre,
                'monto'       => (float) $f->valor,
                'detalle'     => $this->cant((float) $f->cantidad) . ' und × S/ ' . number_format((float) $f->costo, 2)
                    . ((float) $f->cantidad < 0 ? ' (negativo: no suma)' : ''),
            ];
        }

        return $out;
    }

    /** Por venta a crédito: saldo que tenía al corte (abonos posteriores se devuelven). */
    public function desgloseCxc(int $empresaId, string $fechaCorte): array
    {
        $corte = $fechaCorte . ' 23:59:59';
        $ventas = DB::table('ventas as v')->leftJoin('clientes as c', 'c.id', '=', 'v.cliente_id')
            ->where('v.empresa_id', $empresaId)->where('v.es_credito', true)->where('v.estado', 'completada')
            ->where('v.fecha_venta', '<=', $corte)
            ->get(['v.id', 'v.numero', 'v.fecha_venta', 'v.saldo_pendiente', 'c.razon_social', 'c.nombres', 'c.apellidos'])
            ->keyBy('id');

        $abonosPost = DB::table('venta_abonos as a')->join('ventas as v', 'v.id', '=', 'a.venta_id')
            ->where('v.empresa_id', $empresaId)->where('v.estado', 'completada')
            ->where('v.fecha_venta', '<=', $corte)->where('a.fecha', '>', $fechaCorte)
            ->selectRaw('a.venta_id, SUM(a.monto) as t')->groupBy('a.venta_id')->pluck('t', 'venta_id');

        // Abonos de ventas que no figuran como crédito (poco común): igual cuentan.
        $faltan = $abonosPost->keys()->diff($ventas->keys());
        if ($faltan->isNotEmpty()) {
            DB::table('ventas as v')->leftJoin('clientes as c', 'c.id', '=', 'v.cliente_id')->whereIn('v.id', $faltan)
                ->get(['v.id', 'v.numero', 'v.fecha_venta', DB::raw('0 as saldo_pendiente'), 'c.razon_social', 'c.nombres', 'c.apellidos'])
                ->each(fn ($v) => $ventas->put($v->id, $v));
        }

        $out = [];
        foreach ($ventas as $v) {
            $monto = (float) $v->saldo_pendiente + (float) ($abonosPost[$v->id] ?? 0);
            if (abs($monto) < 0.005) continue;
            $out['v' . $v->id] = [
                'descripcion' => trim(($v->numero ?? "#{$v->id}") . ' · ' . $this->nombreTercero($v)),
                'monto'       => $monto,
                'detalle'     => 'Venta del ' . substr((string) $v->fecha_venta, 0, 10),
            ];
        }

        return $out;
    }

    /** Por compra: saldo por pagar que tenía al corte (pagos posteriores se devuelven). */
    public function desgloseCxp(int $empresaId, string $fechaCorte): array
    {
        $entradas = DB::table('entradas as e')->leftJoin('proveedores as pr', 'pr.id', '=', 'e.proveedor_id')
            ->where('e.empresa_id', $empresaId)->where('e.estado', 'confirmado')->where('e.fecha', '<=', $fechaCorte)
            ->get(['e.id', 'e.numero_documento', 'e.correlativo', 'e.fecha', 'e.proveedor', 'e.total', 'e.monto_pagado',
                   'pr.razon_social', 'pr.nombre_comercial'])
            ->keyBy('id');

        $pagosPost = DB::table('entrada_pagos as ep')->join('entradas as e', 'e.id', '=', 'ep.entrada_id')
            ->where('e.empresa_id', $empresaId)->where('e.estado', 'confirmado')->where('e.fecha', '<=', $fechaCorte)
            ->where('ep.fecha', '>', $fechaCorte)
            ->selectRaw('ep.entrada_id, SUM(ep.monto) as t')->groupBy('ep.entrada_id')->pluck('t', 'entrada_id');

        $out = [];
        foreach ($entradas as $e) {
            $monto = max((float) $e->total - (float) $e->monto_pagado, 0) + (float) ($pagosPost[$e->id] ?? 0);
            if (abs($monto) < 0.005) continue;
            $prov = $e->razon_social ?: ($e->nombre_comercial ?: ($e->proveedor ?: 'Sin proveedor'));
            $out['e' . $e->id] = [
                'descripcion' => 'Compra ' . ($e->numero_documento ?: ($e->correlativo ?: "#{$e->id}")) . ' · ' . $prov,
                'monto'       => $monto,
                'detalle'     => 'Del ' . substr((string) $e->fecha, 0, 10) . ' · total S/ ' . number_format((float) $e->total, 2),
            ];
        }

        return $out;
    }

    /** Por anticipo: lo que se le debía al cliente al corte. */
    public function desgloseAnticipos(int $empresaId, string $fechaCorte): array
    {
        $aplPost = DB::table('cliente_anticipo_aplicaciones as ca')
            ->join('cliente_anticipos as c', 'c.id', '=', 'ca.cliente_anticipo_id')
            ->where('c.empresa_id', $empresaId)
            ->where('ca.fecha', '>', $fechaCorte)
            ->selectRaw('ca.cliente_anticipo_id as aid, SUM(ca.monto) as monto, SUM(COALESCE(ca.cantidad, 0)) as cantidad')
            ->groupBy('ca.cliente_anticipo_id')
            ->get()->keyBy('aid');

        // Devueltos: cuentan hasta el día de su devolución (evento real); anulados nunca.
        $devueltoAntes = $this->devueltoHasta($empresaId, 'cliente_anticipo_devolucion', 'anticipo_cliente.devuelto');

        $out = [];
        ClienteAnticipo::deEmpresa($empresaId)
            ->whereIn('estado', ['activo', 'aplicado', 'devuelto'])
            ->whereDate('fecha', '<=', $fechaCorte)
            ->with(['producto', 'items', 'cliente', 'venta:id,numero'])->get()
            ->reject(fn (ClienteAnticipo $a) => $a->estado === 'devuelto' && $devueltoAntes($a, $fechaCorte))
            ->each(function (ClienteAnticipo $a) use ($aplPost, &$out) {
                $post = $aplPost->get($a->id);

                if ($a->items->isNotEmpty()) {
                    // Pendiente del POS (multi-producto): saldo pagado al corte.
                    $monto   = round((float) $a->saldo + (float) ($post->monto ?? 0), 2);
                    $detalle = 'Pedido por entregar' . ($a->venta?->numero ? " (venta {$a->venta->numero})" : '');
                } elseif ($a->tipo_valorizacion === 'material' && $a->producto
                    && $a->cantidad_pendiente !== null && (float) $a->cantidad > 0) {
                    // Material: cantidad pendiente al corte × precio CONGELADO que pagó.
                    $cant    = (float) $a->cantidad_pendiente + (float) ($post->cantidad ?? 0);
                    $monto   = round($cant * ((float) $a->monto / (float) $a->cantidad), 2);
                    $detalle = "{$a->producto->nombre} × " . $this->cant($cant) . ' a S/ ' . number_format((float) $a->monto / (float) $a->cantidad, 2);
                } else {
                    // Dinero: saldo al corte.
                    $monto   = round((float) $a->saldo + (float) ($post->monto ?? 0), 2);
                    $detalle = 'Anticipo en dinero';
                }

                if (abs($monto) < 0.005) return;
                $out['a' . $a->id] = [
                    'descripcion' => $this->nombreTercero($a->cliente) . " · anticipo #{$a->id}",
                    'monto'       => $monto,
                    'detalle'     => $detalle . ' · del ' . $a->fecha->format('Y-m-d'),
                ];
            });

        return $out;
    }

    /** Por descuento de planilla pendiente al corte. */
    public function desglosePlanilla(int $empresaId, string $fechaCorte): array
    {
        $out = [];
        \App\Models\PlanillaDescuento::deEmpresa($empresaId)
            ->whereDate('fecha', '<=', $fechaCorte)
            ->where(fn ($q) => $q->where('estado', 'pendiente')
                ->orWhere(fn ($q2) => $q2->where('estado', 'aplicado')->whereDate('fecha_aplicacion', '>', $fechaCorte)))
            ->with('trabajador:id,name')->get()
            ->each(function ($d) use (&$out) {
                $out['pd' . $d->id] = [
                    'descripcion' => ($d->trabajador?->name ?? 'Trabajador') . ' · ' . $d->motivo,
                    'monto'       => (float) $d->monto,
                    'detalle'     => 'Del ' . $d->fecha->format('Y-m-d'),
                ];
            });

        return $out;
    }

    /** Por cuenta: saldo neto de cada cuenta de efectivo o de banco al corte. */
    public function desgloseCuentas(int $empresaId, string $fechaCorte, bool $efectivo): array
    {
        $out = [];
        Cuenta::deEmpresa($empresaId)->activo()->where('es_efectivo', $efectivo)->orderBy('nombre')->get()
            ->each(function (Cuenta $c) use ($fechaCorte, &$out) {
                $saldo = $this->tesoreria->saldo($c->id, $fechaCorte);
                $out['c' . $c->id] = [
                    'descripcion' => $c->nombre,
                    'monto'       => $saldo,
                    'detalle'     => $c->banco ?: null,
                ];
            });

        return $out;
    }

    private function nombreTercero($t): string
    {
        if (!$t) return 'Sin cliente';
        $n = $t->razon_social ?? null;

        return $n ?: (trim(($t->nombres ?? '') . ' ' . ($t->apellidos ?? '')) ?: 'Sin nombre');
    }

    private function cant(float $c): string
    {
        return rtrim(rtrim(number_format($c, 4, '.', ','), '0'), '.');
    }

    /**
     * ¿Un adelanto/anticipo DEVUELTO ya estaba devuelto al cierre de una fecha?
     *
     * La devolución es un evento real con fecha propia: antes de esa fecha el
     * registro existía y debe contar en el balance. La fecha sale del movimiento
     * de tesorería de la devolución; si no movió caja, de la auditoría de la
     * acción; como último recurso, de la última modificación del registro.
     *
     * Devuelve un closure fn(Model $registro, string $fecha): bool. Lo usan la
     * línea del balance y su modal, para que siempre sumen lo mismo.
     */
    public function devueltoHasta(int $empresaId, string $refTipoTesoreria, string $accionAuditoria): \Closure
    {
        $tesoreria = DB::table('cuenta_movimientos')
            ->where('empresa_id', $empresaId)->where('ref_tipo', $refTipoTesoreria)
            ->selectRaw('ref_id, MAX(fecha) as f')->groupBy('ref_id')->pluck('f', 'ref_id');
        $auditoria = DB::table('auditoria')
            ->where('empresa_id', $empresaId)->where('accion', $accionAuditoria)
            ->selectRaw('modelo_id, MAX(created_at) as f')->groupBy('modelo_id')->pluck('f', 'modelo_id');

        return function ($registro, string $fecha) use ($tesoreria, $auditoria): bool {
            $devolucion = $tesoreria[$registro->id] ?? $auditoria[$registro->id] ?? $registro->updated_at;

            return $devolucion !== null && substr((string) $devolucion, 0, 10) <= $fecha;
        };
    }

    /**
     * ¿El kardex cubre esta fecha? Hay filas hasta el corte y el corte no es
     * anterior al inventario inicial (antes de la apertura el kardex solo tiene
     * fragmentos y daría un falso parcial).
     */
    private function tieneKardex(int $empresaId, string $fechaCorte): bool
    {
        $primeraApertura = DB::table('stock_iniciales')->where('empresa_id', $empresaId)->min('fecha');

        return ($primeraApertura === null || $fechaCorte >= substr((string) $primeraApertura, 0, 10))
            && DB::table('movimientos_inventario')
                ->where('empresa_id', $empresaId)
                ->where('fecha', '<=', $fechaCorte . ' 23:59:59')
                ->exists();
    }

    /**
     * Valor del inventario A UNA FECHA de corte (fin de ese día).
     *
     * El stock es una tabla viva (sin kardex), así que para fechas pasadas se
     * parte del stock ACTUAL y se revierten los movimientos POSTERIORES al
     * corte, valorizados con el mismo costo canónico (precio_costo del
     * producto, o costo_promedio real si está en 0):
     *
     *   valor(F) = valor_actual − entradas_post + ventas_post + salidas_post
     *              − devoluciones_post − ajustes_de_cierre_post
     *
     * Las ventas anuladas no cuentan (su stock ya se restauró) y las ventas
     * backdated caen en su fecha real. Si el corte es hoy o futuro, devuelve
     * el valor actual directo (camino rápido, comportamiento histórico).
     */
    private function stockValorizadoA(int $empresaId, string $fechaCorte): float
    {
        // ── Fuente preferida: KARDEX (movimientos_inventario) ───────────────
        // El kardex guarda, por (almacén, producto), el saldo y el costo
        // promedio VIGENTES en cada movimiento. El valor del inventario a una
        // fecha = última fila de cada producto hasta el fin de ese día. Es
        // históricamente ESTABLE: reabrir un balance no lo re-valoriza al costo
        // actual (antes: se reconstruía desde el stock de hoy al precio_costo de
        // hoy y el número "subía" con cada cambio de costo). Requiere kardex
        // poblado (kardex:reconstruir / botón Recalcular stock); sin él, cae al
        // método legado.
        // Solo si el kardex CUBRE esa fecha: (1) hay filas ≤ corte y (2) el corte
        // no es anterior al inventario inicial (antes de la apertura el kardex
        // solo tiene fragmentos — entradas migradas — y daría un falso parcial).
        if ($this->tieneKardex($empresaId, $fechaCorte)) {
            // CANTIDAD a la fecha: última fila del kardex de cada (almacén,
            // producto) hasta el fin del día (histórica, exacta).
            // COSTO "precio del día" CONOCIDO A ESA FECHA (misma base del Excel,
            // pero congelada): última COMPRA hasta la fecha; sin compras aún, el
            // precio_costo del producto; último recurso, el CPP histórico.
            // Compras posteriores ya no re-valorizan días pasados.
            $corte = $fechaCorte . ' 23:59:59';
            $v = DB::selectOne(
                'WITH saldos AS (
                    SELECT DISTINCT ON (mi.almacen_id, mi.producto_id) mi.producto_id, mi.saldo_cantidad
                    FROM movimientos_inventario mi
                    WHERE mi.empresa_id = ? AND mi.fecha <= ?
                    ORDER BY mi.almacen_id, mi.producto_id, mi.fecha DESC, mi.id DESC
                ),
                compra_dia AS (
                    SELECT DISTINCT ON (mi.producto_id) mi.producto_id, mi.costo_unitario
                    FROM movimientos_inventario mi
                    WHERE mi.empresa_id = ? AND mi.fecha <= ?
                      AND mi.tipo IN (\'entrada\', \'transferencia_recepcion\') AND mi.costo_unitario > 0
                    ORDER BY mi.producto_id, mi.fecha DESC, mi.id DESC
                ),
                cpp AS (
                    SELECT DISTINCT ON (mi.producto_id) mi.producto_id, mi.costo_promedio
                    FROM movimientos_inventario mi
                    WHERE mi.empresa_id = ? AND mi.fecha <= ?
                    ORDER BY mi.producto_id, mi.fecha DESC, mi.id DESC
                )
                -- GREATEST(...,0): el stock NEGATIVO es un error de registro
                -- (ventas antes de ingresar la compra), no inventario real; nunca
                -- debe restar valor fantasma al balance. Se avisa aparte para
                -- corregirlo (ver alerta de stock negativo en el controlador).
                SELECT COALESCE(SUM(GREATEST(s.saldo_cantidad, 0) * COALESCE(cd.costo_unitario, NULLIF(p.precio_costo, 0), cp.costo_promedio, 0)), 0) AS v
                FROM saldos s
                LEFT JOIN compra_dia cd ON cd.producto_id = s.producto_id
                LEFT JOIN cpp cp ON cp.producto_id = s.producto_id
                JOIN productos p ON p.id = s.producto_id AND p.activo = true',
                [$empresaId, $corte, $empresaId, $corte, $empresaId, $corte],
            );

            return round((float) $v->v, 2);
        }

        // ── Método LEGADO (sin kardex): reconstrucción al costo actual ──────
        $actual = (float) DB::table('stock')
            ->join('productos', 'productos.id', '=', 'stock.producto_id')
            ->where('productos.empresa_id', $empresaId)
            ->where('productos.activo', true)
            ->selectRaw('COALESCE(SUM(stock.cantidad * COALESCE(NULLIF(productos.precio_costo, 0), stock.costo_promedio)), 0) as v')
            ->value('v');

        if ($fechaCorte >= now()->toDateString()) {
            return round($actual, 2);
        }

        // Costo canónico por producto (mismo criterio que la línea de stock).
        $costo = "COALESCE(NULLIF(p.precio_costo, 0),
            (SELECT s2.costo_promedio FROM stock s2 WHERE s2.producto_id = p.id AND s2.costo_promedio > 0 ORDER BY s2.id LIMIT 1), 0)";

        // Ventas posteriores al corte (salieron DESPUÉS → se devuelven).
        $ventasPost = (float) DB::table('venta_items as vi')
            ->join('ventas as v', 'v.id', '=', 'vi.venta_id')
            ->join('productos as p', 'p.id', '=', 'vi.producto_id')
            ->where('v.empresa_id', $empresaId)->where('v.estado', 'completada')
            ->where('p.activo', true)
            ->whereDate('v.fecha_venta', '>', $fechaCorte)
            ->selectRaw("COALESCE(SUM(vi.cantidad_base * {$costo}), 0) as t")->value('t');

        // Salidas de inventario posteriores (se devuelven).
        $salidasPost = (float) DB::table('salidas_detalle as sd')
            ->join('salidas as s', 's.id', '=', 'sd.salida_id')
            ->join('productos as p', 'p.id', '=', 'sd.producto_id')
            ->where('s.empresa_id', $empresaId)->where('s.estado', 'confirmado')
            ->where('p.activo', true)
            ->whereDate('s.fecha', '>', $fechaCorte)
            ->selectRaw("COALESCE(SUM(sd.cantidad_base * {$costo}), 0) as t")->value('t');

        // Entradas (compras) posteriores (entraron DESPUÉS → se restan).
        $entradasPost = (float) DB::table('entradas_detalle as ed')
            ->join('entradas as e', 'e.id', '=', 'ed.entrada_id')
            ->join('productos as p', 'p.id', '=', 'ed.producto_id')
            ->where('e.empresa_id', $empresaId)->where('e.estado', 'confirmado')
            ->where('p.activo', true)
            ->whereDate('e.fecha', '>', $fechaCorte)
            ->selectRaw("COALESCE(SUM(ed.cantidad_base * {$costo}), 0) as t")->value('t');

        // Devoluciones con reingreso posteriores (entraron DESPUÉS → se restan).
        $devolucionesPost = (float) DB::table('devoluciones_detalle as dd')
            ->join('devoluciones as d', 'd.id', '=', 'dd.devolucion_id')
            ->join('productos as p', 'p.id', '=', 'dd.producto_id')
            ->where('d.empresa_id', $empresaId)->where('d.estado', 'completada')
            ->where('dd.restock', true)
            ->where('p.activo', true)
            ->whereDate('d.fecha', '>', $fechaCorte)
            ->selectRaw("COALESCE(SUM(dd.cantidad_base * {$costo}), 0) as t")->value('t');

        // Ajustes de cierre de inventario posteriores (diferencia ±, se restan).
        $ajustesPost = (float) DB::table('cierres_inventario_items as ci')
            ->join('cierres_inventario as c', 'c.id', '=', 'ci.cierre_id')
            ->join('productos as p', 'p.id', '=', 'ci.producto_id')
            ->where('c.empresa_id', $empresaId)->where('c.estado', 'confirmado')
            ->where('p.activo', true)
            ->whereDate('c.fecha', '>', $fechaCorte)
            ->selectRaw("COALESCE(SUM(ci.diferencia * {$costo}), 0) as t")->value('t');

        return round($actual + $ventasPost + $salidasPost - $entradasPost - $devolucionesPost - $ajustesPost, 2);
    }

    /**
     * Confirma el balance: valida borrador, congela totales y lo vuelve
     * inmutable (será el "BALANCE AYER" del día siguiente).
     */
    public function confirmar(BalanceDiario $balance, User $user): void
    {
        if (!$balance->esBorrador()) {
            abort(422, 'Este balance ya fue confirmado.');
        }

        $balance->recalcularTotales();
        $balance->update(['estado' => 'confirmado', 'user_id' => $user->id]);

        AuditoriaService::log('balance.confirmado', $balance, [
            'fecha'         => $balance->fecha->toDateString(),
            'balance_neto'  => (float) $balance->balance_neto,
            'utilidad_real' => $balance->utilidad_real !== null ? (float) $balance->utilidad_real : null,
        ], $user);
    }
}
