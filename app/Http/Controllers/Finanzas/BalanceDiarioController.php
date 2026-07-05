<?php

namespace App\Http\Controllers\Finanzas;

use App\Http\Controllers\Controller;
use App\Models\BalanceDiario;
use App\Models\BalanceDiarioItem;
use App\Models\ClienteAnticipo;
use App\Models\CuentaMovimiento;
use App\Models\Deuda;
use App\Models\Entrada;
use App\Models\Gasto;
use App\Models\ProveedorAdelanto;
use App\Models\Venta;
use App\Services\BalanceDiarioService;
use App\Services\TesoreriaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

/**
 * Balance diario: la foto patrimonial que el dueño arma cada día
 * (réplica del Excel "BALANCE FERRETERIA H&C").
 */
class BalanceDiarioController extends Controller
{
    public function __construct(private BalanceDiarioService $service) {}

    /**
     * Histórico de balances + acceso al día seleccionado.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $balances = BalanceDiario::deEmpresa($user->empresa_id)
            ->with('user')
            ->orderByDesc('fecha')
            ->paginate(30);

        return Inertia::render('Finanzas/BalanceDiario', [
            'balances' => $balances,
            'hoy'      => now()->toDateString(),
        ]);
    }

    /**
     * Genera (o regenera las líneas automáticas de) el balance de una fecha
     * y lo muestra para edición/conciliación.
     */
    public function show(Request $request, string $fecha)
    {
        $user = $request->user();

        abort_unless(preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha), 404);

        $balance = BalanceDiario::deEmpresa($user->empresa_id)->where('fecha', $fecha)->first();

        // Borrador (o inexistente): regenerar líneas automáticas al momento,
        // así siempre refleja el estado real de CxC/CxP/stock/deudas.
        if (!$balance || $balance->esBorrador()) {
            $balance = $this->service->generar($user, $fecha);
        }

        $balance->load(['items', 'user']);

        // Detalle de gastos del día para el panel lateral (como su Excel).
        $gastos = Gasto::deEmpresa($user->empresa_id)
            ->where('fecha', $fecha)
            ->with(['tipo', 'concepto'])
            ->orderBy('id')
            ->get();

