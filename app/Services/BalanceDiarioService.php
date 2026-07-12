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

            $balance->update([
                'balance_anterior' => $anterior?->balance_neto,
                'gastos_dia'       => round($gastosDia, 2),
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

        // F7/F10 — Efectivo y cuentas bancarias EN BRUTO (pedido del cliente):
        // A FAVOR muestra TODO lo ingresado a cada cuenta hasta la fecha;
        // las salidas van como línea "Gastos emitidos" EN CONTRA. El neto
        // (favor − contra) da el mismo saldo real, pero cada lado muestra
        // su monto completo, como en su Excel.
        $fechaCorte = $balance->fecha->toDateString();
        Cuenta::deEmpresa($empresaId)->activo()
            ->orderByDesc('es_efectivo')->orderBy('nombre')->get()
            ->each(function (Cuenta $c) use (&$items, &$orden, $fechaCorte) {
                $ingresosBrutos = (float) \App\Models\CuentaMovimiento::where('cuenta_id', $c->id)
                    ->where('fecha', '<=', $fechaCorte)
                    ->where('tipo', 'ingreso')
                    ->sum('monto');
                $items[] = [
                    'seccion'     => 'favor',
                    'categoria'   => $c->es_efectivo ? 'efectivo' : 'cuenta_bancaria',
                    'descripcion' => $c->nombre,
                    'ref_tipo'    => 'cuenta', 'ref_id' => $c->id,
                    'monto'       => round($ingresosBrutos, 2),
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

        // Deudas por cobrar: ventas a crédito con saldo.
        $cxc = (float) Venta::deEmpresa($empresaId)->conSaldoPendiente()->sum('saldo_pendiente');
        $items[] = [
            'seccion' => 'favor', 'categoria' => 'cxc',
            'descripcion' => 'Deudas por cobrar (ventas a crédito)',
            'monto' => round($cxc, 2), 'orden' => ++$orden,
        ];

        // Préstamos otorgados a terceros (una línea por deuda, como el Excel).
        Deuda::deEmpresa($empresaId)->porCobrar()->activa()->orderBy('nombre')->get()
            ->each(function (Deuda $d) use (&$items, &$orden) {
                $items[] = [
                    'seccion' => 'favor', 'categoria' => 'prestamo_otorgado',
                    'descripcion' => $d->nombre,
                    'ref_tipo' => 'deuda', 'ref_id' => $d->id,
                    'monto' => (float) $d->saldo, 'orden' => ++$orden,
                ];
            });

        // Adelantos a proveedores (una línea por adelanto activo).
        ProveedorAdelanto::deEmpresa($empresaId)->activo()->with('proveedor')->get()
            ->each(function (ProveedorAdelanto $a) use (&$items, &$orden) {
                $prov = $a->proveedor?->razon_social ?? $a->proveedor?->nombre_comercial ?? 'Proveedor';
                $items[] = [
                    'seccion' => 'favor', 'categoria' => 'adelanto_proveedor',
                    'descripcion' => "Adelanto a {$prov}",
                    'ref_tipo' => 'proveedor_adelanto', 'ref_id' => $a->id,
                    'monto' => (float) $a->saldo, 'orden' => ++$orden,
                ];
            });

        // Descuentos de planilla PENDIENTES: dinero por recuperar del personal
        // (faltantes de caja, cargos). Es un activo: cuando el faltante ocurrió
        // el egreso ya golpeó EN CONTRA (gastos emitidos); esta línea refleja
        // el derecho a descontarlo del sueldo. Al APLICARSE el descuento la
        // línea baja — ese es el movimiento del balance al aplicar.
        $planillaPendiente = (float) \App\Models\PlanillaDescuento::deEmpresa($empresaId)
            ->pendiente()->sum('monto');
        $items[] = [
            'seccion' => 'favor', 'categoria' => 'planilla_descuento',
            'descripcion' => 'Por descontar en planilla (faltantes y cargos)',
            'monto' => round($planillaPendiente, 2), 'orden' => ++$orden,
        ];

        // ── EN CONTRA ────────────────────────────────────────────────────
        $orden = 0;

        // Proveedores por pagar: entradas confirmadas con saldo.
        $cxp = (float) Entrada::deEmpresa($empresaId)
            ->confirmado()
            ->whereRaw('total - monto_pagado > 0.01')
            ->where('estado_pago', '!=', 'pagado')
            ->selectRaw('COALESCE(SUM(total - monto_pagado), 0) as v')
            ->value('v');

        $items[] = [
            'seccion' => 'contra', 'categoria' => 'cxp',
            'descripcion' => 'Proveedores por pagar',
            'monto' => round($cxp, 2), 'orden' => ++$orden,
        ];

        // F10 — Gastos emitidos POR CUENTA: todas las salidas de dinero hasta
        // la fecha (pagos a proveedores, gastos, cuotas, faltantes...),
        // desglosadas por cuenta/método (pedido del cliente: saber DE QUÉ
        // cuenta salió cada sol). Contraparte EN CONTRA de las cuentas en
        // bruto: favor(bruto) − gastos emitidos de la cuenta = saldo real.
        Cuenta::deEmpresa($empresaId)->activo()
            ->orderByDesc('es_efectivo')->orderBy('nombre')->get()
            ->each(function (Cuenta $c) use (&$items, &$orden, $fechaCorte) {
                $egresos = (float) \App\Models\CuentaMovimiento::where('cuenta_id', $c->id)
                    ->where('fecha', '<=', $fechaCorte)
                    ->where('tipo', 'egreso')
                    ->sum('monto');
                if ($egresos < 0.01) return; // sin salidas: no ensuciar EN CONTRA

                $items[] = [
                    'seccion' => 'contra', 'categoria' => 'gastos_emitidos',
                    'descripcion' => "Gastos emitidos — {$c->nombre}",
                    'ref_tipo' => 'cuenta', 'ref_id' => $c->id,
                    'monto' => round($egresos, 2), 'orden' => ++$orden,
                ];
            });

        // Anticipos de clientes valorizados A PRECIO DEL DÍA (incluye los
        // multi-producto "pendiente por entregar" creados desde el POS).
        $anticipos = ClienteAnticipo::deEmpresa($empresaId)->activo()->with(['producto', 'items.unidad'])->get()
            ->sum(fn (ClienteAnticipo $a) => $a->valorPasivoHoy());

        $items[] = [
            'seccion' => 'contra', 'categoria' => 'anticipo_cliente',
            'descripcion' => 'Clientes anticipos (a precio del día)',
            'monto' => round((float) $anticipos, 2), 'orden' => ++$orden,
        ];

        // Deudas por pagar: bancarias, personales, al personal (línea por deuda).
        Deuda::deEmpresa($empresaId)->porPagar()->activa()->orderBy('tipo')->orderBy('nombre')->get()
            ->each(function (Deuda $d) use (&$items, &$orden) {
                $items[] = [
                    'seccion' => 'contra',
                    'categoria' => $d->tipo === 'trabajador' ? 'personal' : 'deuda',
                    'descripcion' => $d->nombre,
                    'ref_tipo' => 'deuda', 'ref_id' => $d->id,
                    'monto' => (float) $d->saldo, 'orden' => ++$orden,
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
