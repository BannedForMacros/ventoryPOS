<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kardex de inventario. UNA fila por cada movimiento de stock del sistema
 * (entrada, salida, venta, entrega, devolución, transferencia, cierre, ajuste).
 *
 * Es la fuente de verdad del inventario: la tabla `stock` es su última fila.
 * Se escribe solo desde Stock::ajustar() (en la misma transacción del
 * movimiento) y desde el motor único KardexService, que lo rearma desde los
 * documentos cuando un movimiento rompe el orden cronológico.
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
            'ajuste_ingreso'             => 'Ajuste (+)',
            'ajuste_salida'              => 'Ajuste (−)',
            default                      => 'Ajuste',
        };
    }
}
