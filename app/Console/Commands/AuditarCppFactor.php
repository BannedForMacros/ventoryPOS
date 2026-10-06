<?php

namespace App\Console\Commands;

use App\Services\KardexService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * SOLO LECTURA. Lista los productos cuyo costo promedio quedó inflado por
 * comprarse en una presentación con factor ≠ 1 (caja de 12, saco de 50 kg...).
 *
 * Hasta octubre 2026 el precio de la línea de una entrada (precio por
 * PRESENTACIÓN) entraba al costo promedio como si fuera el precio por UNIDAD
 * BASE: una caja de 12 a S/ 120 ponía la unidad a S/ 120 en vez de S/ 10. El
 * motor ya divide entre el factor; este comando muestra, sin escribir nada,
 * el costo promedio actual frente al que dejaría una reconstrucción.
 *
 * OJO: la corrección NO espera a que alguien la aplique. Cualquier
 * reconstrucción la escribe: el "Recalcular" de Stock, `kardex:reconstruir` y
 * también el autocontrol nocturno (`inventario:autocontrol`, 02:00, todas las
 * empresas). Este comando sirve para ver el impacto ANTES de esa corrida
 * (el costo de ventas ya congelado en venta_items no se toca).
 *
 *   php artisan inventario:auditar-cpp-factor --empresa=1097
 */
class AuditarCppFactor extends Command
{
    protected $signature = 'inventario:auditar-cpp-factor
        {--empresa= : Limitar a una empresa}
        {--almacen= : Limitar a un almacén}';

    protected $description = 'SOLO LECTURA: productos con compras en presentaciones (factor ≠ 1) y su costo promedio actual vs. corregido.';

    public function handle(KardexService $kardex): int
    {
        $empresaId = $this->option('empresa') ? (int) $this->option('empresa') : null;
        $almacenId = $this->option('almacen') ? (int) $this->option('almacen') : null;

        $pares = DB::table('entradas_detalle as ed')
            ->join('entradas as e', 'e.id', '=', 'ed.entrada_id')
            ->join('productos as p', 'p.id', '=', 'ed.producto_id')
            ->join('almacenes as a', 'a.id', '=', 'e.almacen_id')
            ->where('e.estado', 'confirmado')
            ->whereRaw('ABS(ed.factor_conversion - 1) > 0.00005')
            ->when($empresaId, fn ($q) => $q->where('e.empresa_id', $empresaId))
            ->when($almacenId, fn ($q) => $q->where('e.almacen_id', $almacenId))
            ->groupBy('e.empresa_id', 'e.almacen_id', 'a.nombre', 'ed.producto_id', 'p.nombre')
            ->selectRaw('e.empresa_id, e.almacen_id, a.nombre as almacen, ed.producto_id, p.nombre as producto,
                         COUNT(*) as lineas, MIN(ed.factor_conversion) as factor_min, MAX(ed.factor_conversion) as factor_max')
            ->orderBy('e.empresa_id')->orderBy('a.nombre')->orderBy('p.nombre')
            ->get();

        if ($pares->isEmpty()) {
            $this->info('No hay entradas confirmadas en presentaciones con factor distinto de 1. Nada que revisar.');

            return self::SUCCESS;
        }

        $this->info("[SOLO LECTURA] {$pares->count()} productos (almacén × producto) con compras en presentaciones.");

        $filas = [];
        $valorAntes = 0.0;
        $valorDespues = 0.0;
        foreach ($pares as $p) {
            // Simulación del motor único: no escribe stock ni kardex.
            $r = $kardex->reconstruirPar((int) $p->almacen_id, (int) $p->producto_id, simular: true);
            $valorAntes   += $r['cantidad_antes'] * $r['costo_antes'];
            $valorDespues += $r['cantidad_despues'] * $r['costo_despues'];

            $filas[] = [
                $p->empresa_id,
                mb_strimwidth((string) $p->almacen, 0, 20, '…'),
                mb_strimwidth((string) $p->producto, 0, 36, '…'),
                (int) $p->lineas,
                $this->num((float) $p->factor_min) . ((float) $p->factor_min !== (float) $p->factor_max ? '–' . $this->num((float) $p->factor_max) : ''),
                $this->num($r['cantidad_antes']),
                number_format($r['costo_antes'], 4),
                number_format($r['costo_despues'], 4),
                $r['costo_antes'] > 0 ? number_format($r['costo_despues'] / $r['costo_antes'], 4) : '—',
            ];
        }

        $this->table(
            ['Empresa', 'Almacén', 'Producto', 'Líneas', 'Factor', 'Stock', 'CPP actual', 'CPP corregido', 'Corregido/actual'],
            $filas,
        );

        $this->line(sprintf('  Valor del stock de estos productos: S/ %s actual → S/ %s corregido.',
            number_format($valorAntes, 2), number_format($valorDespues, 2)));
        $this->line('  Este comando no escribió nada. Ojo: la corrección se aplica sola en la próxima reconstrucción');
        $this->line('  ("Recalcular" en Stock, kardex:reconstruir o el autocontrol nocturno inventario:autocontrol de las 02:00).');
        $this->line('  Ojo: el costo ya congelado en ventas pasadas (venta_items) no cambia con la reconstrucción.');

        return self::SUCCESS;
    }

    private function num(float $n): string
    {
        return rtrim(rtrim(number_format($n, 4, '.', ''), '0'), '.');
    }
}
