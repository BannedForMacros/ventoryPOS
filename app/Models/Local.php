<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Local extends Model
{
    protected $table = 'locales';

    protected $fillable = [
        'empresa_id',
        'nombre',
        'direccion',
        'telefono',
        'es_principal',
        'activo',
        'descuenta_stock_en_venta',
        'modo_cierre_caja',
        'modo_cierre_inventario',
        'usa_fondos_iniciales',
        'fondos_iniciales_en_declaracion',
        'permite_devoluciones',
        'dias_max_devolucion',
        'requiere_aprobacion_devolucion',
        'restock_default',
    ];

    protected function casts(): array
    {
        return [
            'es_principal'                    => 'boolean',
            'activo'                          => 'boolean',
            'descuenta_stock_en_venta'        => 'boolean',
            'usa_fondos_iniciales'            => 'boolean',
            'fondos_iniciales_en_declaracion' => 'boolean',
            'permite_devoluciones'            => 'boolean',
            'requiere_aprobacion_devolucion'  => 'boolean',
            'restock_default'                 => 'boolean',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function almacenes(): HasMany
    {
        return $this->hasMany(Almacen::class);
    }

    public function cajas(): HasMany
    {
        return $this->hasMany(Caja::class);
    }
}
