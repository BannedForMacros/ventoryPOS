<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Empresa extends Model
{
    /** URL pública del logo y mapa de "Afecta caja" — se serializan al frontend. */
    protected $appends = ['logo_url', 'afecta_caja'];

    protected $fillable = [
        'razon_social',
        'nombre_comercial',
        'ruc',
        'direccion',
        'telefono',
        'email',
        'logo',
        // Plantilla del ticket impreso (JSON): qué campos salen y textos libres.
        'ticket_config',
        'activo',
        'tasa_igv',
        'modo_almacen',
        'descuenta_stock_en_venta',
        'permite_stock_negativo',
        'permite_duplicar_items_venta',
        'modo_cierre_caja',
        'modo_cierre_inventario',
        'cierre_precarga_stock',
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
        // Manejo de ventas (configurable por empresa)
        'venta_edicion_minutos',
        'venta_edicion_con_contador',
        'cajera_puede_editar',
        'cajera_puede_anular',
        // Despacho en almacén: el stock solo descuenta al confirmar el despacho.
        'usa_despacho_almacen',
        // Manejo de efectivo (todo opt-in; defaults = comportamiento clásico)
        'modo_apertura_caja',
        'apertura_editable',
        'usa_retiros_caja',
        'retiro_requiere_aprobacion',
        'cierre_pregunta_destino',
        'usa_caja_grande',
        // Mercadería en tránsito: comprada/facturada pero que todavía no llega.
        'usa_mercaderia_transito',
        'vende_mercaderia_transito',
        // "Afecta caja" configurable por módulo (JSON). Ver App\Support\AfectaCaja.
        'afecta_caja_config',
    ];

    protected function casts(): array
    {
        return [
            'activo'                          => 'boolean',
            'ticket_config'                   => 'array',
            'tasa_igv'                        => 'decimal:2',
            'descuenta_stock_en_venta'        => 'boolean',
            'permite_stock_negativo'          => 'boolean',
            'permite_duplicar_items_venta'    => 'boolean',
            'cierre_precarga_stock'           => 'boolean',
            'usa_fondos_iniciales'            => 'boolean',
            'fondos_iniciales_en_declaracion' => 'boolean',
            'requiere_consolidacion_caja'     => 'boolean',
            'permite_devoluciones'            => 'boolean',
            'requiere_aprobacion_devolucion'  => 'boolean',
            'restock_default'                 => 'boolean',
            'usa_agenda'                      => 'boolean',
            'agenda_sujeto_requerido'         => 'boolean',
            'venta_edicion_minutos'           => 'integer',
            'venta_edicion_con_contador'      => 'boolean',
            'cajera_puede_editar'             => 'boolean',
            'cajera_puede_anular'             => 'boolean',
            'usa_despacho_almacen'            => 'boolean',
            'apertura_editable'               => 'boolean',
            'usa_retiros_caja'                => 'boolean',
            'retiro_requiere_aprobacion'      => 'boolean',
            'cierre_pregunta_destino'         => 'boolean',
            'usa_caja_grande'                 => 'boolean',
            'usa_mercaderia_transito'         => 'boolean',
            'vende_mercaderia_transito'       => 'boolean',
            'afecta_caja_config'              => 'array',
        ];
    }

    /**
     * Mapa resuelto de "Afecta caja" por módulo (defaults ∪ config guardada),
     * con metadatos para la UI. Se serializa al frontend: el componente
     * <AfectaCajaSelect> y la pantalla de Configuración leen de aquí.
     */
    protected function afectaCaja(): Attribute
    {
        return Attribute::make(
            get: fn () => \App\Support\AfectaCaja::resuelto($this),
        );
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
