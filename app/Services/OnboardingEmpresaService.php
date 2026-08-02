<?php

namespace App\Services;

use App\Models\Almacen;
use App\Models\Caja;
use App\Models\Cliente;
use App\Models\DevolucionMotivo;
use App\Models\Empresa;
use App\Models\Local;
use App\Models\MetodoPago;
use App\Models\Modulo;
use App\Models\Permiso;
use App\Models\Rol;
use App\Models\TipoMetodoPago;
use App\Models\UnidadMedida;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Alta de una empresa nueva desde el panel del superadmin (/admin), sin
 * seeders ni tinker. Reproduce la infraestructura mínima que los seeders de
 * referencia (FerreteriaHYCSeeder / ImportadoraTestSeeder) siembran para que
 * la empresa quede OPERATIVA desde el primer login de su admin:
 *
 *   empresa → local → caja → almacén → roles (Administrador + Cajera con su
 *   matriz de permisos) → usuario admin → unidad base → Cliente General →
 *   métodos de pago → cuenta Efectivo → motivos de devolución.
 *
 * Todo lo demás (cuentas bancarias, catálogo, más usuarios, flags finos de
 * configuración) lo gestiona el admin de la empresa desde su propio panel.
 */
class OnboardingEmpresaService
{
    /**
     * Permisos del rol Cajera por defecto. Misma matriz que los seeders de
     * referencia: vende, cobra, maneja turno y consulta catálogo/stock.
     */
    private const MATRIZ_CAJERA = [
        'pos'                => ['ver' => true, 'crear' => true, 'editar' => false, 'eliminar' => false],
        'ventas'             => ['ver' => true, 'crear' => true, 'editar' => false, 'eliminar' => false],
        'turnos'             => ['ver' => true, 'crear' => true, 'editar' => true,  'eliminar' => false],
        'clientes'           => ['ver' => true, 'crear' => true, 'editar' => true,  'eliminar' => false],
        'devoluciones'       => ['ver' => true, 'crear' => true, 'editar' => false, 'eliminar' => false],
        'gastos'             => ['ver' => true, 'crear' => true, 'editar' => false, 'eliminar' => false],
        'catalogo.productos' => ['ver' => true, 'crear' => false, 'editar' => false, 'eliminar' => false],
        'inventario.stock'   => ['ver' => true, 'crear' => false, 'editar' => false, 'eliminar' => false],
    ];

    private const METODOS_PAGO = [
        ['nombre' => 'Efectivo',      'slug' => 'efectivo',       'vuelto' => true],
        ['nombre' => 'Tarjeta',       'slug' => 'tarjeta_debito', 'vuelto' => false],
        ['nombre' => 'Yape',          'slug' => 'yape',           'vuelto' => false],
        ['nombre' => 'Plin',          'slug' => 'plin',           'vuelto' => false],
        ['nombre' => 'Transferencia', 'slug' => 'transferencia',  'vuelto' => false],
    ];

    private const MOTIVOS_DEVOLUCION = [
        ['nombre' => 'Producto equivocado',     'slug' => 'producto_equivocado', 'afecta' => 'permite',      'orden' => 10],
        ['nombre' => 'Talla/tamaño incorrecto', 'slug' => 'talla_incorrecta',    'afecta' => 'permite',      'orden' => 20],
        ['nombre' => 'No le gustó al cliente',  'slug' => 'no_gusto',            'afecta' => 'permite',      'orden' => 30],
        ['nombre' => 'Defecto de fábrica',      'slug' => 'defecto_fabrica',     'afecta' => 'obliga_merma', 'orden' => 40],
        ['nombre' => 'Vencido',                 'slug' => 'vencido',             'afecta' => 'obliga_merma', 'orden' => 50],
        ['nombre' => 'Otro',                    'slug' => 'otro',                'afecta' => 'permite',      'orden' => 99],
    ];

