<?php

namespace App\Services;

use App\Models\Empresa;
use App\Models\Local;
use App\Models\Producto;

/**
 * Resuelve configuración operacional con jerarquía:
 *   Producto (override individual) → Local (override de empresa) → Empresa (default)
 *
 * Reglas:
 *   - controla_stock: si producto.tipo='servicio' SIEMPRE retorna false (no controla stock).
 *     De lo contrario, si producto.controla_stock no es null, usa eso.
 *     Si no, hereda de la decisión local/empresa.
 *
 *   - descuenta_stock_en_venta y modo_cierre_caja:
 *     si local define un valor, lo usa; si no, usa el de empresa.
 */
class ConfiguracionOperacionService
{
    /**
     * Determina si una venta de este producto en este local debe descontar stock.
     *
     * Servicios nunca descuentan stock (no hay nada que descontar).
     * Productos físicos respetan: producto.controla_stock || local || empresa.
     */
    public function deboDescontarStock(Producto $producto, ?Local $local = null): bool
    {
        if ($producto->esServicio()) {
            return false;
        }

        if ($producto->controla_stock !== null) {
            return (bool) $producto->controla_stock;
        }

        return $this->descuentaStockResuelto($producto->empresa_id, $local);
    }

    /**
     * Resuelve el flag descuenta_stock_en_venta a nivel de local/empresa.
     * No considera el override por producto.
     */
    public function descuentaStockResuelto(int $empresaId, ?Local $local = null): bool
    {
        if ($local && $local->descuenta_stock_en_venta !== null) {
            return (bool) $local->descuenta_stock_en_venta;
        }

        $empresa = Empresa::findOrFail($empresaId);
        return (bool) $empresa->descuenta_stock_en_venta;
    }

    /**
     * Resuelve el modo de cierre de caja efectivo para un local.
     * Retorna 'rapido' | 'con_declaraciones' | 'completo'.
     */
    public function modoCierreCaja(Local $local): string
    {
        if ($local->modo_cierre_caja !== null) {
            return $local->modo_cierre_caja;
        }

        $empresa = $local->empresa()->firstOrFail();
        return $empresa->modo_cierre_caja ?? 'con_declaraciones';
    }

    /**
     * true si el cierre de turno requiere arqueo de denominaciones + métodos.
     */
    public function requiereArqueo(Local $local): bool
    {
        return in_array($this->modoCierreCaja($local), ['con_declaraciones', 'completo'], true);
    }

    /**
     * true si el cierre de turno además incluye paso de cierre de inventario.
     */
    public function requiereCierreInventarioEnTurno(Local $local): bool
    {
        return $this->modoCierreCaja($local) === 'completo';
    }
}
