<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntradaDetalle extends Model
{
    protected $table = 'entradas_detalle';

    protected $fillable = [
        'entrada_id',
        'producto_id',
        'unidad_medida_id',
        'cantidad',
        'factor_conversion',
        'cantidad_base',
        'precio_costo',
        'subtotal',
        'numero_documento',
    ];

    protected function casts(): array
    {
        return [
            'cantidad'          => 'decimal:4',
            'factor_conversion' => 'decimal:4',
            'cantidad_base'     => 'decimal:4',
            'precio_costo'      => 'decimal:4',
            'subtotal'          => 'decimal:2',
        ];
    }

    /**
     * `precio_costo` es el precio de UNA PRESENTACIÓN tal como se compró (una
     * caja de 12, un saco): subtotal = cantidad × precio_costo. El stock, el
     * costo promedio y el costo de venta van por UNIDAD BASE, así que al
     * inventario entra precio_costo / factor_conversion.
     *
     * Antes se usaba precio_costo directo junto a cantidad_base: una caja de 12
     * a S/ 120 dejaba el costo promedio en S/ 120 por UNIDAD (12 veces más).
     */
    public static function costoPorUnidadBase(float $precioCosto, float $factor): float
    {
        return $factor > 0 ? $precioCosto / $factor : $precioCosto;
    }

    public function costoBase(): float
    {
        return self::costoPorUnidadBase((float) $this->precio_costo, (float) $this->factor_conversion);
    }

    public function entrada(): BelongsTo
    {
        return $this->belongsTo(Entrada::class);
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    public function unidadMedida(): BelongsTo
    {
        return $this->belongsTo(UnidadMedida::class);
    }
}
