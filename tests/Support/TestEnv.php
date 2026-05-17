<?php

namespace Tests\Support;

use App\Models\Almacen;
use App\Models\Caja;
use App\Models\Categoria;
use App\Models\Cliente;
use App\Models\DescuentoConcepto;
use App\Models\DevolucionMotivo;
use App\Models\Empresa;
use App\Models\Local;
use App\Models\MetodoPago;
use App\Models\Producto;
use App\Models\ProductoUnidad;
use App\Models\Rol;
use App\Models\Stock;
use App\Models\TipoMetodoPago;
use App\Models\Turno;
use App\Models\UnidadMedida;
use App\Models\User;

/**
 * Bootstrapper de entorno para tests. Crea una empresa completa con sus
 * dependencias mínimas para poder ejercitar el flujo POS (venta, devolución,
 * turno, agenda, IGV).
 *
 * Cada test que llame TestEnv::crear() recibe un escenario independiente
 * (empresa nueva con RUC único, admin nuevo con email único, etc.). Dado que
 * los tests corren con DatabaseTransactions, todo se hace rollback al final.
 */
class TestEnv
{
    public Empresa $empresa;
    public Local $local;
    public Caja $caja;
    public Almacen $almacen;
    public Rol $rolAdmin;
    public User $admin;
    public Cliente $clienteGeneral;
    public UnidadMedida $unidad;
    public Categoria $categoria;

    /** @var array<string, MetodoPago>  keyed by slug del tipo */
    public array $metodos = [];

    /** @var array<string, DevolucionMotivo> keyed by slug */
    public array $motivos = [];

    public DescuentoConcepto $descuentoConcepto;

