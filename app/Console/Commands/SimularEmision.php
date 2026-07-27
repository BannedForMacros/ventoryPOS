<?php

namespace App\Console\Commands;

use App\Exceptions\MapeoComprobanteException;
use App\Models\Venta;
use App\Services\Facturacion\VentaAComprobante;
use Illuminate\Console\Command;
use Throwable;

/**
 * Ensaya el mapeo de ventas REALES a comprobante electrónico sin enviar nada.
 *
 * Este comando es la razón de ser de que `VentaAComprobante` sea puro. Antes de que
 * el POS emita su primer documento fiscal irreversible queremos saber, sobre el
 * histórico completo de la ferretería, cuántas ventas mapean limpias, cuántas
 * necesitan absorber céntimos y cuáles no se podrían emitir y por qué. Eso son
 * minutos aquí frente a descubrirlo de una en una en caja, con el cliente delante y
 * con Notas de Crédito de por medio.
 *
 * No escribe en base de datos, no llama a FacturaMac y no toca SUNAT. Solo lee.
 */
class SimularEmision extends Command
{
    protected $signature = 'pos:simular-emision
                            {venta_id? : ID de una venta concreta; imprime su payload completo}
                            {--todas : Recorre las últimas ventas y saca una tabla resumen}
                            {--limite=100 : Cuántas ventas revisar con --todas}';

    protected $description = 'Simula la emisión electrónica de ventas reales (payload + conciliación) SIN enviar nada';

    public function handle(VentaAComprobante $mapper): int
    {
        $this->warn('Simulación en seco: no se escribe nada en la BD ni se contacta con FacturaMac/SUNAT.');
        $this->newLine();

        $ventaId = $this->argument('venta_id');

        if ($ventaId) {
            return $this->simularUna($mapper, (int) $ventaId);
        }

        if ($this->option('todas')) {
            return $this->simularTodas($mapper, max(1, (int) $this->option('limite')));
        }

        $this->error('Indica un venta_id o usa --todas. Ej: php artisan pos:simular-emision --todas --limite=200');

        return self::INVALID;
    }

    // ── Una venta: payload completo ──────────────────────────────────────────────

    private function simularUna(VentaAComprobante $mapper, int $ventaId): int
    {
        $venta = $this->consulta()->find($ventaId);

        if (! $venta) {
            $this->error("No existe la venta {$ventaId}.");

            return self::FAILURE;
        }

        // Mismo criterio que en --todas: hoy toda la ferretería vende como `ticket`,
        // y sin esto el comando no serviría para lo único que se le pide, que es
        // ensayar el mapeo antes de emitir. La reasignación es SOLO en memoria.
        if ($venta->tipo_comprobante === 'ticket') {
            $venta->tipo_comprobante = $this->tipoTentativo($venta);
            $this->warn("La venta es un ticket (no se emitiría). Se simula como '{$venta->tipo_comprobante}' "
                . 'para ensayar el mapeo.');
        }

        $this->linea($venta);

        try {
            $resultado = $mapper->mapearConDiagnostico($venta);
        } catch (MapeoComprobanteException $e) {
            $this->error('NO EMITIBLE: ' . $e->getMessage());
            if ($e->datos) {
                $this->line('  Contexto: ' . json_encode($e->datos, JSON_UNESCAPED_UNICODE));
            }

            return self::FAILURE;
        }

        $d = $resultado['diagnostico'];

        $this->newLine();
        $this->info('── Conciliación de céntimos ─────────────────────────────');
        $this->table(
            ['total venta', 'Σ subtotales', 'Σ IGV ítems', 'total comprobante', 'delta', 'ajuste'],
            [[
                number_format($d['total_venta'], 2),
                number_format($d['suma_subtotales'], 2),
                number_format($d['suma_igv'], 2),
                number_format($d['total_calculado'], 2),
                number_format($d['delta'], 2),
                $d['ajustado'] ? "sí (línea #{$d['linea_ajustada']})" : 'no',
            ]],
        );

        $this->newLine();
        $this->info('── Payload que se enviaría ──────────────────────────────');
        $this->line(json_encode(
            $resultado['payload'],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        ));

        return self::SUCCESS;
    }

    // ── Lote: tabla resumen ──────────────────────────────────────────────────────

