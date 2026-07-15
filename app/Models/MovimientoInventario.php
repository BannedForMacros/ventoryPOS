<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Ledger / kardex de inventario. UNA fila por cada movimiento de stock del
 * sistema (entrada, salida, venta, devolución, transferencia, cierre, ajuste).
 *
 * Es una tabla de SOLO LECTURA para la app: se escribe exclusivamente desde
 * Stock::ajustar() (post-commit, sin bloquear el flujo) y desde el comando
 * `kardex:reconstruir`. Nadie la consulta para calcular stock/costos reales;
 * el stock canónico sigue viviendo en la tabla `stock`. Este ledger existe
 * solo para trazabilidad y para el Reporte de Kardex.
 */
class MovimientoInventario extends Model
{
    protected $table = 'movimientos_inventario';

    protected $fillable = [
        'empresa_id',
        'almacen_id',
        'producto_id',
        'fecha',
        'tipo',
        'referencia_tipo',
        'referencia_id',
        'documento',
        'cantidad',
        'costo_unitario',
        'costo_promedio',
        'saldo_cantidad',
        'saldo_valorizado',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'fecha'            => 'datetime',
            'cantidad'         => 'decimal:4',
            'costo_unitario'   => 'decimal:4',
            'costo_promedio'   => 'decimal:4',
            'saldo_cantidad'   => 'decimal:4',
            'saldo_valorizado' => 'decimal:4',
        ];
    }

    public function almacen(): BelongsTo
    {
        return $this->belongsTo(Almacen::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Etiqueta legible del tipo de movimiento para el reporte. */
    public static function etiquetaTipo(string $tipo): string
    {
        return match ($tipo) {
            'inventario_inicial'         => 'Inventario inicial',
            'entrada'                    => 'Entrada',
            'entrada_reverso'            => 'Entrada (reverso)',
            'entrada_edicion'            => 'Entrada (edición)',
            'salida'                     => 'Salida',
            'venta'                      => 'Venta',
            'venta_anulacion'            => 'Venta (anulación)',
            'devolucion'                 => 'Devolución',
            'devolucion_reverso'         => 'Devolución (reverso)',
            'transferencia_envio'        => 'Transferencia (envío)',
            'transferencia_recepcion'    => 'Transferencia (recepción)',
            'transferencia_reverso'      => 'Transferencia (reverso)',
            'transferencia_reaplicacion' => 'Transferencia (reaplicación)',
            'cierre'                     => 'Cierre de inventario',
            'entrega_pendiente'          => 'Entrega de pendiente',
            default                      => 'Ajuste',
        };
    }
}