    /**
     * @param array $opts {
     *   modo_almacen?: 'simple'|'central_y_local',
     *   tasa_igv?: float,
     *   permite_devoluciones?: bool,
     *   requiere_aprobacion_devolucion?: bool,
     *   descuenta_stock_en_venta?: bool,
     *   modo_cierre_caja?: 'rapido'|'con_declaraciones',
     *   modo_cierre_inventario?: 'por_venta'|'declarado',
     * }
     */
    public static function crear(array $opts = []): self
    {
        $self = new self();
        $suffix = uniqid('', false);

        // Empresa con defaults razonables; opts overridea
        $self->empresa = Empresa::create(array_merge([
            'razon_social'                    => "Test SAC {$suffix}",
            'nombre_comercial'                => "Test {$suffix}",
            'ruc'                             => substr('20' . str_pad((string) random_int(0, 99999999), 9, '0', STR_PAD_LEFT), 0, 11),
            'direccion'                       => 'Av. Test 123',
            'telefono'                        => '999999999',
            'email'                           => "test+{$suffix}@example.com",
            'activo'                          => true,
            'tasa_igv'                        => 18.00,
            'modo_almacen'                    => 'simple',
            'descuenta_stock_en_venta'        => true,
            'modo_cierre_caja'                => 'con_declaraciones',
            'modo_cierre_inventario'          => 'por_venta',
            'usa_fondos_iniciales'            => false,
            'fondos_iniciales_en_declaracion' => false,
            'permite_devoluciones'            => true,
            'dias_max_devolucion'             => 30,
            'requiere_aprobacion_devolucion'  => false,
            'restock_default'                 => true,
            'usa_agenda'                      => true,
            'agenda_sujeto_label'             => 'Mascota',
            'agenda_sujeto_requerido'         => false,
        ], $opts));

        // Local principal
        $self->local = Local::create([
            'empresa_id'   => $self->empresa->id,
            'nombre'       => 'Local Principal',
            'direccion'    => 'Av. Test 123',
            'telefono'     => '999999999',
            'es_principal' => true,
            'activo'       => true,
        ]);

        // Caja
        $self->caja = Caja::create([
            'empresa_id'                 => $self->empresa->id,
            'local_id'                   => $self->local->id,
            'nombre'                     => 'Caja 1',
            'caja_chica_activa'          => false,
            'caja_chica_monto_sugerido'  => 0,
            'caja_chica_en_arqueo'       => false,
            'activo'                     => true,
        ]);

        // Almacén local (tipo='local' porque modo_almacen por defecto es 'simple')
        $self->almacen = Almacen::create([
            'empresa_id' => $self->empresa->id,
            'local_id'   => $self->local->id,
            'nombre'     => 'Almacén Principal',
            'tipo'       => 'local',
            'activo'     => true,
        ]);

        // Rol admin (es_admin=true bypassea cualquier permiso)
        $self->rolAdmin = Rol::create([
            'empresa_id'  => $self->empresa->id,
            'nombre'      => 'Administrador',
            'descripcion' => 'Admin con todos los permisos',
            'es_admin'    => true,
            'activo'      => true,
        ]);

        // Usuario admin
        $self->admin = User::create([
            'empresa_id'        => $self->empresa->id,
            'local_id'          => $self->local->id,
            'rol_id'            => $self->rolAdmin->id,
            'name'              => 'Admin Test',
            'email'             => "admin+{$suffix}@test.com",
            'password'          => bcrypt('secret'),
            'email_verified_at' => now(),
            'activo'            => true,
        ]);

        // Cliente General (DNI 99999999) — VentaService::crear lo necesita
        // como fallback cuando no se pasa cliente_id.
        $self->clienteGeneral = Cliente::create([
            'empresa_id'       => $self->empresa->id,
            'tipo_documento'   => 'DNI', // enum nativo PG: DNI/RUC/CE/pasaporte/otro
            'numero_documento' => '99999999',
            'nombres'          => 'Cliente',
            'apellidos'        => 'General',
            'activo'           => true,
        ]);

        // Unidad de medida + Categoría defaults
        $self->unidad = UnidadMedida::create([
            'empresa_id'  => $self->empresa->id,
            'nombre'      => 'Unidad',
            'abreviatura' => 'UND',
            'activo'      => true,
        ]);

        $self->categoria = Categoria::create([
            'empresa_id'  => $self->empresa->id,
            'nombre'      => 'General',
            'descripcion' => 'Categoría por defecto',
            'activo'      => true,
        ]);

        // Métodos de pago — uno por cada tipo del catálogo global
        $self->seedMetodosPago();

        // Motivos de devolución
        $self->seedMotivos();

        // Descuento concepto base
        $self->descuentoConcepto = DescuentoConcepto::create([
            'empresa_id'           => $self->empresa->id,
            'nombre'               => 'Descuento general',
            'requiere_aprobacion'  => false,
            'activo'               => true,
        ]);

        return $self;
    }

    /**
     * Crea un producto físico con su unidad base + un stock inicial.
     *
     * @param array $opts {
     *   nombre?: string,
     *   codigo?: string,
     *   precio_venta?: float,
     *   precio_costo?: float,
     *   incluye_igv?: bool,
     *   controla_stock?: bool|null,
     *   es_retornable?: bool|null,
     *   tipo?: 'producto'|'servicio',
     *   stock_inicial?: float,  // cantidad base del stock
     *   activo?: bool,
     * }
     */
    public function crearProducto(array $opts = []): Producto
    {
        $sfx = uniqid('', false);
        $producto = Producto::create([
            'empresa_id'     => $this->empresa->id,
            'categoria_id'   => $this->categoria->id,
            'codigo'         => $opts['codigo']         ?? "P-{$sfx}",
            'nombre'         => $opts['nombre']         ?? "Producto {$sfx}",
            'descripcion'    => null,
            'tipo'           => $opts['tipo']           ?? 'producto',
            'tipo_precio'    => 'fijo',
            'precio_venta'   => $opts['precio_venta']   ?? 10.00,
            'precio_costo'   => $opts['precio_costo']   ?? 6.00,
            'activo'         => $opts['activo']         ?? true,
            'incluye_igv'    => $opts['incluye_igv']    ?? true,
            'controla_stock' => $opts['controla_stock'] ?? null,
            'es_retornable'  => $opts['es_retornable']  ?? null,
        ]);

        // Unidad base
        ProductoUnidad::create([
            'producto_id'       => $producto->id,
            'unidad_medida_id'  => $this->unidad->id,
            'es_base'           => true,
            'factor_conversion' => 1,
            'tipo_precio'       => 'fijo',
            'precio_venta'      => $opts['precio_venta'] ?? 10.00,
            'precio_costo'      => $opts['precio_costo'] ?? 6.00,
            'activo'            => true,
        ]);

        // Stock inicial — usar INSERT directo para no atravesar la lógica
        // de Stock::ajustar (que pediría costo, transacción anidada, etc.).
        $stockInicial = (float) ($opts['stock_inicial'] ?? 100);
        if ($producto->tipo === 'producto') {
            Stock::create([
                'almacen_id'     => $this->almacen->id,
                'producto_id'    => $producto->id,
                'cantidad'       => $stockInicial,
                'costo_promedio' => $opts['precio_costo'] ?? 6.00,
            ]);
        }

        return $producto->fresh(['unidadBase']);
    }

