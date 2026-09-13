<?php

namespace App\Services;

use App\Models\BalanceDiario;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * "¿Qué cambió después del cierre?" — la explicación de un día sucio.
 *
 * Sobre la verificación (BalanceVerificacionService, que dice QUÉ líneas
 * cambiaron y cuánto), busca los DOCUMENTOS que lo causaron: todo lo que tiene
 * fecha igual o anterior al día pero se registró, editó, anuló o borró DESPUÉS
 * de tomada la foto de ese balance. Si una línea cambió y no hay ningún
 * documento que lo explique, se informa como corrección del cálculo.
 *
 * SOLO LEE. Los días cerrados no se reabren ni se regeneran.
 */
class CambiosCierreService
{
    /** Desde este monto un día se marca como "cambió después del cierre". */
    public const UMBRAL = 100.0;

    public function __construct(private BalanceVerificacionService $verificador) {}

    /**
     * Momento en que se tomó la foto del balance: cuándo se generaron sus líneas
     * automáticas (si se regeneró después de confirmar, cuenta la regeneración).
     */
    public function fotoTomada(BalanceDiario $balance): ?Carbon
    {
        $lineas = $balance->items()->where('es_manual', false)->max('created_at');
        if ($lineas) {
            return Carbon::parse($lineas);
        }

        $confirmado = DB::table('auditoria')->where('empresa_id', $balance->empresa_id)
            ->where('accion', 'balance.confirmado')->where('modelo_id', $balance->id)->max('created_at');

        return $confirmado ? Carbon::parse($confirmado) : $balance->updated_at;
    }

    /** Solo el monto (rápido): para marcar días en la lista. */
    public function resumen(BalanceDiario $balance): array
    {
        if ($balance->estado !== 'confirmado' || !$this->verificador->verificable($balance)) {
            return ['fecha' => $balance->fecha->toDateString(), 'diferencia' => 0.0, 'relevante' => false, 'verificable' => false];
        }

        $r = $this->verificador->verificar($balance);

        return [
            'fecha'       => $r['fecha'],
            'diferencia'  => $r['diferencia_patrimonio'],
            'relevante'   => abs($r['diferencia_patrimonio']) >= self::UMBRAL,
            'verificable' => true,
        ];
    }

    /** Verificación completa + documentos causantes por línea. */
    public function analizar(BalanceDiario $balance): array
    {
        $r = $this->verificador->verificar($balance);
        $foto = $this->fotoTomada($balance);

        $documentos = $foto
            ? $this->documentosPosteriores($balance->empresa_id, $r['fecha'], $foto)
            : collect();

        $r['foto_tomada']   = $foto?->toIso8601String();
        $r['relevante']     = abs($r['diferencia_patrimonio']) >= self::UMBRAL;
        $r['verificable']   = $this->verificador->verificable($balance);
        $r['categorias']    = collect($r['categorias'])->map(function ($c) use ($documentos) {
            $docs = $documentos->filter(fn ($d) => in_array($c['categoria'], $d['categorias'], true))->values();

            return $c + [
                'documentos'    => $docs->all(),
                // Sin documento que lo explique: el cálculo de ese día cambió (una
                // corrección del sistema), no un registro del usuario.
                'sin_documento' => $docs->isEmpty(),
            ];
        })->all();

        return $r;
    }

