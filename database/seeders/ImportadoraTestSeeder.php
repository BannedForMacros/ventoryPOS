<?php

namespace Database\Seeders;

use App\Models\Almacen;
use App\Models\Caja;
use App\Models\Categoria;
use App\Models\Cita;
use App\Models\CitaItem;
use App\Models\Cliente;
use App\Models\DescuentoConcepto;
use App\Models\DevolucionMotivo;
use App\Models\Empresa;
use App\Models\Entrada;
use App\Models\EntradaDetalle;
use App\Models\Local;
use App\Models\MetodoPago;
use App\Models\Modulo;
use App\Models\Permiso;
use App\Models\Producto;
use App\Models\ProductoUnidad;
use App\Models\Rol;
use App\Models\Stock;
use App\Models\TipoMetodoPago;
use App\Models\UnidadMedida;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeder destructivo para entorno de prueba MacSoft (importadora de bazar
 * en Chiclayo). Limpia toda la data de tenant y deja:
 *
 *   - 1 empresa: MacSoft E.I.R.L. (modo simple, agenda activa)
 *   - 1 local, 1 caja, 1 almacén
 *   - 2 usuarios:
 *       jesus@gmail.com  / 12345678  (rol Administrador, sin tope descuento)
 *       cajera@gmail.com / 12345678  (rol Cajera, tope 10% descuento)
 *   - 5 métodos de pago (Efectivo, Tarjeta, Yape, Plin, Transferencia)
 *   - 6 motivos de devolución estándar
 *   - 8 categorías + 50 productos físicos + 2 servicios (asesoría/maquillaje)
 *   - Stock inicial cargado vía 1 entrada confirmada
 *   - 6 clientes (incluido Cliente General)
 *   - 5 citas de prueba con servicios
 *
 * NO toca catálogos globales: módulos, tipos_metodo_pago.
 *
 * Correr:  php artisan db:seed --class=ImportadoraTestSeeder --force
 */
class ImportadoraTestSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->warn('Limpiando data tenant existente (esto borra TODAS las empresas y data asociada)...');

        DB::transaction(function () {
            $this->limpiarDataTenant();

            $this->command->info('Creando MacSoft E.I.R.L. ...');
            $empresa = $this->crearEmpresa();
            [$local, $caja, $almacen] = $this->crearInfraestructura($empresa);

            $this->command->info('Creando roles, permisos y usuarios ...');
            $rolAdmin  = $this->crearRolAdmin($empresa);
            $rolCajera = $this->crearRolCajera($empresa);
            [$admin, $cajera] = $this->crearUsuarios($empresa, $local, $rolAdmin, $rolCajera);

            $this->command->info('Sembrando métodos de pago, motivos de devolución y conceptos ...');
            $this->sembrarMetodosPago($empresa);
            $this->sembrarMotivosDevolucion($empresa);
            $this->sembrarDescuentoConceptos($empresa);

            $this->command->info('Creando unidad de medida, categorías y catálogo de 50 productos ...');
            $unidad       = $this->crearUnidad($empresa);
            $categorias   = $this->crearCategorias($empresa);
            $productos    = $this->crearCatalogoProductos($empresa, $unidad, $categorias);
            $servicios    = $this->crearServiciosAgenda($empresa, $unidad, $categorias);

            $this->command->info('Cargando stock inicial vía 1 entrada confirmada ...');
            $this->cargarStockInicial($empresa, $almacen, $admin, $productos);

            $this->command->info('Creando clientes de prueba ...');
            $clientes = $this->crearClientes($empresa);

            $this->command->info('Creando 5 citas de prueba ...');
            $this->crearCitas($empresa, $local, $admin, $clientes, $servicios);
        });

        $this->command->getOutput()->writeln('');
        $this->command->getOutput()->writeln('<bg=green;fg=white> LISTO </> MacSoft poblada para pruebas.');
        $this->command->getOutput()->writeln('');
        $this->command->getOutput()->writeln('  Usuarios:');
        $this->command->getOutput()->writeln('    <fg=yellow>jesus@gmail.com</>  / 12345678   (Administrador, sin tope)');
        $this->command->getOutput()->writeln('    <fg=yellow>cajera@gmail.com</> / 12345678   (Cajera, tope 10% descuento)');
        $this->command->getOutput()->writeln('');
    }

    /* ─── Limpieza ───────────────────────────────────────────────────────── */

    private function limpiarDataTenant(): void
    {
        // Desactivamos FKs temporalmente (Postgres) y truncamos en bloque.
        // RESTART IDENTITY resetea autoincrement; CASCADE no es necesario porque
        // listamos todas las tablas explícitamente en orden de hijo→padre.
        $tablas = [
            'auditoria',
            'venta_pagos', 'venta_items', 'descuentos_log', 'ventas',
            'devolucion_pagos', 'devoluciones_detalle', 'devoluciones',
            'cita_items', 'citas',
            'turno_arqueo_metodos', 'turno_arqueo', 'turno_cierre_productos', 'turnos',
            'gastos', 'gasto_conceptos', 'gasto_tipos',
            'cierres_inventario_items', 'cierres_inventario',
            'transferencias_detalle', 'transferencias',
            'salidas_detalle', 'salidas',
            'entradas_detalle', 'entradas',
            'stock', 'producto_unidades', 'productos',
            'almacenes',
            'descuento_conceptos', 'devolucion_motivos', 'salida_tipos',
            'cuenta_metodo_pago', 'cuentas', 'metodos_pago',
            'clientes', 'proveedores',
            'cajas',
            'permisos',
            'users',
            'roles',
            'unidades_medida', 'categorias',
            'locales',
            'empresas',
        ];

        DB::statement("SET session_replication_role = 'replica'");
        foreach ($tablas as $t) {
            DB::statement("TRUNCATE TABLE {$t} RESTART IDENTITY CASCADE");
        }
        DB::statement("SET session_replication_role = 'origin'");
    }

    /* ─── Empresa + infra ────────────────────────────────────────────────── */

    private function crearEmpresa(): Empresa
    {
        return Empresa::create([
            'razon_social'                    => 'MacSoft E.I.R.L.',
            'nombre_comercial'                => 'MacSoft Importaciones',
            'ruc'                             => '20612345678',
            'direccion'                       => 'Av. Balta 850, Chiclayo, Lambayeque',
            'telefono'                        => '+51 974 123 456',
            'email'                           => 'macsoft@gmail.com',
            'activo'                          => true,
            'tasa_igv'                        => 18.00,
            'modo_almacen'                    => 'simple',
            'descuenta_stock_en_venta'        => true,
            'modo_cierre_caja'                => 'con_declaraciones',
            'modo_cierre_inventario'          => 'por_venta',
            'usa_fondos_iniciales'            => true,
            'fondos_iniciales_en_declaracion' => false,
            'permite_devoluciones'            => true,
            'dias_max_devolucion'             => 15,
            'requiere_aprobacion_devolucion'  => false,
            'restock_default'                 => true,
            'usa_agenda'                      => true,
            'agenda_sujeto_label'             => null, // no usa sujeto extra (es para vet)
            'agenda_sujeto_requerido'         => false,
        ]);
    }

    /** @return array{0: Local, 1: Caja, 2: Almacen} */
    private function crearInfraestructura(Empresa $empresa): array
    {
        $local = Local::create([
            'empresa_id'   => $empresa->id,
            'nombre'       => 'Tienda Chiclayo',
            'direccion'    => 'Av. Balta 850',
            'telefono'     => '+51 974 123 456',
            'es_principal' => true,
            'activo'       => true,
        ]);

        $caja = Caja::create([
            'empresa_id'                => $empresa->id,
            'local_id'                  => $local->id,
            'nombre'                    => 'Caja Principal',
            'caja_chica_activa'         => true,
            'caja_chica_monto_sugerido' => 50.00,
            'caja_chica_en_arqueo'      => false,
            'activo'                    => true,
        ]);

        $almacen = Almacen::create([
            'empresa_id' => $empresa->id,
            'local_id'   => $local->id,
            'nombre'     => 'Tienda Chiclayo',
            'tipo'       => 'local', // modo simple usa tipo='local'
            'activo'     => true,
        ]);

        return [$local, $caja, $almacen];
    }

    /* ─── Roles, permisos, usuarios ──────────────────────────────────────── */

    private function crearRolAdmin(Empresa $empresa): Rol
    {
        // es_admin=true bypassea cualquier verificación de permisos.
        // max_descuento_porcentaje=NULL => sin tope (puede dar el descuento que quiera).
        return Rol::create([
            'empresa_id'               => $empresa->id,
            'nombre'                   => 'Administrador',
            'descripcion'              => 'Dueña — acceso total',
            'es_admin'                 => true,
            'activo'                   => true,
            'max_descuento_porcentaje' => null,
        ]);
    }

    private function crearRolCajera(Empresa $empresa): Rol
    {
        $rol = Rol::create([
            'empresa_id'               => $empresa->id,
            'nombre'                   => 'Cajera',
            'descripcion'              => 'Vende, cobra, abre y cierra turno. No edita catálogo ni configuración.',
            'es_admin'                 => false,
            'activo'                   => true,
            'max_descuento_porcentaje' => 10.00, // máx 10% por venta, después pide aprobación
        ]);

        // Permisos explícitos para la cajera. Resto de módulos: sin permiso.
        $matrizCajera = [
            'pos'                => ['ver' => true, 'crear' => true, 'editar' => false, 'eliminar' => false],
            'ventas'             => ['ver' => true, 'crear' => true, 'editar' => false, 'eliminar' => false],
            'turnos'             => ['ver' => true, 'crear' => true, 'editar' => true,  'eliminar' => false],
            'clientes'           => ['ver' => true, 'crear' => true, 'editar' => true,  'eliminar' => false],
            'devoluciones'       => ['ver' => true, 'crear' => true, 'editar' => false, 'eliminar' => false],
            'gastos'             => ['ver' => true, 'crear' => true, 'editar' => false, 'eliminar' => false],
            'agenda'             => ['ver' => true, 'crear' => true, 'editar' => true,  'eliminar' => false],
            'catalogo.productos' => ['ver' => true, 'crear' => false,'editar' => false, 'eliminar' => false],
            'inventario.stock'   => ['ver' => true, 'crear' => false,'editar' => false, 'eliminar' => false],
        ];

        $modulosPorSlug = Modulo::pluck('id', 'slug');
        foreach ($matrizCajera as $slug => $acciones) {
            $moduloId = $modulosPorSlug[$slug] ?? null;
            if (!$moduloId) continue;

            Permiso::create([
                'rol_id'    => $rol->id,
                'modulo_id' => $moduloId,
                ...$acciones,
            ]);
        }

        return $rol;
    }

    /** @return array{0: User, 1: User} */
    private function crearUsuarios(Empresa $empresa, Local $local, Rol $admin, Rol $cajera): array
    {
        $jesus = User::create([
            'empresa_id'        => $empresa->id,
            'local_id'          => $local->id,
            'rol_id'            => $admin->id,
            'name'              => 'Jesús',
            'email'             => 'jesus@gmail.com',
            'password'          => bcrypt('12345678'),
            'email_verified_at' => now(),
            'activo'            => true,
        ]);

        $cajeraUser = User::create([
            'empresa_id'        => $empresa->id,
            'local_id'          => $local->id,
            'rol_id'            => $cajera->id,
            'name'              => 'Cajera',
            'email'             => 'cajera@gmail.com',
            'password'          => bcrypt('12345678'),
            'email_verified_at' => now(),
            'activo'            => true,
        ]);

        return [$jesus, $cajeraUser];
    }

    /* ─── Métodos de pago, motivos, descuentos ───────────────────────────── */

    private function sembrarMetodosPago(Empresa $empresa): void
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

            MetodoPago::create([
                'empresa_id'    => $empresa->id,
                'nombre'        => $d['nombre'],
                'tipo_id'       => $tipoId,
                'admite_vuelto' => $d['vuelto'],
                'activo'        => true,
            ]);
        }
    }

    private function sembrarMotivosDevolucion(Empresa $empresa): void
    {
        $defaults = [
            ['nombre' => 'Producto equivocado',     'slug' => 'producto_equivocado', 'afecta' => 'permite',      'orden' => 10],
            ['nombre' => 'Talla/tamaño incorrecto', 'slug' => 'talla_incorrecta',    'afecta' => 'permite',      'orden' => 20],
            ['nombre' => 'No le gustó al cliente',  'slug' => 'no_gusto',            'afecta' => 'permite',      'orden' => 30],
            ['nombre' => 'Defecto de fábrica',      'slug' => 'defecto_fabrica',     'afecta' => 'obliga_merma', 'orden' => 40],
            ['nombre' => 'Vencido',                 'slug' => 'vencido',             'afecta' => 'obliga_merma', 'orden' => 50],
            ['nombre' => 'Otro',                    'slug' => 'otro',                'afecta' => 'permite',      'orden' => 99],
        ];

        foreach ($defaults as $d) {
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
    }

    private function sembrarDescuentoConceptos(Empresa $empresa): void
    {
        $conceptos = [
            'Cliente frecuente',
            'Promoción del día',
            'Cierre de temporada',
            'Producto en exhibición',
            'Cortesía',
        ];
        foreach ($conceptos as $nombre) {
            DescuentoConcepto::create([
                'empresa_id'          => $empresa->id,
                'nombre'              => $nombre,
                'requiere_aprobacion' => false,
                'activo'              => true,
            ]);
        }
    }

    /* ─── Catálogo: unidad, categorías, productos, servicios ─────────────── */

    private function crearUnidad(Empresa $empresa): UnidadMedida
    {
        return UnidadMedida::create([
            'empresa_id'  => $empresa->id,
            'nombre'      => 'Unidad',
            'abreviatura' => 'UND',
            'activo'      => true,
        ]);
    }

    /** @return array<string, Categoria> keyed by slug interno */
    private function crearCategorias(Empresa $empresa): array
    {
        $defs = [
            'ropa'         => 'Ropa',
            'accesorios'   => 'Accesorios y carteras',
            'calzado'      => 'Calzado',
            'cosmeticos'   => 'Cosméticos',
            'cuidado'      => 'Cuidado personal',
            'cabello'      => 'Cabello y peinado',
            'joyeria'      => 'Joyería y bisutería',
            'tecnologia'   => 'Tecnología y gadgets',
            'servicios'    => 'Servicios de imagen',
        ];

        $result = [];
        foreach ($defs as $key => $nombre) {
            $result[$key] = Categoria::create([
                'empresa_id'  => $empresa->id,
                'nombre'      => $nombre,
                'descripcion' => null,
                'activo'      => true,
            ]);
        }
        return $result;
    }

    /**
     * Catálogo de 50 productos físicos típicos de una importadora de bazar
     * para mujer en Chiclayo. Sin imágenes (campo `imagen` se deja en NULL
     * porque no tenemos un host garantizado).
     *
     * Precios en soles, costos aproximadamente 55% del precio (típico margen
     * de importadora directa al consumidor).
     *
     * @return array<Producto>
     */
    private function crearCatalogoProductos(Empresa $empresa, UnidadMedida $unidad, array $categorias): array
    {
        $catalogo = [
            // ─── Ropa (12) ─────────────────────────────────────────────────
            ['cat' => 'ropa', 'nombre' => 'Blusa básica blanca',         'precio' => 45,  'stock' => 30],
            ['cat' => 'ropa', 'nombre' => 'Blusa con encaje',            'precio' => 65,  'stock' => 20],
            ['cat' => 'ropa', 'nombre' => 'Vestido casual floreado',     'precio' => 89,  'stock' => 18],
            ['cat' => 'ropa', 'nombre' => 'Vestido coctel negro',        'precio' => 120, 'stock' => 12],
            ['cat' => 'ropa', 'nombre' => 'Jean skinny',                 'precio' => 75,  'stock' => 35],
            ['cat' => 'ropa', 'nombre' => 'Pantalón palazzo',            'precio' => 70,  'stock' => 22],
            ['cat' => 'ropa', 'nombre' => 'Falda midi',                  'precio' => 60,  'stock' => 25],
            ['cat' => 'ropa', 'nombre' => 'Short denim',                 'precio' => 50,  'stock' => 28],
            ['cat' => 'ropa', 'nombre' => 'Polo oversize',               'precio' => 55,  'stock' => 40],
            ['cat' => 'ropa', 'nombre' => 'Chompa de hilo',              'precio' => 95,  'stock' => 15],
            ['cat' => 'ropa', 'nombre' => 'Cardigan tejido',             'precio' => 110, 'stock' => 14],
            ['cat' => 'ropa', 'nombre' => 'Pijama 2 piezas',             'precio' => 65,  'stock' => 30],

            // ─── Accesorios (10) ───────────────────────────────────────────
            ['cat' => 'accesorios', 'nombre' => 'Cartera bandolera',        'precio' => 89,  'stock' => 18],
            ['cat' => 'accesorios', 'nombre' => 'Cartera tote grande',      'precio' => 120, 'stock' => 12],
            ['cat' => 'accesorios', 'nombre' => 'Mochila mini cuero',       'precio' => 95,  'stock' => 15],
            ['cat' => 'accesorios', 'nombre' => 'Billetera cuero sintético','precio' => 45,  'stock' => 25],
            ['cat' => 'accesorios', 'nombre' => 'Cinturón delgado dorado',  'precio' => 35,  'stock' => 30],
            ['cat' => 'accesorios', 'nombre' => 'Lentes de sol cat-eye',    'precio' => 55,  'stock' => 22],
            ['cat' => 'accesorios', 'nombre' => 'Lentes de sol aviador',    'precio' => 60,  'stock' => 20],
            ['cat' => 'accesorios', 'nombre' => 'Pañuelo de seda',          'precio' => 40,  'stock' => 25],
            ['cat' => 'accesorios', 'nombre' => 'Sombrero de playa',        'precio' => 50,  'stock' => 18],
            ['cat' => 'accesorios', 'nombre' => 'Cangurera bandolera',      'precio' => 65,  'stock' => 16],

            // ─── Calzado (5) ───────────────────────────────────────────────
            ['cat' => 'calzado', 'nombre' => 'Sandalias planas',         'precio' => 55,  'stock' => 25],
            ['cat' => 'calzado', 'nombre' => 'Tacones nude',             'precio' => 110, 'stock' => 15],
            ['cat' => 'calzado', 'nombre' => 'Zapatillas blancas',       'precio' => 145, 'stock' => 20],
            ['cat' => 'calzado', 'nombre' => 'Botines tobilleros',       'precio' => 130, 'stock' => 12],
            ['cat' => 'calzado', 'nombre' => 'Pantuflas peluche',        'precio' => 35,  'stock' => 30],

            // ─── Cosméticos (10) ───────────────────────────────────────────
            ['cat' => 'cosmeticos', 'nombre' => 'Labial mate rojo',          'precio' => 28, 'stock' => 50],
            ['cat' => 'cosmeticos', 'nombre' => 'Labial mate nude',          'precio' => 28, 'stock' => 50],
            ['cat' => 'cosmeticos', 'nombre' => 'Brillo labial transparente','precio' => 22, 'stock' => 45],
            ['cat' => 'cosmeticos', 'nombre' => 'Base líquida tono claro',   'precio' => 55, 'stock' => 30],
            ['cat' => 'cosmeticos', 'nombre' => 'Polvo compacto',            'precio' => 45, 'stock' => 35],
            ['cat' => 'cosmeticos', 'nombre' => 'Paleta sombras 12 colores', 'precio' => 75, 'stock' => 18],
            ['cat' => 'cosmeticos', 'nombre' => 'Rímel volumen',             'precio' => 35, 'stock' => 40],
            ['cat' => 'cosmeticos', 'nombre' => 'Delineador líquido',        'precio' => 25, 'stock' => 50],
            ['cat' => 'cosmeticos', 'nombre' => 'Brocha kabuki',             'precio' => 30, 'stock' => 25],
            ['cat' => 'cosmeticos', 'nombre' => 'Set 5 brochas maquillaje',  'precio' => 65, 'stock' => 20],

            // ─── Cuidado personal (5) ──────────────────────────────────────
            ['cat' => 'cuidado', 'nombre' => 'Perfume floral 100ml',     'precio' => 89, 'stock' => 25],
            ['cat' => 'cuidado', 'nombre' => 'Perfume fresh 50ml',       'precio' => 55, 'stock' => 30],
            ['cat' => 'cuidado', 'nombre' => 'Crema hidratante facial',  'precio' => 45, 'stock' => 35],
            ['cat' => 'cuidado', 'nombre' => 'Mascarillas carbón x10',   'precio' => 30, 'stock' => 40],
            ['cat' => 'cuidado', 'nombre' => 'Aceite capilar de argán',  'precio' => 50, 'stock' => 28],

            // ─── Cabello (4) ───────────────────────────────────────────────
            ['cat' => 'cabello', 'nombre' => 'Plancha cerámica',         'precio' => 180, 'stock' => 10],
            ['cat' => 'cabello', 'nombre' => 'Secadora 1800W',           'precio' => 220, 'stock' => 8],
            ['cat' => 'cabello', 'nombre' => 'Rizador cerámico',         'precio' => 145, 'stock' => 10],
            ['cat' => 'cabello', 'nombre' => 'Set 10 ligas para cabello','precio' => 18,  'stock' => 60],

            // ─── Joyería bisutería (4) ─────────────────────────────────────
            ['cat' => 'joyeria', 'nombre' => 'Set aretes argollas x6',   'precio' => 35, 'stock' => 30],
            ['cat' => 'joyeria', 'nombre' => 'Collar gargantilla dorado','precio' => 45, 'stock' => 25],
            ['cat' => 'joyeria', 'nombre' => 'Pulsera cuero trenzado',   'precio' => 28, 'stock' => 35],
            ['cat' => 'joyeria', 'nombre' => 'Reloj minimalista mujer',  'precio' => 75, 'stock' => 18],

            // ─── Tecnología (3) ────────────────────────────────────────────
            ['cat' => 'tecnologia', 'nombre' => 'Audífonos bluetooth',   'precio' => 65, 'stock' => 25],
            ['cat' => 'tecnologia', 'nombre' => 'Cargador USB-C 20W',    'precio' => 35, 'stock' => 40],
            ['cat' => 'tecnologia', 'nombre' => 'Soporte para celular',  'precio' => 25, 'stock' => 50],
        ];

        $productos = [];
        $i = 1;
        foreach ($catalogo as $item) {
            $costo = round($item['precio'] * 0.55, 2); // margen ~45%
            $codigo = 'P-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT);
            $i++;

            $producto = Producto::create([
                'empresa_id'     => $empresa->id,
                'categoria_id'   => $categorias[$item['cat']]->id,
                'codigo'         => $codigo,
                'nombre'         => $item['nombre'],
                'descripcion'    => null,
                'tipo'           => 'producto',
                'tipo_precio'    => 'fijo',
                'precio_venta'   => $item['precio'],
                'precio_costo'   => $costo,
                'activo'         => true,
                'incluye_igv'    => true, // todos gravados (importadora)
                'controla_stock' => true,
                'es_retornable'  => true,
            ]);

            ProductoUnidad::create([
                'producto_id'       => $producto->id,
                'unidad_medida_id'  => $unidad->id,
                'es_base'           => true,
                'factor_conversion' => 1,
                'tipo_precio'       => 'fijo',
                'precio_venta'      => $item['precio'],
                'precio_costo'      => $costo,
                'activo'            => true,
            ]);

            // El stock se materializa en cargarStockInicial() vía la entrada
            // confirmada. Aquí solo guardamos la metadata para esa fase.
            $productos[] = ['model' => $producto, 'stock_inicial' => $item['stock'], 'costo' => $costo];
        }

        return $productos;
    }

    /** @return array<Producto> */
    private function crearServiciosAgenda(Empresa $empresa, UnidadMedida $unidad, array $categorias): array
    {
        $defs = [
            ['nombre' => 'Asesoría de imagen',         'precio' => 80,  'codigo' => 'S-0001'],
            ['nombre' => 'Maquillaje para evento',     'precio' => 120, 'codigo' => 'S-0002'],
        ];

        $servicios = [];
        foreach ($defs as $d) {
            $producto = Producto::create([
                'empresa_id'     => $empresa->id,
                'categoria_id'   => $categorias['servicios']->id,
                'codigo'         => $d['codigo'],
                'nombre'         => $d['nombre'],
                'descripcion'    => null,
                'tipo'           => 'servicio',
                'tipo_precio'    => 'fijo',
                'precio_venta'   => $d['precio'],
                'precio_costo'   => 0,
                'activo'         => true,
                'incluye_igv'    => true,
                'controla_stock' => false,
                'es_retornable'  => false,
            ]);

            ProductoUnidad::create([
                'producto_id'       => $producto->id,
                'unidad_medida_id'  => $unidad->id,
                'es_base'           => true,
                'factor_conversion' => 1,
                'tipo_precio'       => 'fijo',
                'precio_venta'      => $d['precio'],
                'precio_costo'      => 0,
                'activo'            => true,
            ]);

            $servicios[] = $producto;
        }
        return $servicios;
    }

    /* ─── Stock inicial via entrada confirmada ───────────────────────────── */

    private function cargarStockInicial(Empresa $empresa, Almacen $almacen, User $admin, array $productos): void
    {
        $totalEntrada = 0;
        foreach ($productos as $p) {
            $totalEntrada += $p['costo'] * $p['stock_inicial'];
        }

        $entrada = Entrada::create([
            'empresa_id'       => $empresa->id,
            'almacen_id'       => $almacen->id,
            'user_id'          => $admin->id,
            'numero_documento' => 'IMP-2026-001',
            'tipo'             => 'compra',
            'fecha'            => now()->subDays(3),
            'estado'           => 'confirmado',
            'observacion'      => 'Stock inicial de apertura de tienda (importación)',
            'total'            => round($totalEntrada, 2),
        ]);

        $unidadId = ProductoUnidad::where('producto_id', $productos[0]['model']->id)->value('unidad_medida_id');

        foreach ($productos as $p) {
            EntradaDetalle::create([
                'entrada_id'       => $entrada->id,
                'producto_id'      => $p['model']->id,
                'unidad_medida_id' => $unidadId,
                'cantidad'         => $p['stock_inicial'],
                'cantidad_base'    => $p['stock_inicial'],
                'precio_costo'     => $p['costo'],
                'subtotal'         => round($p['costo'] * $p['stock_inicial'], 2),
            ]);

            // Cargar stock directamente (Stock::ajustar abre su propia transacción
            // anidada; aquí inserto el row directo porque ya estamos en una transaction)
            Stock::create([
                'almacen_id'     => $almacen->id,
                'producto_id'    => $p['model']->id,
                'cantidad'       => $p['stock_inicial'],
                'costo_promedio' => $p['costo'],
            ]);
        }
    }

    /* ─── Clientes de prueba ─────────────────────────────────────────────── */

    /** @return array<Cliente> */
    private function crearClientes(Empresa $empresa): array
    {
        $general = Cliente::create([
            'empresa_id'         => $empresa->id,
            'tipo_documento'     => 'DNI',
            'numero_documento'   => '99999999',
            'nombres'            => 'Clientes Varios',
            'apellidos'          => '',
            'activo'             => true,
            'es_cliente_general' => true,
        ]);

        $defs = [
            ['nombres' => 'María',    'apellidos' => 'Salazar Rodríguez', 'doc' => '72345678', 'tel' => '+51 974 555 001'],
            ['nombres' => 'Lucía',    'apellidos' => 'Vega Castillo',     'doc' => '70112233', 'tel' => '+51 974 555 002'],
            ['nombres' => 'Andrea',   'apellidos' => 'Torres Mendoza',    'doc' => '71889944', 'tel' => '+51 974 555 003'],
            ['nombres' => 'Patricia', 'apellidos' => 'Quispe Vargas',     'doc' => '70556677', 'tel' => '+51 974 555 004'],
            ['nombres' => 'Carolina', 'apellidos' => 'Flores Cabrera',    'doc' => '73221100', 'tel' => '+51 974 555 005'],
        ];

        $clientes = [$general];
        foreach ($defs as $d) {
            $clientes[] = Cliente::create([
                'empresa_id'         => $empresa->id,
                'tipo_documento'     => 'DNI',
                'numero_documento'   => $d['doc'],
                'nombres'            => $d['nombres'],
                'apellidos'          => $d['apellidos'],
                'telefono'           => $d['tel'],
                'activo'             => true,
                'es_cliente_general' => false,
            ]);
        }
        return $clientes;
    }

    /* ─── Citas de prueba ────────────────────────────────────────────────── */

    private function crearCitas(Empresa $empresa, Local $local, User $admin, array $clientes, array $servicios): void
    {
        $asesoria   = $servicios[0]; // Asesoría de imagen
        $maquillaje = $servicios[1]; // Maquillaje para evento
        $unidadAsesoria   = ProductoUnidad::where('producto_id', $asesoria->id)->first();
        $unidadMaquillaje = ProductoUnidad::where('producto_id', $maquillaje->id)->first();

        $plan = [
            [
                'cliente'   => $clientes[1], // María
                'servicio'  => $maquillaje,
                'unidad'    => $unidadMaquillaje,
                'fechaHora' => now()->addDay()->setTime(10, 0),
                'duracion'  => 60,
                'obs'       => 'Maquillaje para matrimonio civil',
            ],
            [
                'cliente'   => $clientes[2], // Lucía
                'servicio'  => $asesoria,
                'unidad'    => $unidadAsesoria,
                'fechaHora' => now()->addDay()->setTime(14, 0),
                'duracion'  => 45,
                'obs'       => 'Asesoría de imagen para entrevista',
            ],
            [
                'cliente'   => $clientes[3], // Andrea
                'servicio'  => $maquillaje,
                'unidad'    => $unidadMaquillaje,
                'fechaHora' => now()->addDays(3)->setTime(8, 0),
                'duracion'  => 90,
                'obs'       => 'Maquillaje de novia + prueba',
            ],
            [
                'cliente'   => $clientes[4], // Patricia
                'servicio'  => $asesoria,
                'unidad'    => $unidadAsesoria,
                'fechaHora' => now()->addDays(5)->setTime(16, 30),
                'duracion'  => 60,
                'obs'       => 'Personal shopping - cambio de armario',
            ],
            [
                'cliente'   => $clientes[5], // Carolina
                'servicio'  => $maquillaje,
                'unidad'    => $unidadMaquillaje,
                'fechaHora' => now()->addDays(7)->setTime(11, 0),
                'duracion'  => 60,
                'obs'       => 'Maquillaje para sesión de fotos',
            ],
        ];

        $i = 1;
        foreach ($plan as $p) {
            $cita = Cita::create([
                'empresa_id'    => $empresa->id,
                'local_id'      => $local->id,
                'cliente_id'    => $p['cliente']->id,
                'profesional_id'=> $admin->id, // por defecto la dueña atiende
                'created_by'    => $admin->id,
                'numero'        => 'C-' . str_pad((string) $empresa->id, 3, '0', STR_PAD_LEFT) . '-' . str_pad((string) $i, 5, '0', STR_PAD_LEFT),
                'fecha_hora'    => $p['fechaHora'],
                'duracion_min'  => $p['duracion'],
                'estado'        => Cita::ESTADO_PROGRAMADA,
                'observaciones' => $p['obs'],
            ]);

            CitaItem::create([
                'cita_id'            => $cita->id,
                'producto_id'        => $p['servicio']->id,
                'producto_unidad_id' => $p['unidad']->id,
                'cantidad'           => 1,
                'duracion_min'       => $p['duracion'],
                'precio_estimado'    => (float) $p['servicio']->precio_venta,
                'observaciones'      => null,
                'orden'              => 0,
            ]);
            $i++;
        }
    }
}
