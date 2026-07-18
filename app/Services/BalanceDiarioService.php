<?php

namespace App\Services;

use App\Models\BalanceDiario;
use App\Models\BalanceDiarioItem;
use App\Models\ClienteAnticipo;
use App\Models\Cuenta;
use App\Models\Deuda;
use App\Models\Entrada;
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

            // Balance anterior: último confirmado antes de esta fecha.
            $anterior = BalanceDiario::deEmpresa($empresaId)
                ->confirmado()
                ->where('fecha', '<', $fecha)
                ->orderByDesc('fecha')
                ->first();

            // Gastos del día (operativos, del Excel "GASTOS DEL DÍA").
            $gastosDia = (float) Gasto::deEmpresa($empresaId)
                ->where('fecha', $fecha)
                ->sum('monto');

            // ── UTILIDAD OPERATIVA del día: ventas − costo de lo vendido − gastos.
            // Es la ganancia REAL ("cuánto vendí, cuánto gané"). NO se usa el
            // Δpatrimonio como utilidad: pagar proveedores o comprar mercadería
            // baja el saldo pero NO es pérdida. El costo usa el snapshot congelado
            // del ítem (mismo criterio del Reporte de Utilidad).
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

            $utilidadDia = round($ventasDia - $costoDia - $gastosDia, 2);

            $balance->update([
                'balance_anterior' => $anterior?->balance_neto,
                'gastos_dia'       => round($gastosDia, 2),
                'ventas_dia'       => round($ventasDia, 2),
                'costo_dia'        => round($costoDia, 2),
                'utilidad_dia'     => $utilidadDia,
            ]);

            $this->regenerarItemsAutomaticos($balance);

            $balance->recalcularTotales();

            return $balance->fresh('items');
        });
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
        $fechaCorte = $balance->fecha->toDateString();
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
        $stockValorizado = $this->stockValorizadoA($empresaId, $fechaCorte);

        $items[] = [
            'seccion' => 'favor', 'categoria' => 'stock',
            'descripcion' => 'Stock (inventario valorizado)',
            'monto' => round($stockValorizado, 2), 'orden' => ++$orden,
        ];

        // Deudas por cobrar A LA FECHA: ventas a crédito nacidas hasta el corte,
        // con el saldo QUE TENÍAN ese día (los abonos posteriores se devuelven:
        // un abono del 12 no puede borrar la deuda del balance del 11).
        $cxcActual = (float) Venta::deEmpresa($empresaId)
            ->where('es_credito', true)->where('estado', 'completada')
            ->where('fecha_venta', '<=', $fechaCorte . ' 23:59:59')
            ->sum('saldo_pendiente');
        $abonosPost = (float) DB::table('venta_abonos as a')
            ->join('ventas as v', 'v.id', '=', 'a.venta_id')
            ->where('v.empresa_id', $empresaId)->where('v.estado', 'completada')
            ->where('v.fecha_venta', '<=', $fechaCorte . ' 23:59:59')
            ->where('a.fecha', '>', $fechaCorte)
            ->sum('a.monto');
        $cxc = $cxcActual + $abonosPost;
        $items[] = [
            'seccion' => 'favor', 'categoria' => 'cxc',
            'descripcion' => 'Deudas por cobrar (ventas a crédito)',
            'monto' => round($cxc, 2), 'orden' => ++$orden,
        ];

        // Ajuste por deuda: pagos POSTERIORES al corte (amortización devuelve
        // saldo; incremento lo resta). Sirve para ambas direcciones.
        $ajusteDeudaPost = DB::table('deuda_pagos as dp')
            ->join('deudas as d', 'd.id', '=', 'dp.deuda_id')
            ->where('d.empresa_id', $empresaId)
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

        ProveedorAdelanto::deEmpresa($empresaId)
            ->whereIn('estado', ['activo', 'aplicado'])
            ->whereDate('fecha', '<=', $fechaCorte)
            ->with('proveedor')->get()
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
        $planillaPendiente = (float) \App\Models\PlanillaDescuento::deEmpresa($empresaId)
            ->whereDate('fecha', '<=', $fechaCorte)
            ->where(fn ($q) => $q->where('estado', 'pendiente')
                ->orWhere(fn ($q2) => $q2->where('estado', 'aplicado')->whereDate('fecha_aplicacion', '>', $fechaCorte)))
            ->sum('monto');
        $items[] = [
            'seccion' => 'favor', 'categoria' => 'planilla_descuento',
            'descripcion' => 'Por descontar en planilla (faltantes y cargos)',
            'monto' => round($planillaPendiente, 2), 'orden' => ++$orden,
        ];

        // ── EN CONTRA ────────────────────────────────────────────────────
        $orden = 0;

        // Proveedores por pagar A LA FECHA: compras hasta el corte con el saldo
        // que tenían ese día (pagos posteriores se devuelven — pagar el 12 no
        // borra la deuda del balance del 11).
        $cxpActual = (float) Entrada::deEmpresa($empresaId)
            ->confirmado()
            ->whereDate('fecha', '<=', $fechaCorte)
            ->selectRaw('COALESCE(SUM(GREATEST(total - monto_pagado, 0)), 0) as v')
            ->value('v');
        $pagosCxpPost = (float) DB::table('entrada_pagos as ep')
            ->join('entradas as e', 'e.id', '=', 'ep.entrada_id')
            ->where('e.empresa_id', $empresaId)->where('e.estado', 'confirmado')
            ->whereDate('e.fecha', '<=', $fechaCorte)
            ->where('ep.fecha', '>', $fechaCorte)
            ->sum('ep.monto');
        $cxp = $cxpActual + $pagosCxpPost;

        $items[] = [
            'seccion' => 'contra', 'categoria' => 'cxp',
            'descripcion' => 'Proveedores por pagar',
            'monto' => round($cxp, 2), 'orden' => ++$orden,
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
        $aplAnticipoPost = DB::table('cliente_anticipo_aplicaciones as ca')
            ->join('cliente_anticipos as c', 'c.id', '=', 'ca.cliente_anticipo_id')
            ->where('c.empresa_id', $empresaId)
            ->where('ca.fecha', '>', $fechaCorte)
            ->selectRaw('ca.cliente_anticipo_id as aid, SUM(ca.monto) as monto, SUM(COALESCE(ca.cantidad, 0)) as cantidad')
            ->groupBy('ca.cliente_anticipo_id')
            ->get()->keyBy('aid');

        $anticipos = ClienteAnticipo::deEmpresa($empresaId)
            ->whereIn('estado', ['activo', 'aplicado'])
            ->whereDate('fecha', '<=', $fechaCorte)
            ->with(['producto', 'items'])->get()
            ->sum(function (ClienteAnticipo $a) use ($aplAnticipoPost) {
                $post = $aplAnticipoPost->get($a->id);

                // Pendiente del POS (multi-producto): saldo pagado al corte.
                if ($a->items->isNotEmpty()) {
                    return round((float) $a->saldo + (float) ($post->monto ?? 0), 2);
                }

                // Material clásico: cantidad pendiente al corte × precio del día.
                if ($a->tipo_valorizacion === 'material' && $a->producto && $a->cantidad_pendiente !== null) {
                    $cant = (float) $a->cantidad_pendiente + (float) ($post->cantidad ?? 0);
                    return round($cant * (float) $a->producto->precio_venta, 2);
                }

                // Dinero: saldo al corte.
                return round((float) $a->saldo + (float) ($post->monto ?? 0), 2);
            });

        $items[] = [
            'seccion' => 'contra', 'categoria' => 'anticipo_cliente',
            'descripcion' => 'Clientes anticipos (a precio del día)',
            'monto' => round((float) $anticipos, 2), 'orden' => ++$orden,
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

        foreach ($items as $item) {
            $balance->items()->create($item + ['es_manual' => false, 'conciliado' => false]);
        }
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
        $primeraApertura = DB::table('stock_iniciales')
            ->where('empresa_id', $empresaId)
            ->min('fecha');
        $tieneKardex = ($primeraApertura === null || $fechaCorte >= substr((string) $primeraApertura, 0, 10))
            && DB::table('movimientos_inventario')
                ->where('empresa_id', $empresaId)
                ->where('fecha', '<=', $fechaCorte . ' 23:59:59')
                ->exists();

        if ($tieneKardex) {
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
                SELECT COALESCE(SUM(s.saldo_cantidad * COALESCE(cd.costo_unitario, NULLIF(p.precio_costo, 0), cp.costo_promedio, 0)), 0) AS v
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
