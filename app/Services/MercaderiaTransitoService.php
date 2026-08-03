<?php

namespace App\Services;

use App\Models\Empresa;
use App\Models\Entrada;
use Illuminate\Support\Facades\DB;

/**
 * Mercadería comprada que todavía no llega (entradas en estado en_transito).
 *
 * Vive aparte del stock a propósito: `stock.cantidad` es lo que está FÍSICAMENTE
 * en el almacén y nada debe ensuciarlo. Lo que viene en camino se consulta como
 * un dato paralelo, y solo la empresa que lo habilita lo ve.
 */
class MercaderiaTransitoService
{
    /** ¿La empresa habilitó el módulo de mercadería en tránsito? */
    public function habilitado(?Empresa $empresa): bool
    {
        return (bool) $empresa?->usa_mercaderia_transito;
    }

    /** ¿Puede sobrevender contra lo que viene en camino? Exige el módulo activo. */
    public function permiteVender(?Empresa $empresa): bool
    {
        return $this->habilitado($empresa) && (bool) $empresa->vende_mercaderia_transito;
    }

    /**
     * Lo que viene en camino a un almacén, por producto y en unidad BASE (que es
     * la unidad en la que el POS razona el stock).
     *
     * @return array<int, array{cantidad: float, fecha: string|null}>
     *         producto_id => [cantidad en camino, fecha más próxima de llegada]
     */
    public function porAlmacen(int $empresaId, int $almacenId): array
    {
        $filas = DB::table('entradas as e')
            ->join('entradas_detalle as d', 'd.entrada_id', '=', 'e.id')
            ->where('e.empresa_id', $empresaId)
            ->where('e.almacen_id', $almacenId)
            ->where('e.estado', Entrada::ESTADO_EN_TRANSITO)
            ->groupBy('d.producto_id')
            ->select([
                'd.producto_id',
                DB::raw('SUM(d.cantidad_base) as cantidad'),
                // La fecha que le importa al vendedor es la del primer camión que
                // llega, no la del último.
                DB::raw('MIN(e.fecha_estimada_llegada) as fecha'),
            ])
            ->get();

        $mapa = [];
        foreach ($filas as $f) {
            $cantidad = round((float) $f->cantidad, 4);
            if ($cantidad <= 0) continue;

            $mapa[(int) $f->producto_id] = [
                'cantidad' => $cantidad,
                'fecha'    => $f->fecha,
            ];
        }

        return $mapa;
    }

    /**
     * Resumen para avisos: cuántas entradas vienen en camino y cuántas ya se
     * pasaron de la fecha prometida.
     *
     * @return array{en_camino: int, atrasadas: int, valor: float}
     */
    public function resumen(int $empresaId): array
    {
        $hoy = now()->startOfDay()->toDateString();

        $base = Entrada::deEmpresa($empresaId)->enTransito();

        return [
            'en_camino' => (clone $base)->count(),
            'atrasadas' => (clone $base)->whereNotNull('fecha_estimada_llegada')
                ->whereDate('fecha_estimada_llegada', '<', $hoy)->count(),
            'valor'     => round((float) (clone $base)->sum('total'), 2),
        ];
    }
}
