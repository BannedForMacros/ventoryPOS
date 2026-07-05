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

        // F7 — Efectivo y cuentas bancarias: saldo AUTOMÁTICO desde el libro
        // de tesorería (ingresos − egresos hasta la fecha del balance). Ya no
        // se digitan a mano; si el dinero físico no cuadra, se registra un
        // ajuste con motivo en Tesorería y aquí se refleja.
        $fechaCorte = $balance->fecha->toDateString();
        Cuenta::deEmpresa($empresaId)->activo()
            ->orderByDesc('es_efectivo')->orderBy('nombre')->get()
            ->each(function (Cuenta $c) use (&$items, &$orden, $fechaCorte) {
                $items[] = [
                    'seccion'     => 'favor',
                    'categoria'   => $c->es_efectivo ? 'efectivo' : 'cuenta_bancaria',
                    'descripcion' => $c->nombre,
                    'ref_tipo'    => 'cuenta', 'ref_id' => $c->id,
                    'monto'       => $this->tesoreria->saldo($c->id, $fechaCorte),
                    'orden'       => ++$orden,
                ];
            });

        // Stock valorizado a costo del día: cantidad × precio_costo ACTUAL
        // del producto (el "punitario actual" del Excel de ladrillos).
        $stockValorizado = (float) DB::table('stock')
            ->join('productos', 'productos.id', '=', 'stock.producto_id')
            ->where('productos.empresa_id', $empresaId)
            ->where('productos.activo', true)
            ->selectRaw('COALESCE(SUM(stock.cantidad * productos.precio_costo), 0) as v')
            ->value('v');

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

        // Anticipos de clientes valorizados A PRECIO DEL DÍA.
        $anticipos = ClienteAnticipo::deEmpresa($empresaId)->activo()->with('producto')->get()
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
