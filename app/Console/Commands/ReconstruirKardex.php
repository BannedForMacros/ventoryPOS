<?php

namespace App\Console\Commands;

use App\Models\Stock;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Reconstruye el ledger de kardex (movimientos_inventario) desde CERO a partir
 * de todos los documentos ya existentes (entradas, salidas, ventas, devoluciones,
 * transferencias y cierres), en orden cronológico, recalculando costo promedio
 * ponderado y saldo por cada (almacén, producto).
 *
 * Es SEGURO y REPETIBLE: solo escribe en movimientos_inventario. No toca la
 * tabla `stock` ni ningún otro dato. Se puede correr las veces que haga falta.
 *
 *   php artisan kardex:reconstruir
 *   php artisan kardex:reconstruir --empresa=2
 */
class ReconstruirKardex extends Command
{
    protected $signature = 'kardex:reconstruir {--empresa= : Limitar a una empresa} {--fresh : Vaciar el kardex antes de reconstruir (por defecto sí)}';

    protected $description = 'Reconstruye el kardex (movimientos_inventario) desde los documentos existentes.';

    public function handle(): int
    {
        $empresaId = $this->option('empresa') ? (int) $this->option('empresa') : null;

        // Almacenes en alcance
        $almacenes = DB::table('almacenes')
            ->when($empresaId, fn ($q) => $q->where('empresa_id', $empresaId))
            ->get(['id', 'empresa_id', 'local_id', 'tipo'])
            ->keyBy('id');

        if ($almacenes->isEmpty()) {
            $this->warn('No hay almacenes en el alcance indicado.');
            return self::SUCCESS;
        }

        $almacenIds = $almacenes->keys()->all();

        // Vaciar el kardex del alcance (por defecto siempre)
        $this->info('Limpiando kardex previo del alcance...');
        DB::table('movimientos_inventario')->whereIn('almacen_id', $almacenIds)->delete();

        $pares = Stock::combinacionesConMovimientos($almacenIds);
        $this->info("Reconstruyendo {$pares->count()} combinaciones (almacén × producto)...");

        $bar = $this->output->createProgressBar($pares->count());
        $bar->start();

        $totalFilas = 0;
        foreach ($pares as $p) {
            $almacen = $almacenes->get($p['almacen_id']);
            if (!$almacen) { $bar->advance(); continue; }

            $movs = $this->recolectarMovimientos($almacen, (int) $p['producto_id']);
            if (empty($movs)) { $bar->advance(); continue; }

            // Orden cronológico estable
            usort($movs, function ($a, $b) {
                $fa = $a['fecha'] ?? '0000-00-00';
                $fb = $b['fecha'] ?? '0000-00-00';
                return $fa <=> $fb ?: (($a['orden'] ?? 0) <=> ($b['orden'] ?? 0));
            });

            $totalFilas += $this->reproducir($almacen, (int) $p['producto_id'], $movs);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Listo. Se registraron {$totalFilas} movimientos en el kardex.");
        return self::SUCCESS;
    }

    /**
     * Reúne todos los movimientos de un (almacén, producto) desde todas las fuentes.
     * Cada movimiento: fecha, tipo, referencia, cantidad (±base), costo (para los
     * que recalculan CPP), recalc (bool), documento, user_id.
     */
    private function recolectarMovimientos(object $almacen, int $productoId): array
    {
        $almacenId = (int) $almacen->id;
        $movs = [];

        // Inventario inicial (apertura): si existe, es la PRIMERA línea del kardex
        // (traza el saldo de arranque) y solo se cuentan movimientos POSTERIORES a
        // la fecha de corte. Sin apertura, se listan todos (comportamiento previo).
        $apertura = DB::table('stock_iniciales')
            ->where('almacen_id', $almacenId)->where('producto_id', $productoId)->first();
        $corte = $apertura ? substr((string) $apertura->fecha, 0, 10) . ' 23:59:59' : null;
        $post  = fn ($q, string $col) => $corte ? $q->where($col, '>', $corte) : $q;

        if ($apertura) {
            $movs[] = $this->mov(substr((string) $apertura->fecha, 0, 10), 0,
                'inventario_inicial', 'stock_inicial', (int) $apertura->id,
                (float) $apertura->cantidad, (float) $apertura->costo, true, null, null);
        }

        // (+) Entradas confirmadas — recalculan CPP con su precio_costo
        foreach ($post(DB::table('entradas_detalle as ed')
            ->join('entradas as e', 'e.id', '=', 'ed.entrada_id')
            ->where('e.almacen_id', $almacenId)
            ->where('e.estado', 'confirmado')
            ->where('ed.producto_id', $productoId), 'e.fecha')
            ->get(['ed.cantidad_base', 'ed.precio_costo', 'e.id', 'e.fecha', 'e.numero_documento', 'e.user_id']) as $r) {
            $movs[] = $this->mov($r->fecha, (int) $r->id, 'entrada', 'entrada', (int) $r->id,
                (float) $r->cantidad_base, (float) $r->precio_costo, true, $r->numero_documento, $r->user_id);
        }

        // (-) Salidas confirmadas
        foreach ($post(DB::table('salidas_detalle as sd')
            ->join('salidas as s', 's.id', '=', 'sd.salida_id')
            ->where('s.almacen_id', $almacenId)
            ->where('s.estado', 'confirmado')
            ->where('sd.producto_id', $productoId), 's.fecha')
            ->get(['sd.cantidad_base', 's.id', 's.fecha', 's.numero_documento', 's.user_id']) as $r) {
            $movs[] = $this->mov($r->fecha, (int) $r->id, 'salida', 'salida', (int) $r->id,
                -1 * (float) $r->cantidad_base, 0, false, $r->numero_documento, $r->user_id);
        }

        // (-) Transferencias salientes (enviada/recibida)
        foreach ($post(DB::table('transferencias_detalle as td')
            ->join('transferencias as t', 't.id', '=', 'td.transferencia_id')
            ->where('t.almacen_origen_id', $almacenId)
            ->whereIn('t.estado', ['enviada', 'recibida'])
            ->where('td.producto_id', $productoId), 't.fecha_envio')
            ->get(['td.cantidad_base_enviada', 't.id', 't.fecha_envio', 't.user_envio_id']) as $r) {
            $movs[] = $this->mov($r->fecha_envio, (int) $r->id, 'transferencia_envio', 'transferencia', (int) $r->id,
                -1 * (float) $r->cantidad_base_enviada, 0, false, null, $r->user_envio_id);
        }

        // (+) Transferencias entrantes (recibida) — recalculan CPP con costo_unitario
        foreach ($post(DB::table('transferencias_detalle as td')
            ->join('transferencias as t', 't.id', '=', 'td.transferencia_id')
            ->where('t.almacen_destino_id', $almacenId)
            ->where('t.estado', 'recibida')
            ->where('td.producto_id', $productoId), 't.fecha_recepcion')
            ->get(['td.cantidad_base_recibida', 'td.costo_unitario', 't.id', 't.fecha_recepcion', 't.user_recepcion_id']) as $r) {
            $movs[] = $this->mov($r->fecha_recepcion, (int) $r->id, 'transferencia_recepcion', 'transferencia', (int) $r->id,
                (float) $r->cantidad_base_recibida, (float) $r->costo_unitario, true, null, $r->user_recepcion_id);
        }

        // (-) Ventas completadas — solo si el almacén es tipo 'local' del local de la venta
        if ($almacen->tipo === 'local' && $almacen->local_id) {
            foreach ($post(DB::table('venta_items as vi')
                ->join('ventas as v', 'v.id', '=', 'vi.venta_id')
                ->where('v.local_id', $almacen->local_id)
                ->where('v.empresa_id', $almacen->empresa_id)
                ->where('v.estado', 'completada')
                ->where('vi.producto_id', $productoId), 'v.fecha_venta')
                ->get(['vi.cantidad_base', 'v.id', 'v.fecha_venta', 'v.user_id']) as $r) {
                $movs[] = $this->mov($r->fecha_venta, (int) $r->id, 'venta', 'venta', (int) $r->id,
                    -1 * (float) $r->cantidad_base, 0, false, null, $r->user_id);
            }

            // (+) Devoluciones completadas con restock
            foreach ($post(DB::table('devoluciones_detalle as dd')
                ->join('devoluciones as d', 'd.id', '=', 'dd.devolucion_id')
                ->where('d.local_id', $almacen->local_id)
                ->where('d.empresa_id', $almacen->empresa_id)
                ->where('d.estado', 'completada')
                ->where('dd.restock', true)
                ->where('dd.producto_id', $productoId), 'd.created_at')
                ->get(['dd.cantidad_base', 'd.id', 'd.created_at']) as $r) {
                $movs[] = $this->mov($r->created_at, (int) $r->id, 'devolucion', 'devolucion', (int) $r->id,
                    (float) $r->cantidad_base, 0, false, null, null);
            }
        }

        // (±) Cierres confirmados — aplican la diferencia declarada
        foreach ($post(DB::table('cierres_inventario_items as ci')
            ->join('cierres_inventario as c', 'c.id', '=', 'ci.cierre_id')
            ->where('c.almacen_id', $almacenId)
            ->where('c.estado', 'confirmado')
            ->where('ci.producto_id', $productoId), 'c.fecha')
            ->get(['ci.diferencia', 'c.id', 'c.fecha', 'c.user_id']) as $r) {
            $movs[] = $this->mov($r->fecha, (int) $r->id, 'cierre', 'cierre', (int) $r->id,
                (float) $r->diferencia, 0, false, null, $r->user_id);
        }

        return $movs;
    }

    private function mov($fecha, int $orden, string $tipo, string $refTipo, int $refId,
                         float $cantidad, float $costo, bool $recalc, ?string $doc, $userId): array
    {
        return [
            'fecha'    => $fecha ? (string) $fecha : null,
            'orden'    => $orden,
            'tipo'     => $tipo,
            'ref_tipo' => $refTipo,
            'ref_id'   => $refId,
            'cantidad' => $cantidad,
            'costo'    => $costo,
            'recalc'   => $recalc,
            'doc'      => $doc,
            'user_id'  => $userId,
        ];
    }

    /**
     * Reproduce los movimientos en orden, recalculando CPP y saldo, e inserta
     * una fila de kardex por cada uno.
     */
    private function reproducir(object $almacen, int $productoId, array $movs): int
    {
        $cant = 0.0;
        $cpp  = 0.0;
        $filas = [];
        $ahora = now();

        foreach ($movs as $m) {
            $q     = (float) $m['cantidad'];
            $costo = (float) $m['costo'];

            if ($m['recalc'] && $q > 0 && $costo > 0) {
                // Ingreso CON costo real (compra / recepción): recalcula CPP ponderado.
                $nueva = $cant + $q;
                $cpp   = $nueva > 0 ? (($cant * $cpp) + ($q * $costo)) / $nueva : 0.0;
                $cant  = $nueva;
                $costoUnitario = $costo;
            } else {
                // Salida / venta / transf. salida / devolución / cierre / ingreso sin costo:
                // NO altera el CPP; entra o sale al costo vigente (regla canónica).
                $cant += $q;
                $costoUnitario = $cpp;
            }

            $filas[] = [
                'empresa_id'       => $almacen->empresa_id,
                'almacen_id'       => $almacen->id,
                'producto_id'      => $productoId,
                'fecha'            => $m['fecha'] ?? $ahora,
                'tipo'             => $m['tipo'],
                'referencia_tipo'  => $m['ref_tipo'],
                'referencia_id'    => $m['ref_id'],
                'documento'        => $m['doc'],
                'cantidad'         => round($q, 4),
                'costo_unitario'   => round($costoUnitario, 4),
                'costo_promedio'   => round($cpp, 4),
                'saldo_cantidad'   => round($cant, 4),
                'saldo_valorizado' => round($cant * $cpp, 4),
                'user_id'          => $m['user_id'],
                'created_at'       => $ahora,
                'updated_at'       => $ahora,
            ];
        }

        foreach (array_chunk($filas, 500) as $chunk) {
            DB::table('movimientos_inventario')->insert($chunk);
        }

        return count($filas);
    }
}