    /**
     * @param array $datosEmpresa razon_social, ruc y opcionales (nombre_comercial, direccion, telefono, email)
     * @param array $datosAdmin   name, email, password del primer administrador
     */
    public function crear(array $datosEmpresa, array $datosAdmin): Empresa
    {
        return DB::transaction(function () use ($datosEmpresa, $datosAdmin) {
            $empresa = Empresa::create([
                ...$datosEmpresa,
                'activo'                          => true,
                'tasa_igv'                        => 18.00,
                'modo_almacen'                    => 'simple',
                'descuenta_stock_en_venta'        => true,
                'modo_cierre_caja'                => 'con_declaraciones',
                'modo_cierre_inventario'          => 'por_venta',
                'usa_fondos_iniciales'            => true,
                'fondos_iniciales_en_declaracion' => false,
                'requiere_consolidacion_caja'     => false,
                'permite_devoluciones'            => true,
                'dias_max_devolucion'             => 15,
                'requiere_aprobacion_devolucion'  => false,
                'restock_default'                 => true,
                'usa_agenda'                      => false,
            ]);

            $local = Local::create([
                'empresa_id'   => $empresa->id,
                'nombre'       => 'Tienda Principal',
                'direccion'    => $empresa->direccion,
                'telefono'     => $empresa->telefono,
                'es_principal' => true,
                'activo'       => true,
            ]);

            Caja::create([
                'empresa_id'                => $empresa->id,
                'local_id'                  => $local->id,
                'nombre'                    => 'Caja Principal',
                'caja_chica_activa'         => false,
                'caja_chica_monto_sugerido' => 0,
                'caja_chica_en_arqueo'      => false,
                'activo'                    => true,
            ]);

            Almacen::create([
                'empresa_id' => $empresa->id,
                'local_id'   => $local->id,
                'nombre'     => 'Tienda Principal',
                'tipo'       => 'local', // modo simple usa tipo='local'
                'activo'     => true,
            ]);

            $rolAdmin = Rol::create([
                'empresa_id'               => $empresa->id,
                'nombre'                   => 'Administrador',
                'descripcion'              => 'Acceso total',
                'es_admin'                 => true,
                'activo'                   => true,
                'max_descuento_porcentaje' => null,
            ]);

            $rolCajera = Rol::create([
                'empresa_id'               => $empresa->id,
                'nombre'                   => 'Cajera',
                'descripcion'              => 'Vende, cobra, abre y cierra turno.',
                'es_admin'                 => false,
                'activo'                   => true,
                'max_descuento_porcentaje' => 10.00,
            ]);

            $modulosPorSlug = Modulo::pluck('id', 'slug');
            foreach (self::MATRIZ_CAJERA as $slug => $acciones) {
                if (!isset($modulosPorSlug[$slug])) continue;
                Permiso::create([
                    'rol_id'    => $rolCajera->id,
                    'modulo_id' => $modulosPorSlug[$slug],
                    ...$acciones,
                ]);
            }

            $admin = new User([
                'empresa_id' => $empresa->id,
                'local_id'   => $local->id,
                'rol_id'     => $rolAdmin->id,
                'name'       => $datosAdmin['name'],
                'email'      => $datosAdmin['email'],
                'password'   => $datosAdmin['password'],
                'activo'     => true,
            ]);
            // Sin flujo de verificación por correo: la cuenta la entrega el
            // proveedor en mano, igual que hacían los seeders.
            $admin->forceFill(['email_verified_at' => now()])->save();

            UnidadMedida::create([
                'empresa_id'  => $empresa->id,
                'nombre'      => 'Unidad',
                'abreviatura' => 'UND',
                'activo'      => true,
            ]);

            Cliente::create([
                'empresa_id'         => $empresa->id,
                'tipo_documento'     => 'DNI',
                'numero_documento'   => '99999999',
                'nombres'            => 'Cliente General',
                'apellidos'          => '',
                'activo'             => true,
                'es_cliente_general' => true,
            ]);

            $tipos = TipoMetodoPago::pluck('id', 'slug');
            foreach (self::METODOS_PAGO as $m) {
                if (!isset($tipos[$m['slug']])) continue;
                MetodoPago::create([
                    'empresa_id'    => $empresa->id,
                    'nombre'        => $m['nombre'],
                    'tipo_id'       => $tipos[$m['slug']],
                    'admite_vuelto' => $m['vuelto'],
                    'activo'        => true,
                ]);
            }

            TesoreriaService::efectivo($empresa->id);

            foreach (self::MOTIVOS_DEVOLUCION as $d) {
                DevolucionMotivo::create([
                    'empresa_id'             => $empresa->id,
                    'slug'                   => $d['slug'],
                    'nombre'                 => $d['nombre'],
                    'afecta_restock_default' => $d['afecta'],
                    'es_sistema'             => true,
                    'activo'                 => true,
                    'orden'                  => $d['orden'],
                ]);
            }

            return $empresa;
        });
    }
}