    /**
     * Documentos con fecha <= $fecha registrados, editados, anulados o borrados
     * después de $foto. Cada uno indica a qué categorías del balance afecta.
     */
    public function documentosPosteriores(int $empresaId, string $fecha, Carbon $foto): Collection
    {
        $corte   = $fecha . ' 23:59:59';
        $docs    = collect();
        $usuarios = [];

        $agregar = function (array $categorias, string $tipo, ?string $documento, $fechaDoc, $cuando, $monto, $userId = null, ?string $userName = null)
            use (&$docs, &$usuarios) {
            if ($userId) $usuarios[$userId] = true;
            $docs->push([
                'categorias'      => $categorias,
                'tipo'            => $tipo,
                'documento'       => $documento,
                'fecha_documento' => $fechaDoc ? substr((string) $fechaDoc, 0, 10) : null,
                'cuando'          => $cuando ? Carbon::parse($cuando)->format('Y-m-d H:i') : null,
                'monto'           => $monto !== null ? round((float) $monto, 2) : null,
                'user_id'         => $userId,
                'usuario'         => $userName,
            ]);
        };

        $cajas = ['efectivo', 'cuenta_bancaria'];

        // ── Registrados DESPUÉS con fecha anterior (retrofechados) ─────────
        foreach (DB::table('entradas')->where('empresa_id', $empresaId)->where('estado', 'confirmado')
            ->where('fecha', '<=', $fecha)->where('created_at', '>', $foto)
            ->get(['id', 'numero_documento', 'correlativo', 'fecha', 'created_at', 'total', 'user_id']) as $e) {
            $agregar(['stock', 'cxp'], 'Compra registrada después del cierre',
                $e->numero_documento ?: ($e->correlativo ?: "#{$e->id}"), $e->fecha, $e->created_at, $e->total, $e->user_id);
        }

        // Compra ya existente cuyas líneas se reemplazaron después (editar una
        // entrada borra y vuelve a crear su detalle; no deja auditoría propia).
        foreach (DB::table('entradas as e')->join('entradas_detalle as ed', 'ed.entrada_id', '=', 'e.id')
            ->where('e.empresa_id', $empresaId)->where('e.fecha', '<=', $fecha)
            ->where('e.created_at', '<=', $foto)->where('ed.created_at', '>', $foto)
            ->groupBy('e.id', 'e.numero_documento', 'e.correlativo', 'e.fecha', 'e.total', 'e.user_id')
            ->selectRaw('e.id, e.numero_documento, e.correlativo, e.fecha, e.total, e.user_id, MAX(ed.created_at) as cuando')
            ->get() as $e) {
            $agregar(['stock', 'cxp'], 'Compra editada después del cierre',
                $e->numero_documento ?: ($e->correlativo ?: "#{$e->id}"), $e->fecha, $e->cuando, $e->total, $e->user_id);
        }

        foreach (DB::table('ventas')->where('empresa_id', $empresaId)->where('estado', 'completada')
            ->where('fecha_venta', '<=', $corte)->where('created_at', '>', $foto)
            ->get(['id', 'numero', 'fecha_venta', 'created_at', 'total', 'user_id', 'es_credito']) as $v) {
            $agregar(array_merge(['stock', 'anticipo_cliente'], $v->es_credito ? ['cxc'] : [], $cajas),
                'Venta registrada con fecha anterior', $v->numero, $v->fecha_venta, $v->created_at, $v->total, $v->user_id);
        }

        foreach (DB::table('venta_abonos as a')->join('ventas as v', 'v.id', '=', 'a.venta_id')
            ->where('v.empresa_id', $empresaId)->where('a.fecha', '<=', $fecha)->where('a.created_at', '>', $foto)
            ->get(['a.id', 'v.numero', 'a.fecha', 'a.created_at', 'a.monto', 'a.user_id']) as $a) {
            $agregar(array_merge(['cxc'], $cajas), 'Cobro de crédito registrado después', $a->numero, $a->fecha, $a->created_at, $a->monto, $a->user_id);
        }

        foreach (DB::table('entrada_pagos as p')->join('entradas as e', 'e.id', '=', 'p.entrada_id')
            ->where('e.empresa_id', $empresaId)->where('p.fecha', '<=', $fecha)->where('p.created_at', '>', $foto)
            ->get(['p.id', 'e.numero_documento', 'e.id as entrada_id', 'p.fecha', 'p.created_at', 'p.monto', 'p.user_id']) as $p) {
            $agregar(array_merge(['cxp'], $cajas), 'Pago a proveedor registrado después',
                $p->numero_documento ?: "#{$p->entrada_id}", $p->fecha, $p->created_at, $p->monto, $p->user_id);
        }

        foreach (DB::table('cliente_anticipos')->where('empresa_id', $empresaId)
            ->where('fecha', '<=', $fecha)->where('created_at', '>', $foto)
            ->get(['id', 'fecha', 'created_at', 'monto', 'user_id']) as $a) {
            $agregar(array_merge(['anticipo_cliente'], $cajas), 'Anticipo de cliente registrado después', "Anticipo #{$a->id}", $a->fecha, $a->created_at, $a->monto, $a->user_id);
        }

        foreach (DB::table('cliente_anticipo_aplicaciones as ca')->join('cliente_anticipos as c', 'c.id', '=', 'ca.cliente_anticipo_id')
            ->where('c.empresa_id', $empresaId)->where('ca.fecha', '<=', $fecha)->where('ca.created_at', '>', $foto)
            ->get(['ca.id', 'ca.numero', 'c.id as anticipo_id', 'ca.fecha', 'ca.created_at', 'ca.monto', 'ca.user_id']) as $a) {
            $agregar(['anticipo_cliente', 'stock'], 'Entrega de pedido registrada después',
                $a->numero ?: "Anticipo #{$a->anticipo_id}", $a->fecha, $a->created_at, $a->monto, $a->user_id);
        }

        foreach (DB::table('proveedor_adelantos')->where('empresa_id', $empresaId)
            ->where('fecha', '<=', $fecha)->where('created_at', '>', $foto)
            ->get(['id', 'fecha', 'created_at', 'monto', 'user_id']) as $a) {
            $agregar(array_merge(['adelanto_proveedor'], $cajas), 'Adelanto a proveedor registrado después', "Adelanto #{$a->id}", $a->fecha, $a->created_at, $a->monto, $a->user_id);
        }

        foreach (DB::table('deudas')->where('empresa_id', $empresaId)
            ->where(fn ($q) => $q->where('fecha_inicio', '<=', $fecha)->orWhereNull('fecha_inicio'))
            ->where('created_at', '>', $foto)
            ->get(['id', 'nombre', 'fecha_inicio', 'created_at', 'monto_original', 'user_id']) as $d) {
            $agregar(array_merge(['deuda', 'personal', 'prestamo_otorgado'], $cajas), 'Deuda o préstamo registrado después', $d->nombre, $d->fecha_inicio, $d->created_at, $d->monto_original, $d->user_id);
        }

        foreach (DB::table('deuda_pagos as p')->join('deudas as d', 'd.id', '=', 'p.deuda_id')
            ->where('d.empresa_id', $empresaId)->where('p.fecha', '<=', $fecha)
            ->where(fn ($q) => $q->where('p.created_at', '>', $foto)->orWhere('p.deleted_at', '>', $foto))
            ->get(['p.id', 'd.nombre', 'p.tipo', 'p.fecha', 'p.created_at', 'p.deleted_at', 'p.monto', 'p.user_id']) as $p) {
            $borrado = $p->deleted_at && Carbon::parse($p->deleted_at)->gt($foto);
            $agregar(array_merge(['deuda', 'personal', 'prestamo_otorgado'], $cajas),
                ($borrado ? 'Movimiento de deuda eliminado: ' : 'Movimiento de deuda registrado después: ') . ($p->tipo === 'amortizacion' ? 'amortización' : $p->tipo),
                $p->nombre, $p->fecha, $borrado ? $p->deleted_at : $p->created_at, $p->monto, $p->user_id);
        }

        foreach (DB::table('ajustes_inventario')->where('empresa_id', $empresaId)
            ->where('fecha', '<=', $fecha)->where('created_at', '>', $foto)
            ->get(['id', 'numero', 'tipo', 'fecha', 'created_at', 'cantidad_base', 'user_id']) as $a) {
            $agregar(['stock'], "Ajuste de inventario ({$a->tipo}) registrado después", $a->numero ?: "#{$a->id}", $a->fecha, $a->created_at, null, $a->user_id);
        }

        foreach (DB::table('gastos')->where('empresa_id', $empresaId)->where('fecha', '<=', $fecha)
            ->where(fn ($q) => $q->where('created_at', '>', $foto)->orWhere('deleted_at', '>', $foto))
            ->get(['id', 'fecha', 'created_at', 'deleted_at', 'monto', 'user_id']) as $g) {
            $borrado = $g->deleted_at && Carbon::parse($g->deleted_at)->gt($foto);
            $agregar($cajas, $borrado ? 'Gasto eliminado' : 'Gasto registrado después', "Gasto #{$g->id}", $g->fecha, $borrado ? $g->deleted_at : $g->created_at, $g->monto, $g->user_id);
        }

        // Movimientos manuales de caja/banco (los demás los explica su documento).
        foreach (DB::table('cuenta_movimientos as m')->join('cuentas as c', 'c.id', '=', 'm.cuenta_id')
            ->where('m.empresa_id', $empresaId)->where('m.fecha', '<=', $fecha)->where('m.created_at', '>', $foto)
            ->where(fn ($q) => $q->whereNull('m.ref_tipo')->orWhereIn('m.ref_tipo', ['ajuste', 'deuda']))
            ->get(['m.id', 'c.nombre as cuenta', 'm.tipo', 'm.descripcion', 'm.fecha', 'm.created_at', 'm.monto', 'm.user_id']) as $m) {
            $agregar($cajas, ($m->tipo === 'ingreso' ? 'Ingreso' : 'Egreso') . " en {$m->cuenta} registrado después",
                mb_strimwidth((string) $m->descripcion, 0, 60, '…'), $m->fecha, $m->created_at, $m->monto, $m->user_id);
        }

        // ── Acciones auditadas DESPUÉS sobre documentos de fecha anterior ──
        $acciones = [
            'venta.anulada'               => [['stock', 'cxc', 'anticipo_cliente', 'efectivo', 'cuenta_bancaria'], 'Venta anulada', 'ventas', 'fecha_venta', 'numero'],
            'venta.editada'               => [['stock', 'cxc', 'anticipo_cliente', 'efectivo', 'cuenta_bancaria'], 'Venta editada', 'ventas', 'fecha_venta', 'numero'],
            'venta.pedido_modificado'     => [['stock', 'anticipo_cliente', 'cxc'], 'Pedido pendiente modificado', 'ventas', 'fecha_venta', 'numero'],
            'entrada.anulada'             => [['stock', 'cxp', 'efectivo', 'cuenta_bancaria'], 'Compra anulada', 'entradas', 'fecha', 'numero_documento'],
            'entrada.pago_anulado'        => [['cxp', 'efectivo', 'cuenta_bancaria'], 'Pago a proveedor anulado', 'entradas', 'fecha', 'numero_documento'],
            'entrada.pago_editado'        => [['cxp', 'efectivo', 'cuenta_bancaria'], 'Pago a proveedor editado', 'entradas', 'fecha', 'numero_documento'],
            'cxc.abono_anulado'           => [['cxc', 'efectivo', 'cuenta_bancaria'], 'Cobro de crédito anulado', 'ventas', 'fecha_venta', 'numero'],
            'cxc.abono_editado'           => [['cxc', 'efectivo', 'cuenta_bancaria'], 'Cobro de crédito editado', 'ventas', 'fecha_venta', 'numero'],
            'anticipo_cliente.anulado'    => [['anticipo_cliente', 'efectivo', 'cuenta_bancaria'], 'Anticipo anulado', 'cliente_anticipos', 'fecha', null],
            'anticipo_cliente.devuelto'   => [['anticipo_cliente', 'efectivo', 'cuenta_bancaria'], 'Anticipo devuelto', 'cliente_anticipos', 'fecha', null],
            'anticipo_cliente.editado'    => [['anticipo_cliente'], 'Anticipo editado', 'cliente_anticipos', 'fecha', null],
            'anticipo_cliente.entrega_anulada' => [['anticipo_cliente', 'stock'], 'Entrega de pedido anulada', 'cliente_anticipos', 'fecha', null],
            'anticipo_cliente.entrega_editada' => [['anticipo_cliente', 'stock'], 'Entrega de pedido editada', 'cliente_anticipos', 'fecha', null],
            'adelanto_proveedor.editado'  => [['adelanto_proveedor', 'efectivo', 'cuenta_bancaria'], 'Adelanto editado', 'proveedor_adelantos', 'fecha', null],
            'adelanto_proveedor.devuelto' => [['adelanto_proveedor', 'efectivo', 'cuenta_bancaria'], 'Adelanto devuelto', 'proveedor_adelantos', 'fecha', null],
            'adelanto_proveedor.anulado'  => [['adelanto_proveedor', 'efectivo', 'cuenta_bancaria'], 'Adelanto anulado', 'proveedor_adelantos', 'fecha', null],
            'deuda.editada'               => [['deuda', 'personal', 'prestamo_otorgado'], 'Deuda editada', 'deudas', 'fecha_inicio', 'nombre'],
            'deuda.anulada'               => [['deuda', 'personal', 'prestamo_otorgado'], 'Deuda anulada', 'deudas', 'fecha_inicio', 'nombre'],
            'ajuste_inventario.anulado'   => [['stock'], 'Ajuste de inventario anulado', 'ajustes_inventario', 'fecha', 'numero'],
            'gasto.editado'               => [['efectivo', 'cuenta_bancaria'], 'Gasto editado', 'gastos', 'fecha', null],
            'devolucion.anulada'          => [['stock', 'efectivo', 'cuenta_bancaria'], 'Devolución anulada', 'devoluciones', 'fecha', null],
        ];

        $auditadas = DB::table('auditoria')->where('empresa_id', $empresaId)->where('created_at', '>', $foto)
            ->whereIn('accion', array_merge(array_keys($acciones), ['deuda.eliminada', 'tesoreria.movimiento_eliminado', 'stock.recalculado', 'stock.autoreparado']))
            ->orderBy('created_at')->get(['accion', 'modelo_id', 'contexto', 'created_at', 'user_name']);

        // Fecha de creación de cada deuda (para las eliminadas: su auditoría no
        // guardaba la fecha de inicio).
        $creacionDeuda = DB::table('auditoria')->where('empresa_id', $empresaId)->where('accion', 'deuda.creada')
            ->selectRaw('modelo_id, MIN(created_at) as f')->groupBy('modelo_id')->pluck('f', 'modelo_id');

        // Estas acciones afectan el balance en la fecha del EVENTO (la plata vuelve
        // ese día), no en la fecha del documento original.
        $fechaDelEvento = ['adelanto_proveedor.devuelto', 'anticipo_cliente.devuelto'];

        $fechasDoc = [];
        foreach ($auditadas as $a) {
            $ctx = json_decode((string) $a->contexto, true) ?: [];

            if ($a->accion === 'deuda.eliminada') {
                // Hard delete: la fecha y el nombre salen del snapshot de la auditoría.
                $snap = $ctx['snapshot'] ?? [];
                $fechaDeuda = $snap['fecha_inicio']
                    ?? ($creacionDeuda[$a->modelo_id] ?? null)
                    ?? collect($snap['movimientos'] ?? [])->min('fecha');
                if ($fechaDeuda === null || substr((string) $fechaDeuda, 0, 10) <= $fecha) {
                    $agregar(array_merge(['deuda', 'personal', 'prestamo_otorgado'], $cajas), 'Deuda o préstamo eliminado' . (isset($ctx['motivo']) ? " (motivo: {$ctx['motivo']})" : ''),
                        $snap['nombre'] ?? null, $fechaDeuda, $a->created_at, $snap['monto_original'] ?? null, null, $a->user_name);
                }
                continue;
            }

            if ($a->accion === 'tesoreria.movimiento_eliminado') {
                if (isset($ctx['fecha']) && substr((string) $ctx['fecha'], 0, 10) <= $fecha) {
                    $agregar($cajas, 'Movimiento de caja/banco eliminado', mb_strimwidth((string) ($ctx['descripcion'] ?? ''), 0, 60, '…'),
                        $ctx['fecha'], $a->created_at, $ctx['monto'] ?? null, null, $a->user_name);
                }
                continue;
            }

            if (in_array($a->accion, ['stock.recalculado', 'stock.autoreparado'], true)) {
                $agregar(['stock'], $a->accion === 'stock.autoreparado' ? 'Inventario corregido automáticamente' : 'Inventario recalculado',
                    null, null, $a->created_at, null, null, $a->user_name);
                continue;
            }

            [$categorias, $tipo, $tabla, $colFecha, $colDoc] = $acciones[$a->accion];
            if (!$a->modelo_id) continue;

            $fechasDoc[$tabla] ??= [];
            if (!array_key_exists($a->modelo_id, $fechasDoc[$tabla])) {
                $fila = DB::table($tabla)->where('id', $a->modelo_id)->first(array_filter([$colFecha, $colDoc]));
                $fechasDoc[$tabla][$a->modelo_id] = $fila;
            }
            $fila = $fechasDoc[$tabla][$a->modelo_id];
            if (!$fila) continue;
            $fechaEfecto = in_array($a->accion, $fechaDelEvento, true) ? $a->created_at : $fila->{$colFecha};
            if (substr((string) $fechaEfecto, 0, 10) > $fecha) continue;

            $agregar($categorias, $tipo, $colDoc ? ($fila->{$colDoc} ?? "#{$a->modelo_id}") : "#{$a->modelo_id}",
                $fila->{$colFecha}, $a->created_at, $ctx['total'] ?? $ctx['monto'] ?? null, null, $a->user_name);
        }

        $nombres = $usuarios ? DB::table('users')->whereIn('id', array_keys($usuarios))->pluck('name', 'id') : collect();

        return $docs
            ->map(fn ($d) => array_merge($d, ['usuario' => $d['usuario'] ?? ($d['user_id'] ? ($nombres[$d['user_id']] ?? null) : null)]))
            ->map(fn ($d) => collect($d)->except('user_id')->all())
            ->sortByDesc('cuando')
            ->values();
    }
}