    private function simularTodas(VentaAComprobante $mapper, int $limite): int
    {
        $ventas = $this->consulta()
            ->whereIn('tipo_comprobante', ['boleta', 'factura'])
            ->latest('id')
            ->limit($limite)
            ->get();

        $soloTickets = false;

        // Hoy la ferretería vende todo como `ticket`, así que sin este fallback el
        // comando no encontraría nada que revisar justo cuando más falta hace:
        // ANTES de empezar a emitir. Los tickets no se emiten nunca, pero su
        // aritmética es idéntica y sirve para validar el mapper contra datos reales.
        if ($ventas->isEmpty()) {
            $this->warn('No hay ventas boleta/factura. Se simulan tickets para validar la aritmética del mapper.');
            $this->newLine();
            $soloTickets = true;

            $ventas = $this->consulta()->latest('id')->limit($limite)->get();
        }

        if ($ventas->isEmpty()) {
            $this->error('No hay ventas en la base de datos.');

            return self::FAILURE;
        }

        $ok = 0;
        $ajustadas = 0;
        $fallidas = [];
        $deltas = [];

        foreach ($ventas as $venta) {
            // El ticket no genera comprobante: para poder ejercitar la aritmética le
            // damos, SOLO EN MEMORIA, el tipo que le tocaría. No se persiste.
            if ($soloTickets && $venta->tipo_comprobante === 'ticket') {
                $venta->tipo_comprobante = $this->tipoTentativo($venta);
            }

            try {
                $d = $mapper->mapearConDiagnostico($venta)['diagnostico'];

                $ok++;
                $deltas[] = abs($d['delta']);
                if ($d['ajustado']) {
                    $ajustadas++;
                }
            } catch (MapeoComprobanteException $e) {
                $fallidas[] = [$venta->id, $venta->numero, $venta->tipo_comprobante, $e->getMessage()];
            } catch (Throwable $e) {
                $fallidas[] = [$venta->id, $venta->numero, $venta->tipo_comprobante, '[ERROR] ' . $e->getMessage()];
            }
        }

        $total = $ventas->count();

        $this->info('── Resumen de la simulación ─────────────────────────────');
        $this->table(
            ['resultado', 'ventas', '%'],
            [
                ['Mapean exacto (delta 0)', $ok - $ajustadas, $this->pct($ok - $ajustadas, $total)],
                ['Necesitan ajuste de céntimos', $ajustadas, $this->pct($ajustadas, $total)],
                ['No emitibles', count($fallidas), $this->pct(count($fallidas), $total)],
                ['TOTAL revisadas', $total, '100%'],
            ],
        );

        if ($deltas !== []) {
            $this->line(sprintf('  Delta máximo observado: S/ %.2f', max($deltas)));
        }

        if ($fallidas !== []) {
            $this->newLine();
            $this->warn('── Ventas que NO se podrían emitir ──────────────────────');
            $this->table(['venta', 'número', 'tipo', 'motivo'], array_slice($fallidas, 0, 40));

            if (count($fallidas) > 40) {
                $this->line('  … y ' . (count($fallidas) - 40) . ' más.');
            }

            $this->newLine();
            $this->info('Motivos agrupados:');
            foreach ($this->agrupar($fallidas) as $motivo => $veces) {
                $this->line("  {$veces}×  {$motivo}");
            }
        }

        return self::SUCCESS;
    }

    // ── Helpers ──────────────────────────────────────────────────────────────────

    /**
     * Eager loading completo: el mapper es puro y NO dispara consultas por su cuenta,
     * así que si algo no viene cargado aquí, se vería como un dato ausente.
     */
    private function consulta()
    {
        return Venta::query()
            ->where('estado', 'completada')
            ->with([
                'empresa',
                'cliente',
                'items.producto:id,codigo,nombre',
                'items.productoUnidad.unidadMedida',
            ]);
    }

    /**
     * Con qué tipo se emitiría un ticket si hoy se emitiera: factura si el cliente
     * tiene RUC, boleta en cualquier otro caso. Es exactamente el criterio que
     * aplicaría el cajero.
     */
    private function tipoTentativo(Venta $venta): string
    {
        return ($venta->cliente && ! $venta->cliente->es_cliente_general && $venta->cliente->tipo_documento === 'RUC')
            ? 'factura'
            : 'boleta';
    }

    private function linea(Venta $venta): void
    {
        $this->info("Venta #{$venta->id} · {$venta->numero} · {$venta->tipo_comprobante} · "
            . "{$venta->moneda} {$venta->total} · cliente: " . ($venta->cliente?->getNombreCompletoAttribute() ?? '—'));
    }

    private function pct(int $parte, int $total): string
    {
        return $total > 0 ? number_format($parte * 100 / $total, 1) . '%' : '—';
    }

    /**
     * Agrupa los fallos por su "forma" y no por su texto literal: los mensajes llevan
     * números y nombres concretos, y sin normalizarlos cada fallo parecería único.
     *
     * @param  list<array{0: int, 1: ?string, 2: string, 3: string}> $fallidas
     * @return array<string, int>
     */
    private function agrupar(array $fallidas): array
    {
        $grupos = [];

        foreach ($fallidas as $fila) {
            $clave = preg_replace(['/\d+[.,]?\d*/', '/«[^»]*»/u'], ['N', '«…»'], $fila[3]);
            $clave = mb_substr(trim((string) $clave), 0, 120);
            $grupos[$clave] = ($grupos[$clave] ?? 0) + 1;
        }

        arsort($grupos);

        return $grupos;
    }
}
