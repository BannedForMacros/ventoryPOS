<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Empresa extends Model
{
    /** URL pública del logo (o null) — se serializa al frontend. */
    protected $appends = ['logo_url'];

    protected $fillable = [
        'razon_social',
        'nombre_comercial',
        'ruc',
        'direccion',
        'telefono',
        'email',
        'logo',
        'activo',
        'tasa_igv',
        'modo_almacen',
        'descuenta_stock_en_venta',
        'permite_stock_negativo',
        'modo_cierre_caja',
        'modo_cierre_inventario',
        'usa_fondos_iniciales',
        'fondos_iniciales_en_declaracion',
        'requiere_consolidacion_caja',
        'permite_devoluciones',
        'dias_max_devolucion',
        'requiere_aprobacion_devolucion',
        'restock_default',
        // Modulo Agenda multidisciplina
        'usa_agenda',
        'agenda_sujeto_label',
        'agenda_sujeto_requerido',
    ];

    protected function casts(): array
    {
        return [
            'activo'                          => 'boolean',
            'tasa_igv'                        => 'decimal:2',
            'descuenta_stock_en_venta'        => 'boolean',
            'permite_stock_negativo'          => 'boolean',
            'usa_fondos_iniciales'            => 'boolean',
            'fondos_iniciales_en_declaracion' => 'boolean',
            'requiere_consolidacion_caja'     => 'boolean',
            'permite_devoluciones'            => 'boolean',
            'requiere_aprobacion_devolucion'  => 'boolean',
            'restock_default'                 => 'boolean',
            'usa_agenda'                      => 'boolean',
            'agenda_sujeto_requerido'         => 'boolean',
        ];
    }

    /** URL pública del logo subido (o null si no hay). */
    protected function logoUrl(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->logo ? Storage::disk('public')->url($this->logo) : null,
        );
    }

    public function locales(): HasMany
    {
        return $this->hasMany(Local::class);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Rol::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function categorias(): HasMany
    {
        return $this->hasMany(Categoria::class);
    }

    public function unidadesMedida(): HasMany
    {
        return $this->hasMany(UnidadMedida::class);
    }

    public function productos(): HasMany
    {
        return $this->hasMany(Producto::class);
    }

    public function almacenes(): HasMany
    {
        return $this->hasMany(Almacen::class);
    }

    public function entradas(): HasMany
    {
        return $this->hasMany(Entrada::class);
    }

    public function transferencias(): HasMany
    {
        return $this->hasMany(Transferencia::class);
    }

    public function clientes(): HasMany
    {
        return $this->hasMany(Cliente::class);
    }

    public function metodosPago(): HasMany
    {
        return $this->hasMany(MetodoPago::class);
    }

    public function cuentas(): HasMany
    {
        return $this->hasMany(Cuenta::class);
    }

    public function usaModoSimple(): bool
    {
        return $this->modo_almacen === 'simple';
    }

    public function usaCentralYLocal(): bool
    {
        return $this->modo_almacen === 'central_y_local';
    }
}
