<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Congela el costo de las líneas de venta históricas con la regla única.
 *
 * - Mercadería con costo 0 o vacío: toma el costo del kardex en el movimiento de
 *   ESA venta (el costo promedio real de ese momento). Si el kardex no lo tiene,
 *   queda NULL = desconocido (antes 0, que los reportes reemplazaban por el costo
 *   de hoy). Los reportes estiman un desconocido con costos estables del kardex.
 * - Con --corregir-editadas: las ventas editadas antes del Bloque 1 recongelaban
 *   su costo al día de la edición; se restaura el costo del kardex de la venta.
 *
 *   php artisan ventas:recuperar-costos --empresa=1097 --simular
 *   php artisan ventas:recuperar-costos --empresa=1097 --corregir-editadas
 */
class RecuperarCostosVenta extends Command
{
    protected $signature = 'ventas:recuperar-costos
        {--empresa= : Limitar a una empresa}
        {--simular : No escribe nada; muestra qué cambiaría}
        {--corregir-editadas : También restaura el costo de ventas editadas que se recongeló al editarlas}';

    protected $description = 'Congela el costo de líneas de venta históricas desde el kardex (sin costo → costo real de esa venta o desconocido).';

    public function handle(): int
    {
        $simular = (bool) $this->option('simular');
        $empresa = $this->option('empresa') ? (int) $this->option('empresa') : null;

        // Costo del kardex en el movimiento de la venta (el que tenía ese día).
        $kardexVenta = "(SELECT NULLIF(mi.costo_promedio, 0) FROM movimientos_inventario mi
            WHERE mi.referencia_tipo = 'venta' AND mi.referencia_id = vi.venta_id AND mi.producto_id = vi.producto_id
              AND mi.tipo IN ('venta', 'entrega_pendiente') ORDER BY mi.id LIMIT 1)";

        $base = fn () => DB::table('venta_items as vi')
            ->join('ventas as v', 'v.id', '=', 'vi.venta_id')
            ->join('productos as p', 'p.id', '=', 'vi.producto_id')
            ->where('v.estado', 'completada')->where('p.tipo', 'producto')
            ->when($empresa, fn ($q) => $q->where('v.empresa_id', $empresa));

        // 1) Sin costo: recuperar del kardex o marcar desconocido.
        $sinCosto = $base()
            ->where(fn ($q) => $q->whereNull('vi.costo_unitario_base')->orWhere('vi.costo_unitario_base', '<=', 0))
            ->selectRaw("vi.id, v.numero, v.fecha_venta, vi.producto_nombre, vi.cantidad_base, vi.costo_unitario_base, {$kardexVenta} AS costo_kardex")
            ->get();

        $recuperados   = $sinCosto->filter(fn ($i) => $i->costo_kardex !== null);
        $desconocidos  = $sinCosto->filter(fn ($i) => $i->costo_kardex === null);
        $aMarcarNull   = $desconocidos->filter(fn ($i) => $i->costo_unitario_base !== null);

        $this->info(($simular ? '[SIMULACIÓN] ' : '') . "Líneas de mercadería sin costo congelado: {$sinCosto->count()}");
        $this->line(sprintf('  Recuperables del kardex (costo real de esa venta): %d · costo S/ %s',
            $recuperados->count(), number_format($recuperados->sum(fn ($i) => (float) $i->cantidad_base * (float) $i->costo_kardex), 2)));
        $this->line("  Sin costo en el kardex → quedan como DESCONOCIDO (NULL): {$desconocidos->count()}");

        // 2) Ventas editadas cuyo costo se recongeló al editarlas.
        $editadas = collect();
        if ($this->option('corregir-editadas')) {
            $editadas = $base()
                ->whereExists(fn ($q) => $q->from('auditoria as a')->whereColumn('a.modelo_id', 'v.id')->where('a.accion', 'venta.editada'))
                ->where('vi.costo_unitario_base', '>', 0)
                ->selectRaw("vi.id, v.numero, v.fecha_venta, vi.producto_nombre, vi.cantidad_base, vi.costo_unitario_base, {$kardexVenta} AS costo_kardex")
                ->get()
                ->filter(fn ($i) => $i->costo_kardex !== null
                    && abs((float) $i->costo_kardex - (float) $i->costo_unitario_base) > max(0.01, 0.005 * (float) $i->costo_kardex));

            $this->line(sprintf('  Ventas editadas con costo recongelado a restaurar: %d líneas · efecto en costo S/ %s',
                $editadas->count(),
                number_format($editadas->sum(fn ($i) => (float) $i->cantidad_base * ((float) $i->costo_kardex - (float) $i->costo_unitario_base)), 2)));
        }

        $muestra = $recuperados->concat($editadas)
            ->sortByDesc(fn ($i) => abs((float) $i->cantidad_base * ((float) $i->costo_kardex - (float) $i->costo_unitario_base)))
            ->take(20);
        if ($muestra->isNotEmpty()) {
            $this->table(['Venta', 'Fecha', 'Producto', 'Cant. base', 'Costo actual', 'Costo del kardex'],
                $muestra->map(fn ($i) => [
                    $i->numero, substr((string) $i->fecha_venta, 0, 10), mb_strimwidth($i->producto_nombre, 0, 34, '…'),
                    round((float) $i->cantidad_base, 4), $i->costo_unitario_base === null ? '—' : round((float) $i->costo_unitario_base, 4),
                    round((float) $i->costo_kardex, 4),
                ])->values()->all());
        }

        if ($simular) {
            $this->warn('Simulación: no se escribió nada.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($recuperados, $aMarcarNull, $editadas) {
            foreach ($recuperados->concat($editadas) as $i) {
                DB::table('venta_items')->where('id', $i->id)->update(['costo_unitario_base' => round((float) $i->costo_kardex, 4)]);
            }
            if ($aMarcarNull->isNotEmpty()) {
                DB::table('venta_items')->whereIn('id', $aMarcarNull->pluck('id'))->update(['costo_unitario_base' => null]);
            }
        });

        $this->info('Costos congelados.');

        return self::SUCCESS;
    }
}
