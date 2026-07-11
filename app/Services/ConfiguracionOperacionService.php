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
     * true si la empresa permite que una venta deje el stock en negativo.
     * Cuando esta activo, el POS no bloquea la venta por falta de stock:
     * el faltante queda como saldo negativo y se avisa al cierre de caja.
     */
    public function permiteStockNegativo(int $empresaId): bool
    {
        $empresa = Empresa::findOrFail($empresaId);
        return (bool) ($empresa->permite_stock_negativo ?? false);
    }

    /**
     * Resuelve el modo de cierre de caja efectivo para un local.
     * Retorna 'rapido' | 'con_declaraciones'.
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
     * Resuelve el modo de cierre de inventario efectivo para un local.
     * Retorna 'por_venta' | 'declarado'.
     *
     *  - por_venta: el stock se ajusta solo con cada venta. Sin acción al cerrar turno.
     *  - declarado: al cerrar turno se exige un cierre de inventario confirmado
     *               asociado al turno (el cajero declara stock real).
     */
    public function modoCierreInventario(Local $local): string
    {
        if ($local->modo_cierre_inventario !== null) {
            return $local->modo_cierre_inventario;
        }

        $empresa = $local->empresa()->firstOrFail();
        return $empresa->modo_cierre_inventario ?? 'por_venta';
    }

    /**
     * true si el cierre de turno requiere arqueo de denominaciones + métodos.
     */
    public function requiereArqueo(Local $local): bool
    {
        return $this->modoCierreCaja($local) === 'con_declaraciones';
    }

    /**
     * true si el cierre de turno además incluye paso de cierre de inventario.
     */
    public function requiereCierreInventarioEnTurno(Local $local): bool
    {
        return $this->modoCierreInventario($local) === 'declarado';
    }

    /**
     * true si el local pide registrar fondos iniciales (caja chica) al abrir el turno.
     */
    public function usaFondosIniciales(Local $local): bool
    {
        if ($local->usa_fondos_iniciales !== null) {
            return (bool) $local->usa_fondos_iniciales;
        }

        $empresa = $local->empresa()->firstOrFail();
        return (bool) $empresa->usa_fondos_iniciales;
    }

    /**
     * Resuelve si la empresa/local permite devoluciones.
     */
    public function permiteDevoluciones(Local $local): bool
    {
        if ($local->permite_devoluciones !== null) {
            return (bool) $local->permite_devoluciones;
        }
        $empresa = $local->empresa()->firstOrFail();
        return (bool) $empresa->permite_devoluciones;
    }

    /**
     * Días máximos para aceptar una devolución desde la fecha de venta.
     * 0 = sin límite.
     */
    public function diasMaxDevolucion(Local $local): int
    {
        if ($local->dias_max_devolucion !== null) {
            return (int) $local->dias_max_devolucion;
        }
        $empresa = $local->empresa()->firstOrFail();
        return (int) ($empresa->dias_max_devolucion ?? 0);
    }

    /**
     * true si las devoluciones requieren aprobación de un supervisor/admin.
     */
    public function requiereAprobacionDevolucion(Local $local): bool
    {
        if ($local->requiere_aprobacion_devolucion !== null) {
            return (bool) $local->requiere_aprobacion_devolucion;
        }
        $empresa = $local->empresa()->firstOrFail();
        return (bool) $empresa->requiere_aprobacion_devolucion;
    }

    /**
     * Valor default de "restock" para los detalles nuevos de devolución.
     */
    public function restockDefault(Local $local): bool
    {
        if ($local->restock_default !== null) {
            return (bool) $local->restock_default;
        }
        $empresa = $local->empresa()->firstOrFail();
        return (bool) $empresa->restock_default;
    }

    /**
     * Determina si un producto es retornable. Servicios siempre son no retornables.
     * producto.es_retornable null → true por defecto (la mayoría de productos retornan).
     */
    public function esRetornable(Producto $producto): bool
    {
        if ($producto->esServicio()) {
            return false;
        }
        if ($producto->es_retornable !== null) {
            return (bool) $producto->es_retornable;
        }
        return true;
    }

    /**
     * Verifica si la fecha de la venta está dentro del plazo permitido.
     */
    public function estaDentroDelPlazo(Local $local, \DateTimeInterface $fechaVenta): bool
    {
        $dias = $this->diasMaxDevolucion($local);
        if ($dias === 0) return true;

        $diff = (int) (new \DateTimeImmutable())->diff($fechaVenta)->days;
        return $diff <= $dias;
    }

    /**
     * true si los fondos iniciales se incluyen en la declaración del cierre
     * (es decir: el cajero también cuenta y declara los fondos al final).
     * Cuando es true, el monto esperado SUMA los fondos iniciales.
     */
    public function fondosInicialesEnDeclaracion(Local $local): bool
    {
        if (!$this->usaFondosIniciales($local)) {
            return false;
        }

        if ($local->fondos_iniciales_en_declaracion !== null) {
            return (bool) $local->fondos_iniciales_en_declaracion;
        }

        $empresa = $local->empresa()->firstOrFail();
        return (bool) $empresa->fondos_iniciales_en_declaracion;
    }
}