        return Inertia::render('Finanzas/BalanceDiarioDetalle', [
            'balance' => $balance,
            'gastos'  => $gastos,
        ]);
    }

    /**
     * F9 — Detalle de una línea del balance (JSON para el modal).
     * Cada monto del balance debe poder explicarse: de dónde sale, sol por sol.
     */
    public function detalleItem(Request $request, string $fecha, string $categoria)
    {
        $user = $request->user();
        abort_unless(preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha), 404);

        $empresaId = $user->empresa_id;
        $refId     = $request->integer('ref_id') ?: null;

        switch ($categoria) {
            // ── Efectivo / cuenta bancaria: movimientos de tesorería ────
            case 'efectivo':
            case 'cuenta_bancaria': {
                $cuentaId = $refId ?? TesoreriaService::efectivo($empresaId)->id;
                // Rango por defecto: la última semana hasta la fecha del balance.
                $hasta = min($request->input('hasta', $fecha), $fecha);
                $desde = $request->input('desde', date('Y-m-d', strtotime($hasta . ' -6 days')));

                $movimientos = CuentaMovimiento::deEmpresa($empresaId)
                    ->where('cuenta_id', $cuentaId)
                    ->whereBetween('fecha', [$desde, $hasta])
                    ->with('user:id,name')
                    ->orderByDesc('fecha')->orderByDesc('id')
                    ->limit(300)
                    ->get(['id', 'fecha', 'tipo', 'monto', 'descripcion', 'ref_tipo', 'user_id']);

                // Resumen por día (responde "¿de dónde salió el efectivo de tal día?")
                $porDia = CuentaMovimiento::deEmpresa($empresaId)
                    ->where('cuenta_id', $cuentaId)
                    ->whereBetween('fecha', [$desde, $hasta])
                    ->selectRaw("fecha,
                        SUM(CASE WHEN tipo = 'ingreso' THEN monto ELSE 0 END) as ingresos,
                        SUM(CASE WHEN tipo = 'egreso'  THEN monto ELSE 0 END) as egresos")
                    ->groupBy('fecha')->orderByDesc('fecha')->get();

                return response()->json([
                    'tipo'        => 'movimientos',
                    'saldo'       => app(TesoreriaService::class)->saldo($cuentaId, $fecha),
                    'desde'       => $desde,
                    'hasta'       => $hasta,
                    'porDia'      => $porDia,
                    'movimientos' => $movimientos,
                ]);
            }

            // ── Stock valorizado: producto por producto ─────────────────
            case 'stock': {
                $filas = DB::table('stock')
                    ->join('productos', 'productos.id', '=', 'stock.producto_id')
                    ->where('productos.empresa_id', $empresaId)
                    ->where('productos.activo', true)
                    ->where('stock.cantidad', '!=', 0)
                    ->selectRaw('productos.nombre, SUM(stock.cantidad) as cantidad, productos.precio_costo,
                                 SUM(stock.cantidad * productos.precio_costo) as valor')
                    ->groupBy('productos.id', 'productos.nombre', 'productos.precio_costo')
                    ->orderByDesc('valor')
                    ->limit(300)
                    ->get();

                return response()->json([
                    'tipo'  => 'stock',
                    'total' => round((float) $filas->sum('valor'), 2),
                    'filas' => $filas,
                ]);
            }

            // ── Deudas por cobrar: venta a venta ────────────────────────
            case 'cxc': {
                $filas = Venta::deEmpresa($empresaId)->conSaldoPendiente()
                    ->with('cliente:id,nombres,apellidos,razon_social')
                    ->orderByDesc('saldo_pendiente')
                    ->limit(300)
                    ->get(['id', 'numero', 'fecha_venta', 'fecha_vencimiento', 'cliente_id', 'total', 'monto_pagado', 'saldo_pendiente'])
                    ->map(fn ($v) => [
                        'fecha'   => $v->fecha_venta?->format('Y-m-d'),
                        'numero'  => $v->numero,
                        'cliente' => $v->cliente?->razon_social ?? trim(($v->cliente?->nombres ?? '') . ' ' . ($v->cliente?->apellidos ?? '')),
                        'total'   => (float) $v->total,
                        'pagado'  => (float) $v->monto_pagado,
                        'saldo'   => (float) $v->saldo_pendiente,
                        'vence'   => $v->fecha_vencimiento?->format('Y-m-d'),
                    ]);

                return response()->json(['tipo' => 'cxc', 'total' => round((float) $filas->sum('saldo'), 2), 'filas' => $filas]);
            }

            // ── Proveedores por pagar: entrada por entrada ──────────────
            case 'cxp': {
                $filas = Entrada::deEmpresa($empresaId)->confirmado()
                    ->where('estado_pago', '!=', 'pagado')
                    ->whereRaw('total - monto_pagado > 0.01')
                    ->with('proveedorRel:id,razon_social,nombre_comercial')
                    ->orderByRaw('total - monto_pagado desc')
                    ->limit(300)
                    ->get()
                    ->map(fn ($e) => [
                        'fecha'     => $e->fecha?->format('Y-m-d'),
                        'documento' => $e->numero_documento,
                        'proveedor' => $e->proveedorRel?->razon_social ?? $e->proveedorRel?->nombre_comercial ?? $e->proveedor,
                        'total'     => (float) $e->total,
                        'pagado'    => (float) $e->monto_pagado,
                        'saldo'     => $e->saldoPendiente(),
                    ]);

                return response()->json(['tipo' => 'cxp', 'total' => round((float) $filas->sum('saldo'), 2), 'filas' => $filas]);
            }

            // ── Anticipos de clientes: valorizado a precio del día ──────
            case 'anticipo_cliente': {
                $filas = ClienteAnticipo::deEmpresa($empresaId)->activo()
                    ->with(['cliente:id,nombres,apellidos,razon_social', 'producto:id,nombre,precio_venta'])
                    ->orderByDesc('fecha')
                    ->limit(300)
                    ->get()
                    ->map(fn ($a) => [
                        'fecha'     => $a->fecha?->format('Y-m-d'),
                        'cliente'   => $a->cliente?->razon_social ?? trim(($a->cliente?->nombres ?? '') . ' ' . ($a->cliente?->apellidos ?? '')),
                        'modalidad' => $a->tipo_valorizacion === 'material'
                            ? "{$a->producto?->nombre} × " . (float) $a->cantidad_pendiente . " a S/" . (float) ($a->producto?->precio_venta ?? 0)
                            : 'Dinero',
                        'recibido'  => (float) $a->monto,
                        'valor_hoy' => $a->valorPasivoHoy(),
                    ]);

                return response()->json(['tipo' => 'anticipos', 'total' => round((float) $filas->sum('valor_hoy'), 2), 'filas' => $filas]);
            }

            // ── Adelanto a proveedor puntual: sus aplicaciones ──────────
            case 'adelanto_proveedor': {
                $adelanto = ProveedorAdelanto::deEmpresa($empresaId)
                    ->with(['proveedor:id,razon_social,nombre_comercial', 'aplicaciones.entrada:id,numero_documento', 'aplicaciones.user:id,name'])
                    ->findOrFail($refId);

                return response()->json([
                    'tipo'     => 'adelanto',
                    'cabecera' => [
                        'proveedor' => $adelanto->proveedor?->razon_social ?? $adelanto->proveedor?->nombre_comercial,
                        'fecha'     => $adelanto->fecha?->format('Y-m-d'),
                        'monto'     => (float) $adelanto->monto,
                        'saldo'     => (float) $adelanto->saldo,
                    ],
                    'filas' => $adelanto->aplicaciones->map(fn ($ap) => [
                        'fecha'   => $ap->fecha?->format('Y-m-d'),
                        'detalle' => $ap->entrada ? "Aplicado a entrada {$ap->entrada->numero_documento}" : ($ap->observacion ?? 'Aplicación'),
                        'monto'   => (float) $ap->monto,
                        'user'    => $ap->user?->name,
                    ]),
                ]);
            }

            // ── Deuda / préstamo puntual: historial de movimientos ──────
            case 'deuda':
            case 'personal':
            case 'prestamo_otorgado': {
                $deuda = Deuda::deEmpresa($empresaId)
                    ->with(['pagos.metodoPago:id,nombre', 'pagos.cuenta:id,nombre', 'pagos.user:id,name'])
                    ->findOrFail($refId);

                return response()->json([
                    'tipo'     => 'deuda',
                    'cabecera' => [
                        'nombre'    => $deuda->nombre,
                        'direccion' => $deuda->direccion,
                        'original'  => (float) $deuda->monto_original,
                        'saldo'     => (float) $deuda->saldo,
                        'inicio'    => $deuda->fecha_inicio?->format('Y-m-d'),
                    ],
                    'filas' => $deuda->pagos->sortByDesc('fecha')->values()->map(fn ($p) => [
                        'fecha'   => $p->fecha?->format('Y-m-d'),
                        'detalle' => ($p->tipo === 'amortizacion' ? 'Amortización' : 'Incremento')
                            . ($p->cuenta ? " · {$p->cuenta->nombre}" : '')
                            . ($p->observacion ? " · {$p->observacion}" : ''),
                        'monto'   => (float) $p->monto * ($p->tipo === 'amortizacion' ? -1 : 1),
                        'user'    => $p->user?->name,
                    ]),
                ]);
            }
        }

        abort(404, 'Esta línea no tiene detalle.');
    }

    /**
     * Actualiza una línea manual (monto) o su check de conciliación ("OK").
     */
    public function actualizarItem(Request $request, BalanceDiarioItem $item)
    {
        $user    = $request->user();
        $balance = $item->balance;

        abort_if($balance->empresa_id !== $user->empresa_id, 403);
        abort_unless($balance->esBorrador(), 422, 'El balance ya fue confirmado.');

        $data = $request->validate([
            'monto'      => ['nullable', 'numeric'],
            'conciliado' => ['nullable', 'boolean'],
        ]);

        // Solo las líneas manuales aceptan cambio de monto; el check de
        // conciliación aplica a cualquiera (es la marca "OK" del Excel).
        $update = [];
        if (array_key_exists('monto', $data) && $data['monto'] !== null) {
            abort_unless($item->es_manual, 422, 'Esta línea se calcula automáticamente.');
            $update['monto'] = round((float) $data['monto'], 2);
        }
        if (array_key_exists('conciliado', $data) && $data['conciliado'] !== null) {
            $update['conciliado'] = $data['conciliado'];
        }

        if ($update) {
            $item->update($update);
            $balance->recalcularTotales();
        }

        return back();
    }

    /**
     * Agrega una línea manual extra (los "OSCAR ALBERTO - DEPÓSITO...",
     * "16 FIERRO 3/4", etc. del Excel).
     */
    public function agregarItem(Request $request, BalanceDiario $balance)
    {
        $user = $request->user();
        abort_if($balance->empresa_id !== $user->empresa_id, 403);
        abort_unless($balance->esBorrador(), 422, 'El balance ya fue confirmado.');

        $data = $request->validate([
            'seccion'     => ['required', Rule::in(['favor', 'contra'])],
            'descripcion' => ['required', 'string', 'max:250'],
            'monto'       => ['required', 'numeric', 'min:0'],
        ]);

        $maxOrden = (int) $balance->items()->where('seccion', $data['seccion'])->max('orden');

        $item = $balance->items()->create([
            'seccion'     => $data['seccion'],
            'categoria'   => $data['seccion'] === 'favor' ? 'otro_favor' : 'otro_contra',
            'descripcion' => $data['descripcion'],
            'monto'       => round((float) $data['monto'], 2),
            'es_manual'   => true,
            'conciliado'  => false,
            'orden'       => $maxOrden + 1,
        ]);

        // Trazabilidad: toda línea manual queda en auditoría con autor,
        // fecha y monto. (Lo recurrente debe registrarse en su módulo:
        // deudas, anticipos, adelantos... no como línea suelta.)
        \App\Services\AuditoriaService::log('balance.linea_manual', $item, [
            'balance_fecha' => $balance->fecha->toDateString(),
            'seccion'       => $data['seccion'],
            'descripcion'   => $data['descripcion'],
            'monto'         => (float) $data['monto'],
        ], $user);

        $balance->recalcularTotales();

        return back()->with('success', 'Línea agregada.');
    }

    /**
     * Elimina una línea manual.
     */
    public function eliminarItem(Request $request, BalanceDiarioItem $item)
    {
        $user    = $request->user();
        $balance = $item->balance;

        abort_if($balance->empresa_id !== $user->empresa_id, 403);
        abort_unless($balance->esBorrador(), 422, 'El balance ya fue confirmado.');
        abort_unless($item->es_manual, 422, 'Las líneas automáticas no se pueden eliminar.');

        \App\Services\AuditoriaService::log('balance.linea_manual_eliminada', $item, [
            'balance_fecha' => $balance->fecha->toDateString(),
            'descripcion'   => $item->descripcion,
            'monto'         => (float) $item->monto,
        ], $user);

        $item->delete();
        $balance->recalcularTotales();

        return back()->with('success', 'Línea eliminada.');
    }

    /**
     * Confirma el balance del día: snapshot inmutable que servirá de
     * "BALANCE AYER" para el siguiente.
     */
    public function confirmar(Request $request, BalanceDiario $balance)
    {
        $user = $request->user();
        abort_if($balance->empresa_id !== $user->empresa_id, 403);

        $this->service->confirmar($balance, $user);

        return back()->with('success', 'Balance confirmado. Ya es la referencia para el día siguiente.');
    }
}