    /**
     * Abre un turno para el usuario indicado (o el admin por defecto).
     */
    public function abrirTurno(?User $user = null, float $apertura = 100.0): Turno
    {
        return Turno::create([
            'empresa_id'      => $this->empresa->id,
            'local_id'        => $this->local->id,
            'caja_id'         => $this->caja->id,
            'user_id'         => ($user ?? $this->admin)->id,
            'monto_apertura'  => $apertura,
            'monto_caja_chica'=> 0,
            'estado'          => 'abierto',
            'fecha_apertura'  => now(),
        ]);
    }

    public function metodo(string $slug): MetodoPago
    {
        return $this->metodos[$slug] ?? throw new \RuntimeException("Método '{$slug}' no inicializado");
    }

    public function motivo(string $slug): DevolucionMotivo
    {
        return $this->motivos[$slug] ?? throw new \RuntimeException("Motivo '{$slug}' no inicializado");
    }

    private function seedMetodosPago(): void
    {
        $tipos = TipoMetodoPago::pluck('id', 'slug');

        $defaults = [
            ['nombre' => 'Efectivo',      'slug' => 'efectivo',       'vuelto' => true],
            ['nombre' => 'Tarjeta',       'slug' => 'tarjeta_debito', 'vuelto' => false],
            ['nombre' => 'Yape',          'slug' => 'yape',           'vuelto' => false],
            ['nombre' => 'Plin',          'slug' => 'plin',           'vuelto' => false],
            ['nombre' => 'Transferencia', 'slug' => 'transferencia',  'vuelto' => false],
        ];

        foreach ($defaults as $d) {
            $tipoId = $tipos[$d['slug']] ?? null;
            if (!$tipoId) continue;

            $this->metodos[$d['slug']] = MetodoPago::create([
                'empresa_id'    => $this->empresa->id,
                'nombre'        => $d['nombre'],
                'tipo_id'       => $tipoId,
                'admite_vuelto' => $d['vuelto'],
                'activo'        => true,
            ]);
        }
    }

    private function seedMotivos(): void
    {
        $defaults = [
            ['slug' => 'producto_equivocado', 'nombre' => 'Producto equivocado', 'afecta' => 'permite',      'orden' => 10],
            ['slug' => 'defecto_fabrica',     'nombre' => 'Defecto de fábrica',  'afecta' => 'obliga_merma', 'orden' => 20],
            ['slug' => 'vencido',             'nombre' => 'Vencido',             'afecta' => 'obliga_merma', 'orden' => 30],
            ['slug' => 'no_gusto',            'nombre' => 'No le gustó',         'afecta' => 'permite',      'orden' => 40],
        ];

        foreach ($defaults as $d) {
            $this->motivos[$d['slug']] = DevolucionMotivo::create([
                'empresa_id'             => $this->empresa->id,
                'slug'                   => $d['slug'],
                'nombre'                 => $d['nombre'],
                'afecta_restock_default' => $d['afecta'],
                'es_sistema'             => true,
                'activo'                 => true,
                'orden'                  => $d['orden'],
            ]);
        }
    }
}
