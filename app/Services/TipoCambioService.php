<?php

namespace App\Services;

use App\Models\TipoCambio;
use Illuminate\Support\Carbon;

/**
 * Tipo de cambio (PEN base). Regla: el TC se CONGELA el día del registro.
 *
 * - tasaPara(fecha, moneda): busca en tipos_cambio; si falta lo trae de la SBS
 *   (Decolecta), lo PERSISTE y lo devuelve. Así un monto de USD registrado con
 *   fecha pasada usa el TC de esa fecha, nunca el de hoy.
 * - Si la SBS no publicó ese día (fin de semana / feriado), usa el último TC
 *   previo publicado.
 * - convertirAPen(): devuelve el trío para guardar junto al monto en soles.
 */
class TipoCambioService
{
    /** Cache en memoria por corrida (evita HTTP duplicado en el seeder). */
    private array $cache = [];

    public function __construct(private DecolectaService $decolecta) {}

    /** Tasa (soles por 1 unidad de $moneda) para una fecha. PEN → 1.0. */
    public function tasaPara(string $fecha, string $moneda = 'USD'): float
    {
        $moneda = strtoupper($moneda);
        $fecha  = substr($fecha, 0, 10);

        if ($moneda === 'PEN') {
            return 1.0;
        }

        $key = "{$fecha}:{$moneda}";
        if (isset($this->cache[$key])) {
            return $this->cache[$key];
        }

        // 1) ¿ya está guardado ese día?
        $fila = TipoCambio::where('fecha', $fecha)->where('moneda', $moneda)->first();
        if ($fila) {
            return $this->cache[$key] = (float) $fila->tasa;
        }

        // 2) traerlo de la SBS y persistirlo
        try {
            $r = $this->decolecta->tipoCambioSbs($moneda, $fecha);
            if (($r['tasa'] ?? 0) > 0) {
                $fila = TipoCambio::firstOrCreate(
                    ['fecha' => $fecha, 'moneda' => $moneda],
                    ['tasa' => $r['tasa'], 'fuente' => 'decolecta_sbs_accounting', 'raw' => $r['raw']],
                );
                return $this->cache[$key] = (float) $fila->tasa;
            }
        } catch (\Throwable $e) {
            // cae al fallback (SBS sin publicación ese día)
        }

        // 3) fallback: último TC previo publicado
        $previo = TipoCambio::where('moneda', $moneda)
            ->where('fecha', '<=', $fecha)
            ->orderByDesc('fecha')
            ->first();
        if ($previo) {
            return $this->cache[$key] = (float) $previo->tasa;
        }

        throw new \RuntimeException("No se pudo obtener tipo de cambio {$moneda} para {$fecha}.");
    }

    /** Tasa de hoy (para el POS). */
    public function tasaHoy(string $moneda = 'USD'): float
    {
        return $this->tasaPara(Carbon::now()->toDateString(), $moneda);
    }

    /**
     * Convierte un monto a soles congelando el TC.
     *
     * @return array{monto_pen: float, moneda: string, tipo_cambio: ?float, monto_moneda: ?float}
     *   monto_pen  → va a la columna de monto existente (siempre soles)
     *   moneda/tipo_cambio/monto_moneda → columnas nuevas (NULL/PEN para soles)
     */
    public function convertirAPen(float $monto, string $moneda, string $fecha, ?float $tasaForzada = null): array
    {
        $moneda = strtoupper($moneda);

        if ($moneda === 'PEN') {
            return ['monto_pen' => round($monto, 2), 'moneda' => 'PEN', 'tipo_cambio' => null, 'monto_moneda' => null];
        }

        $tasa = $tasaForzada && $tasaForzada > 0 ? $tasaForzada : $this->tasaPara($fecha, $moneda);

        return [
            'monto_pen'    => round($monto * $tasa, 2),
            'moneda'       => $moneda,
            'tipo_cambio'  => round($tasa, 6),
            'monto_moneda' => round($monto, 2),
        ];
    }
}
