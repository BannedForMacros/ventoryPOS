<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\TesoreriaService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * F7 — Reconstruye el libro de tesorería desde el histórico existente:
 * ventas (pagos), abonos, gastos, pagos a proveedores, anticipos,
 * adelantos, cuotas de deudas y devoluciones reembolsadas.
 *
 * Idempotente por diseño: BORRA todos los movimientos con origen (ref_tipo
 * distinto de 'ajuste') y los regenera. Los ajustes manuales se preservan.
 */
class TesoreriaBackfillSeeder extends Seeder
{
    public function run(): void
    {
        $tesoreria = app(TesoreriaService::class);

        DB::transaction(function () use ($tesoreria) {
            DB::table('cuenta_movimientos')->where('ref_tipo', '!=', 'ajuste')->delete();

            foreach (DB::table('empresas')->pluck('id') as $empresaId) {
                $sistema = User::where('empresa_id', $empresaId)
                    ->whereHas('rol', fn ($q) => $q->where('es_admin', true))
                    ->first() ?? User::where('empresa_id', $empresaId)->first();
                if (!$sistema) continue;

                // 1. Ventas completadas → ingresos por cada pago (neto de vuelto).
                $pagos = DB::table('venta_pagos')
                    ->join('ventas', 'ventas.id', '=', 'venta_pagos.venta_id')
                    ->where('ventas.empresa_id', $empresaId)
                    ->where('ventas.estado', 'completada')
                    ->select('venta_pagos.*', 'ventas.numero', 'ventas.fecha_venta', 'ventas.user_id as vendedor_id')
                    ->get();
                foreach ($pagos as $p) {
                    $neto = (float) $p->monto - (float) $p->vuelto;
                    $tesoreria->registrar(
                        $empresaId,
                        $tesoreria->resolverCuenta($empresaId, $p->cuenta_metodo_pago_id, $p->metodo_pago_id),
                        $p->vendedor_id ?? $sistema->id,
                        substr((string) $p->fecha_venta, 0, 10),
                        'ingreso', $neto,
                        "Venta {$p->numero}",
                        'venta', $p->venta_id,
                    );
                }

                // 2. Abonos de ventas a crédito.
                foreach (DB::table('venta_abonos')
                    ->join('ventas', 'ventas.id', '=', 'venta_abonos.venta_id')
                    ->where('ventas.empresa_id', $empresaId)
                    ->where('ventas.estado', 'completada')
                    ->select('venta_abonos.*', 'ventas.numero')->get() as $a) {
                    $tesoreria->registrar(
                        $empresaId,
                        $a->cuenta_id ?? $tesoreria->resolverCuenta($empresaId, null, $a->metodo_pago_id),
                        $a->user_id, (string) $a->fecha, 'ingreso', (float) $a->monto,
                        "Abono venta {$a->numero}", 'venta_abono', $a->id,
                    );
                }

                // 3. Gastos → egresos.
                foreach (DB::table('gastos')
                    ->leftJoin('gasto_conceptos', 'gasto_conceptos.id', '=', 'gastos.gasto_concepto_id')
                    ->where('gastos.empresa_id', $empresaId)
                    ->select('gastos.*', 'gasto_conceptos.nombre as concepto_nombre')->get() as $g) {
                    $tesoreria->registrar(
                        $empresaId, $g->cuenta_id, $g->user_id, (string) $g->fecha,
                        'egreso', (float) $g->monto,
                        'Gasto — ' . ($g->concepto_nombre ?? 'operativo'),
                        'gasto', $g->id,
                    );
                }

                // 4. Pagos a proveedores (entrada_pagos SIN adelanto: dinero real).
                foreach (DB::table('entrada_pagos')
                    ->join('entradas', 'entradas.id', '=', 'entrada_pagos.entrada_id')
                    ->where('entradas.empresa_id', $empresaId)
                    ->whereNull('entrada_pagos.proveedor_adelanto_id')
                    ->select('entrada_pagos.*', 'entradas.proveedor', 'entradas.numero_documento')->get() as $ep) {
                    $tesoreria->registrar(
                        $empresaId,
                        $ep->cuenta_id ?? $tesoreria->resolverCuenta($empresaId, null, $ep->metodo_pago_id),
                        $ep->user_id, (string) $ep->fecha, 'egreso', (float) $ep->monto,
                        'Pago a proveedor ' . ($ep->proveedor ?? ''),
                        'entrada_pago', $ep->id,
                    );
                }

                // 4b. Entradas marcadas 'pagado' con el flag antiguo (sin abonos
                // registrados): un egreso por el total en la fecha de la entrada.
                foreach (DB::table('entradas')
                    ->where('empresa_id', $empresaId)
                    ->where('estado_pago', 'pagado')
                    ->whereNotExists(fn ($q) => $q->select(DB::raw(1))
                        ->from('entrada_pagos')->whereColumn('entrada_pagos.entrada_id', 'entradas.id'))
                    ->get() as $e) {
                    $tesoreria->registrar(
                        $empresaId,
                        $e->cuenta_id ?? $tesoreria->resolverCuenta($empresaId, null, $e->metodo_pago_id),
                        $e->user_id, (string) $e->fecha, 'egreso', (float) $e->total,
                        'Pago a proveedor ' . ($e->proveedor ?? '') . ($e->numero_documento ? " ({$e->numero_documento})" : ''),
                        'entrada', $e->id,
                    );
                }

                // 5. Anticipos de clientes (no anulados) → ingreso; devueltos → egreso del saldo.
                foreach (DB::table('cliente_anticipos')->where('empresa_id', $empresaId)
                    ->where('estado', '!=', 'anulado')->get() as $an) {
                    $tesoreria->registrar(
                        $empresaId,
                        $an->cuenta_id ?? $tesoreria->resolverCuenta($empresaId, null, $an->metodo_pago_id),
                        $an->user_id, (string) $an->fecha, 'ingreso', (float) $an->monto,
                        "Anticipo de cliente #{$an->id}", 'cliente_anticipo', $an->id,
                    );
                    if ($an->estado === 'devuelto') {
                        $tesoreria->registrar(
                            $empresaId, $an->cuenta_id, $an->user_id, (string) $an->fecha,
                            'egreso', (float) $an->saldo,
                            "Devolución de anticipo #{$an->id}", 'cliente_anticipo_devolucion', $an->id,
                        );
                    }
                }

                // 6. Adelantos a proveedores (no anulados) → egreso; devueltos → ingreso del saldo.
                foreach (DB::table('proveedor_adelantos')->where('empresa_id', $empresaId)
                    ->where('estado', '!=', 'anulado')->get() as $ad) {
                    $tesoreria->registrar(
                        $empresaId,
                        $ad->cuenta_id ?? $tesoreria->resolverCuenta($empresaId, null, $ad->metodo_pago_id),
                        $ad->user_id, (string) $ad->fecha, 'egreso', (float) $ad->monto,
                        "Adelanto a proveedor #{$ad->id}", 'proveedor_adelanto', $ad->id,
                    );
                    if ($ad->estado === 'devuelto') {
                        $tesoreria->registrar(
                            $empresaId, $ad->cuenta_id, $ad->user_id, (string) $ad->fecha,
                            'ingreso', (float) $ad->saldo,
                            "Devolución de adelanto #{$ad->id}", 'proveedor_adelanto_devolucion', $ad->id,
                        );
                    }
                }

                // 7. Cuotas/incrementos de deudas.
                foreach (DB::table('deuda_pagos')
                    ->join('deudas', 'deudas.id', '=', 'deuda_pagos.deuda_id')
                    ->where('deudas.empresa_id', $empresaId)
                    ->where('deudas.estado', '!=', 'anulada')
                    ->select('deuda_pagos.*', 'deudas.direccion', 'deudas.nombre')->get() as $dp) {
                    $esIngreso = ($dp->direccion === 'por_pagar') === ($dp->tipo === 'incremento');
                    $tesoreria->registrar(
                        $empresaId,
                        $dp->cuenta_id ?? $tesoreria->resolverCuenta($empresaId, null, $dp->metodo_pago_id),
                        $dp->user_id, (string) $dp->fecha,
                        $esIngreso ? 'ingreso' : 'egreso', (float) $dp->monto,
                        ($dp->tipo === 'amortizacion' ? 'Cuota' : 'Incremento') . " de deuda — {$dp->nombre}",
                        'deuda_pago', $dp->id,
                    );
                }

                // 8. Devoluciones completadas con reembolso en dinero → egresos.
                foreach (DB::table('devolucion_pagos')
                    ->join('devoluciones', 'devoluciones.id', '=', 'devolucion_pagos.devolucion_id')
                    ->where('devoluciones.empresa_id', $empresaId)
                    ->whereIn('devoluciones.estado', ['completada'])
                    ->whereIn('devoluciones.forma_reembolso', ['efectivo', 'mismo_metodo'])
                    ->select('devolucion_pagos.*', 'devoluciones.numero', 'devoluciones.fecha as dev_fecha', 'devoluciones.user_id as dev_user')->get() as $vp) {
                    $tesoreria->registrar(
                        $empresaId,
                        $tesoreria->resolverCuenta($empresaId, $vp->cuenta_metodo_pago_id, $vp->metodo_pago_id),
                        $vp->dev_user, substr((string) $vp->dev_fecha, 0, 10),
                        'egreso', (float) $vp->monto,
                        "Reembolso devolución {$vp->numero}", 'devolucion', $vp->devolucion_id,
                    );
                }
            }
        });

        $this->command?->info('Tesorería reconstruida: ' . DB::table('cuenta_movimientos')->count() . ' movimientos.');
    }
}
