<?php

namespace Database\Seeders;

use App\Models\BalanceDiario;
use App\Models\User;
use App\Services\BalanceDiarioService;
use App\Services\TesoreriaService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * DEMO — Ferretería HYC Ferromateriales, balance diario de AYER y HOY.
 *
 * Recrea el escenario del Excel real del cliente (BALANCE FERRETERIA H&C.xlsx,
 * bloques del 01 y 02 de julio 2026): saldos iniciales de BCP/BBVA/Efectivo,
 * stock de ladrillo/cemento/fierro valorizado, deudas bancarias (BCP 1-7630,
 * BCP 2-5557), deudas personales (Jordin Herrera), préstamos otorgados
 * (Jhon Astonitas), proveedores por pagar (Ferronor, Ardiles, Cofesa,
 * Uyustools), adelantos (Uyustools, Prodac), anticipos de clientes
 * valorizados a precio del día, gastos clasificados y descuentos de planilla.
 *
 * Deja el balance de AYER CONFIRMADO y el de HOY en borrador, para que la
 * demo muestre "cerrar el día de hoy" comparando contra ayer.
 *
 * DESTRUCTIVO E IDEMPOTENTE: borra ventas/turnos/gastos/finanzas anteriores
 * y reconstruye todo. Solo para la BD de desarrollo/demo.
 *
 *   php artisan db:seed --class=DemoFerreteriaSeeder
 */
class DemoFerreteriaSeeder extends Seeder
{
    private const EMPRESA = 1;
    private const LOCAL   = 1;
    private const ALMACEN = 1;
    private const CAJA    = 1;

    private TesoreriaService $tesoreria;
    private User $admin;
    private User $cajera;

    /** @var array<string,int> cuentas por alias */
    private array $ctas = [];
    /** @var array<string,int> conceptos de gasto por nombre */
    private array $conceptos = [];
    /** @var array<string,int> gasto_tipo por concepto */
    private array $tipoDeConcepto = [];
    /** @var array<string,array{id:int,unidad:int,costo:float,venta:float}> productos por alias */
    private array $prods = [];
    /** @var array<string,int> clientes por alias */
    private array $clis = [];
    /** @var array<string,int> proveedores por alias */
    private array $provs = [];

    public function run(): void
    {
        $this->tesoreria = app(TesoreriaService::class);
        $this->admin  = User::where('email', 'jesus@gmail.com')->firstOrFail();
        $this->cajera = User::where('email', 'cajera@gmail.com')->firstOrFail();

        $hoy  = Carbon::today();
        $ayer = Carbon::yesterday();

        DB::transaction(function () use ($hoy, $ayer) {
            $this->limpiar();
            $this->catalogo();
            $this->saldosIniciales($hoy->copy()->subDays(4)->toDateString());
            $this->pasivosYActivosPrevios($hoy);
            $this->historialCreditos($hoy);

            // ── AYER: día completo y balance CONFIRMADO ─────────────────
            $this->diaAyer($ayer->toDateString());
            $balanceAyer = app(BalanceDiarioService::class)->generar($this->admin, $ayer->toDateString());
            app(BalanceDiarioService::class)->confirmar($balanceAyer, $this->admin);

            // ── HOY: operaciones del día y balance en BORRADOR ──────────
            $this->diaHoy($hoy->toDateString());
            app(BalanceDiarioService::class)->generar($this->admin, $hoy->toDateString());
        });

        $ba = BalanceDiario::where('fecha', $ayer->toDateString())->first();
        $bh = BalanceDiario::where('fecha', $hoy->toDateString())->first();
        $this->command?->info("Balance AYER  ({$ayer->toDateString()}): neto S/ {$ba->balance_neto} — CONFIRMADO");
        $this->command?->info("Balance HOY   ({$hoy->toDateString()}): neto S/ {$bh->balance_neto} — borrador (para cerrar en la demo)");
    }

    // ──────────────────────────────────────────────────────────────────────
    private function limpiar(): void
    {
        foreach ([
            'balance_diario_items', 'balances_diarios',
            'turno_consolidacion_items', 'turno_consolidaciones',
            'devolucion_pagos', 'devoluciones_detalle', 'devoluciones',
            'descuentos_log',
            'venta_abonos', 'venta_pagos', 'venta_items', 'ventas',
            'turno_cierre_productos', 'turno_arqueo_metodos', 'turno_arqueo', 'turnos',
            'gastos', 'gasto_conceptos', 'gasto_tipos',
            'entrada_pagos', 'entradas_detalle', 'entradas',
            'cliente_anticipo_aplicaciones', 'cliente_anticipos',
            'proveedor_adelanto_aplicaciones', 'proveedor_adelantos',
            'deuda_pagos', 'deudas',
            'planilla_descuentos',
            'cuenta_movimientos',
        ] as $tabla) {
            DB::table($tabla)->delete();
        }

        // Catálogo creado por corridas anteriores de ESTE seeder (los
        // registros hijos que los referencian ya se borraron arriba).
        $cuentasDemo = DB::table('cuentas')->where('empresa_id', self::EMPRESA)
            ->whereIn('nombre', ['Cuenta BCP Soles', 'Cuenta BBVA Soles', 'Yape'])->pluck('id');
        DB::table('cuenta_metodo_pago')->whereIn('cuenta_id', $cuentasDemo)->delete();
        DB::table('cuentas')->whereIn('id', $cuentasDemo)->delete();

        $prodsDemo = DB::table('productos')->where('empresa_id', self::EMPRESA)
            ->where('codigo', 'like', 'FER-%')->pluck('id');
        DB::table('stock')->whereIn('producto_id', $prodsDemo)->delete();
        DB::table('producto_unidades')->whereIn('producto_id', $prodsDemo)->delete();
        DB::table('productos')->whereIn('id', $prodsDemo)->delete();
        DB::table('categorias')->where('empresa_id', self::EMPRESA)
            ->where('nombre', 'Materiales de construcción')->delete();

        DB::table('clientes')->where('empresa_id', self::EMPRESA)->whereIn('numero_documento', [
            '20487965123', '20539871456', '16745823', '17458963', '16987452', '20601234789',
        ])->delete();
        DB::table('proveedores')->where('empresa_id', self::EMPRESA)->whereIn('numero_documento', [
            '20481123457', '20392214569', '20172214870', '20504412987', '20100127390',
        ])->delete();
    }

    // ──────────────────────────────────────────────────────────────────────
    private function catalogo(): void
    {
        $now = now();

        // Segunda caja: el turno demo de hoy ocupa la Caja Principal (queda
        // ABIERTO a nombre de la Cajera), así el admin puede abrir su propio
        // turno aquí y entrar al POS durante la demo.
        \App\Models\Caja::firstOrCreate(
            ['empresa_id' => self::EMPRESA, 'local_id' => self::LOCAL, 'nombre' => 'Caja 2 — Mostrador'],
            ['activo' => true, 'caja_chica_activa' => false, 'caja_chica_monto_sugerido' => 0, 'caja_chica_en_arqueo' => false],
        );

        // Cuentas reales del Excel (+ Yape; Efectivo y Tarjeta ya existen).
        $this->ctas['efectivo'] = DB::table('cuentas')->where('empresa_id', self::EMPRESA)->where('es_efectivo', true)->value('id');
        $this->ctas['bcp'] = DB::table('cuentas')->insertGetId([
            'empresa_id' => self::EMPRESA, 'nombre' => 'Cuenta BCP Soles', 'banco' => 'BCP',
            'numero_cuenta' => '305-2214578-0-11', 'titular' => 'HYC Ferromateriales SRL',
            'activo' => true, 'es_efectivo' => false, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $this->ctas['bbva'] = DB::table('cuentas')->insertGetId([
            'empresa_id' => self::EMPRESA, 'nombre' => 'Cuenta BBVA Soles', 'banco' => 'BBVA',
            'numero_cuenta' => '0011-0249-0100045678', 'titular' => 'HYC Ferromateriales SRL',
            'activo' => true, 'es_efectivo' => false, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $this->ctas['yape'] = DB::table('cuentas')->insertGetId([
            'empresa_id' => self::EMPRESA, 'nombre' => 'Yape', 'banco' => 'BCP',
            'activo' => true, 'es_efectivo' => false, 'created_at' => $now, 'updated_at' => $now,
        ]);

        // Vincular métodos → cuentas (Transferencia→BCP, Yape→Yape).
        $metodos = DB::table('metodos_pago')->pluck('id', 'nombre');
        foreach ([['Transferencia', 'bcp'], ['Yape', 'yape']] as [$metodo, $alias]) {
            if (isset($metodos[$metodo])) {
                DB::table('cuenta_metodo_pago')->insert([
                    'cuenta_id' => $this->ctas[$alias], 'metodo_pago_id' => $metodos[$metodo],
                ]);
            }
        }

        // Clasificaciones de gastos (tipos → conceptos), tomadas del Excel real.
        $estructura = [
            'Vehículos y combustible' => ['operativo', ['Combustible', 'Mantenimiento vehicular', 'Peajes y pasajes']],
            'Administrativo'          => ['administrativo', ['Alquiler local', 'Energía eléctrica', 'Líneas de celulares', 'Contadora', 'Mantenimiento bancario']],
            'Impuestos'               => ['administrativo', ['SUNAT Renta', 'EsSalud']],
            'Personal'                => ['operativo', ['Alimentación personal', 'Limpieza']],
            'Operativo'               => ['operativo', ['Herramientas y repuestos', 'Flete y descarga']],
            'Otro'                    => ['otro', ['Otros gastos']],
        ];
        foreach ($estructura as $tipoNombre => [$categoria, $conceptos]) {
            $tipoId = DB::table('gasto_tipos')->insertGetId([
                'empresa_id' => self::EMPRESA, 'nombre' => $tipoNombre, 'categoria' => $categoria,
                'activo' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
            foreach ($conceptos as $c) {
                $this->conceptos[$c] = DB::table('gasto_conceptos')->insertGetId([
                    'empresa_id' => self::EMPRESA, 'gasto_tipo_id' => $tipoId, 'nombre' => $c,
                    'activo' => true, 'created_at' => $now, 'updated_at' => $now,
                ]);
                $this->tipoDeConcepto[$c] = $tipoId;
            }
        }

        // Productos de ferretería (los de boutique se desactivan para la demo).
        DB::table('productos')->where('empresa_id', self::EMPRESA)->update(['activo' => false]);
        $categoriaId = DB::table('categorias')->insertGetId([
            'empresa_id' => self::EMPRESA, 'nombre' => 'Materiales de construcción',
            'created_at' => $now, 'updated_at' => $now,
        ]);

        $catalogo = [
            // alias => [nombre, costo, venta, stock]
            'lkk'       => ['Ladrillo King Kong 18 huecos', 0.85, 1.10, 85000],
            'pandereta' => ['Ladrillo Pandereta', 0.58, 0.75, 60000],
            'techo15'   => ['Ladrillo de Techo 15', 1.90, 2.40, 18000],
            'cemento'   => ['Cemento Pacasmayo Tipo I x 42.5 kg', 26.50, 29.90, 850],
            'fierro12'  => ['Fierro corrugado 1/2" x 9m Aceros Arequipa', 32.80, 36.50, 620],
            'fierro38'  => ['Fierro corrugado 3/8" x 9m Aceros Arequipa', 18.90, 21.00, 740],
            'alambre16' => ['Alambre N°16 Prodac (kg)', 4.20, 5.50, 900],
            'alambre8'  => ['Alambre N°8 Prodac (kg)', 4.10, 5.20, 700],
            'clavos'    => ['Clavos 2 1/2" (kg)', 3.90, 5.00, 450],
            'calamina'  => ['Calamina galvanizada 0.22 x 3.6m', 28.50, 33.00, 380],
            'arena'     => ['Arena gruesa (m³)', 38.00, 55.00, 120],
            'piedra'    => ['Piedra chancada 1/2" (m³)', 52.00, 70.00, 90],
        ];
        $i = 0;
        foreach ($catalogo as $alias => [$nombre, $costo, $venta, $stock]) {
            $i++;
            $prodId = DB::table('productos')->insertGetId([
                'empresa_id' => self::EMPRESA, 'categoria_id' => $categoriaId,
                'codigo' => sprintf('FER-%03d', $i), 'nombre' => $nombre,
                'tipo' => 'producto', 'tipo_precio' => 'fijo',
                'precio_venta' => $venta, 'precio_costo' => $costo,
                'activo' => true, 'incluye_igv' => true, 'controla_stock' => true, 'es_retornable' => false,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $unidadId = DB::table('producto_unidades')->insertGetId([
                'producto_id' => $prodId, 'unidad_medida_id' => 1, 'es_base' => true,
                'factor_conversion' => 1, 'tipo_precio' => 'fijo',
                'precio_venta' => $venta, 'precio_costo' => $costo, 'activo' => true,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('stock')->insert([
                'almacen_id' => self::ALMACEN, 'producto_id' => $prodId,
                'cantidad' => $stock, 'costo_promedio' => $costo,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $this->prods[$alias] = ['id' => $prodId, 'unidad' => $unidadId, 'costo' => $costo, 'venta' => $venta];
        }

        // Clientes de ferretería.
        foreach ([
            'chiclayo'  => ['razon' => 'CONSTRUCTORA CHICLAYO SAC', 'doc' => '20487965123'],
            'norte'     => ['razon' => 'CONSTRUCTORA NORTE SAC',    'doc' => '20539871456'],
            'eladio'    => ['nombres' => 'Eladio', 'apellidos' => 'Vásquez Cieza', 'doc' => '16745823'],
            'manuel'    => ['nombres' => 'Manuel', 'apellidos' => 'Effio Puican', 'doc' => '17458963'],
            'rosa'      => ['nombres' => 'Rosa', 'apellidos' => 'Paredes Llontop', 'doc' => '16987452'],
            'santarosa' => ['razon' => 'COMERCIAL SANTA ROSA EIRL', 'doc' => '20601234789'],
        ] as $alias => $c) {
            $esRuc = isset($c['razon']);
            $this->clis[$alias] = DB::table('clientes')->insertGetId([
                'empresa_id' => self::EMPRESA,
                'tipo_documento' => $esRuc ? 'RUC' : 'DNI', 'numero_documento' => $c['doc'],
                'nombres' => $esRuc ? $c['razon'] : $c['nombres'],
                'apellidos' => $esRuc ? '' : $c['apellidos'],
                'razon_social' => $esRuc ? $c['razon'] : null,
                'activo' => true, 'es_cliente_general' => false,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // Proveedores reales del Excel.
        foreach ([
            'ferronor'  => ['FERRONOR EIRL', '20481123457'],
            'ardiles'   => ['ARDILES IMPORT SRL', '20392214569'],
            'cofesa'    => ['COFESA SAC', '20172214870'],
            'uyustools' => ['UYUSTOOLS PERU SAC', '20504412987'],
            'prodac'    => ['PRODAC SA', '20100127390'],
        ] as $alias => [$razon, $ruc]) {
            $this->provs[$alias] = DB::table('proveedores')->insertGetId([
                'empresa_id' => self::EMPRESA, 'tipo_documento' => 'RUC',
                'numero_documento' => $ruc, 'razon_social' => $razon,
                'activo' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    // ──────────────────────────────────────────────────────────────────────
    private function saldosIniciales(string $fecha): void
    {
        // Montos del Excel (bloque 01-07-2026 / 02-07-2026).
        foreach ([
            ['bcp', 44915.86], ['bbva', 4693.54], ['efectivo', 8606.11],
        ] as [$alias, $monto]) {
            $this->tesoreria->registrar(
                self::EMPRESA, $this->ctas[$alias], $this->admin, $fecha,
                'ingreso', $monto,
                'Ajuste de saldo: Saldo inicial al implementar el sistema (Excel)',
                'ajuste', null,
            );
        }
    }

    // ──────────────────────────────────────────────────────────────────────
    private function pasivosYActivosPrevios(Carbon $hoy): void
    {
        $now = now();
        $d2  = $hoy->copy()->subDays(2)->toDateString();
        $d3  = $hoy->copy()->subDays(3)->toDateString();

        // Deudas (nombres y montos del Excel). TRAZABILIDAD: nacen con
        // saldo = monto_original (el saldo al implementar el sistema; el
        // préstamo original de antes queda documentado en la observación).
        // Toda reducción posterior es una cuota registrada.
        foreach ([
            ['por_pagar', 'bancaria',   'DEUDA BCP 1 - 7630', 6673.81, 'Préstamo capital de trabajo — saldo al implementar el sistema (préstamo original S/ 8,000)'],
            ['por_pagar', 'bancaria',   'DEUDA BCP 2 - 5557', 32546.43, 'Préstamo vehicular FUSO — saldo al implementar el sistema (préstamo original S/ 35,000)'],
            ['por_pagar', 'personal',   'JORDIN HERRERA', 30000.00, 'Préstamo personal'],
            ['por_pagar', 'trabajador', 'Sueldos pendientes — personal', 2000.00, 'Quincena por pagar'],
            ['por_cobrar', 'personal',  'JHON ASTONITAS', 345.05, 'Préstamo a tercero'],
            ['por_cobrar', 'trabajador', 'Préstamo moto — Carlos Uceda (almacén)', 1350.00, 'Cuota semanal S/ 75 descontada en caja — saldo al implementar el sistema (préstamo original S/ 1,500)'],
        ] as [$dir, $tipo, $nombre, $monto, $obs]) {
            DB::table('deudas')->insert([
                'empresa_id' => self::EMPRESA, 'user_id' => $this->admin->id,
                'direccion' => $dir, 'tipo' => $tipo, 'nombre' => $nombre,
                'monto_original' => $monto, 'saldo' => $monto,
                'fecha_inicio' => $hoy->copy()->subDays(4)->toDateString(),
                'estado' => 'activa', 'observacion' => $obs,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // Cuota semanal de la moto de la semana pasada (historial con más
        // de un pago: 1,350 → 1,275; la de hoy la baja a 1,200).
        $this->cuotaDeuda('Préstamo moto — Carlos Uceda (almacén)', $d3, 75.00, 'efectivo');

        // Cuentas por pagar: compras confirmadas con saldo.
        $entradas = [
            'ferronor'  => ['F001-8934', $d3, 48500.00, 20000.00],
            'ardiles'   => ['F002-1120', $d3, 32400.00, 0.00],
            'cofesa'    => ['F003-5541', $d2, 26300.00, 5000.00],
            'uyustools' => ['F004-0089', $d2, 14700.00, 0.00],
        ];
        foreach ($entradas as $prov => [$doc, $fecha, $total, $pagado]) {
            $entradaId = DB::table('entradas')->insertGetId([
                'empresa_id' => self::EMPRESA, 'almacen_id' => self::ALMACEN,
                'user_id' => $this->admin->id, 'numero_documento' => $doc,
                'proveedor' => DB::table('proveedores')->where('id', $this->provs[$prov])->value('razon_social'),
                'proveedor_id' => $this->provs[$prov],
                'tipo' => 'compra', 'fecha' => $fecha, 'estado' => 'confirmado',
                'total' => $total, 'monto_pagado' => $pagado,
                'estado_pago' => $pagado > 0 ? 'parcial' : 'pendiente',
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $this->entradaIds[$prov] = $entradaId;
            if ($pagado > 0) {
                $pagoId = DB::table('entrada_pagos')->insertGetId([
                    'entrada_id' => $entradaId, 'user_id' => $this->admin->id,
                    'cuenta_id' => $this->ctas['bcp'], 'fecha' => $d2, 'monto' => $pagado,
                    'referencia' => 'Transferencia BCP', 'created_at' => $now, 'updated_at' => $now,
                ]);
                $this->tesoreria->registrar(
                    self::EMPRESA, $this->ctas['bcp'], $this->admin, $d2, 'egreso', $pagado,
                    "Pago a proveedor {$entradas[$prov][0]}", 'entrada_pago', $pagoId,
                );
            }
        }

        // Adelantos a proveedores (activo a favor).
        foreach ([
            ['uyustools', 4500.00, $d3, 'Adelanto por pedido de herramientas'],
            ['prodac',    2800.00, $d2, 'Adelanto campaña de alambre'],
        ] as [$prov, $monto, $fecha, $obs]) {
            $adelantoId = DB::table('proveedor_adelantos')->insertGetId([
                'empresa_id' => self::EMPRESA, 'proveedor_id' => $this->provs[$prov],
                'user_id' => $this->admin->id, 'cuenta_id' => $this->ctas['bcp'],
                'fecha' => $fecha, 'monto' => $monto, 'saldo' => $monto,
                'estado' => 'activo', 'observacion' => $obs,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            $this->tesoreria->registrar(
                self::EMPRESA, $this->ctas['bcp'], $this->admin, $fecha, 'egreso', $monto,
                "Adelanto a proveedor #{$adelantoId}", 'proveedor_adelanto', $adelantoId,
            );
        }

        // Anticipos de clientes (pasivo, valorizado a precio del día).
        $anticipoMaterial = DB::table('cliente_anticipos')->insertGetId([
            'empresa_id' => self::EMPRESA, 'cliente_id' => $this->clis['chiclayo'],
            'user_id' => $this->admin->id, 'cuenta_id' => $this->ctas['bcp'],
            'fecha' => $d3, 'monto' => 26250.00, 'saldo' => 26250.00,
            'tipo_valorizacion' => 'material', 'producto_id' => $this->prods['lkk']['id'],
            'cantidad' => 25000, 'cantidad_pendiente' => 25000,
            'estado' => 'activo', 'observacion' => 'Ladrillo KK pagado por adelantado, entrega por obra',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $this->tesoreria->registrar(
            self::EMPRESA, $this->ctas['bcp'], $this->admin, $d3, 'ingreso', 26250.00,
            "Anticipo de cliente #{$anticipoMaterial}", 'cliente_anticipo', $anticipoMaterial,
        );

        $anticipoMonto = DB::table('cliente_anticipos')->insertGetId([
            'empresa_id' => self::EMPRESA, 'cliente_id' => $this->clis['rosa'],
            'user_id' => $this->admin->id, 'cuenta_id' => $this->ctas['yape'],
            'fecha' => $d2, 'monto' => 6500.00, 'saldo' => 6500.00,
            'tipo_valorizacion' => 'monto',
            'estado' => 'activo', 'observacion' => 'A cuenta de materiales para su casa',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        $this->tesoreria->registrar(
            self::EMPRESA, $this->ctas['yape'], $this->admin, $d2, 'ingreso', 6500.00,
            "Anticipo de cliente #{$anticipoMonto}", 'cliente_anticipo', $anticipoMonto,
        );
    }

    /** @var array<string,int> */
    private array $entradaIds = [];
    /** @var array<string,int> ventas a crédito por alias */
    private array $ventaIds = [];

    // ──────────────────────────────────────────────────────────────────────
    private function historialCreditos(Carbon $hoy): void
    {
        $d3 = $hoy->copy()->subDays(3);
        $d2 = $hoy->copy()->subDays(2);

        // Turnos cerrados previos.
        $t3 = $this->crearTurno($d3, cerrado: true, apertura: 200, efectivoDia: 0);
        $t2 = $this->crearTurno($d2, cerrado: true, apertura: 200, efectivoDia: 2000);

        // Ventas a crédito grandes (las "DEUDAS POR COBRAR" del Excel).
        $this->ventaIds['chiclayo'] = $this->venta($t3, $d3->copy()->setTime(10, 15), 'V-0101', 'chiclayo', [
            ['lkk', 30000], ['cemento', 400],
        ], credito: true, inicial: 15000.00, metodoInicial: 'Transferencia', vence: $hoy->copy()->addDays(26));

        $this->ventaIds['eladio'] = $this->venta($t2, $d2->copy()->setTime(9, 40), 'V-0201', 'eladio', [
            ['fierro12', 200], ['cemento', 36],
        ], credito: true, inicial: 2000.00, metodoInicial: 'Efectivo', vence: $hoy->copy()->addDays(12));

        $this->ventaIds['norte'] = $this->venta($t2, $d2->copy()->setTime(16, 5), 'V-0202', 'norte', [
            ['pandereta', 45000], ['fierro12', 250],
        ], credito: true, inicial: 10000.00, metodoInicial: 'Transferencia', vence: $hoy->copy()->addDays(20));
    }

    // ──────────────────────────────────────────────────────────────────────
    private function diaAyer(string $fecha): void
    {
        $dia = Carbon::parse($fecha);
        $turno = $this->crearTurno($dia, cerrado: true, apertura: 200, efectivoDia: 2079 - 510, faltante: 25.50);

        // Ventas al contado.
        $this->venta($turno, $dia->copy()->setTime(9, 10),  'V-0401', null, [['lkk', 500], ['cemento', 10]], metodoContado: 'Efectivo');
        $this->venta($turno, $dia->copy()->setTime(11, 25), 'V-0402', null, [['calamina', 30], ['alambre16', 40]], metodoContado: 'Yape');
        $this->venta($turno, $dia->copy()->setTime(12, 40), 'V-0403', 'norte', [['fierro12', 80], ['fierro38', 12]], metodoContado: 'Transferencia');
        $this->venta($turno, $dia->copy()->setTime(16, 50), 'V-0404', null, [['arena', 6], ['clavos', 20]], metodoContado: 'Efectivo');

        // Venta a crédito de ayer.
        $this->ventaIds['manuel'] = $this->venta($turno, $dia->copy()->setTime(15, 20), 'V-0405', 'manuel', [
            ['pandereta', 4000], ['cemento', 60],
        ], credito: true, inicial: 800.00, metodoInicial: 'Efectivo', vence: $dia->copy()->addDays(14));

        // Abono de Constructora Chiclayo a su crédito (transferencia BCP).
        $this->abono($this->ventaIds['chiclayo'], $fecha, 5000.00, 'bcp', 'Transferencia BCP — obra Av. Balta');

        // Gastos del día, clasificados (nombres reales del Excel).
        $this->gasto($fecha, 355.00, 'Combustible', 'efectivo', 'Combustible FUSO', $turno);
        $this->gasto($fecha, 120.00, 'Mantenimiento vehicular', 'efectivo', 'Mecánico y fajas — FUSO', $turno);
        $this->gasto($fecha, 35.00,  'Alimentación personal', 'efectivo', 'Almuerzo personal', $turno);
        $this->gasto($fecha, 240.70, 'Líneas de celulares', 'bcp', 'Líneas de celulares (5)');

        // Faltante del cierre → egreso de caja + descuento de planilla pendiente.
        $this->tesoreria->registrar(
            self::EMPRESA, $this->ctas['efectivo'], $this->cajera, $fecha, 'egreso', 25.50,
            'Faltante de caja — cierre de turno (Cajera)', 'cierre_turno', $turno,
        );
        DB::table('planilla_descuentos')->insert([
            'empresa_id' => self::EMPRESA, 'user_id' => $this->cajera->id,
            'registrado_por' => $this->admin->id, 'fecha' => $fecha, 'monto' => 25.50,
            'motivo' => 'Faltante de caja en cierre de turno', 'ref_tipo' => 'cierre_turno', 'ref_id' => $turno,
            'estado' => 'pendiente', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────
    private function diaHoy(string $fecha): void
    {
        $dia = Carbon::parse($fecha);
        $turno = $this->crearTurno($dia, cerrado: false, apertura: 200, efectivoDia: 0);

        // Ventas de hoy.
        $this->venta($turno, $dia->copy()->setTime(9, 5),  'V-0501', null, [['lkk', 1000], ['piedra', 8]], metodoContado: 'Efectivo');
        $this->venta($turno, $dia->copy()->setTime(10, 35), 'V-0502', 'rosa', [['cemento', 25], ['alambre8', 25]], metodoContado: 'Yape');
        $this->venta($turno, $dia->copy()->setTime(12, 15), 'V-0503', 'eladio', [
            ['fierro38', 100], ['clavos', 8],
        ], credito: true, inicial: 300.00, metodoInicial: 'Efectivo', vence: $dia->copy()->addDays(14));

        // Abono de Constructora Norte (transferencia BCP).
        $this->abono($this->ventaIds['norte'], $fecha, 1500.00, 'bcp', 'Transferencia BCP');

        // Pago a Ferronor desde BCP (baja la CxP, NO es gasto del día).
        $pagoId = DB::table('entrada_pagos')->insertGetId([
            'entrada_id' => $this->entradaIds['ferronor'], 'user_id' => $this->admin->id,
            'cuenta_id' => $this->ctas['bcp'], 'fecha' => $fecha, 'monto' => 2000.00,
            'referencia' => 'Transferencia BCP', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('entradas')->where('id', $this->entradaIds['ferronor'])->update([
            'monto_pagado' => DB::raw('monto_pagado + 2000'), 'estado_pago' => 'parcial', 'updated_at' => now(),
        ]);
        $this->tesoreria->registrar(
            self::EMPRESA, $this->ctas['bcp'], $this->admin, $fecha, 'egreso', 2000.00,
            'Pago a proveedor F001-8934 (FERRONOR EIRL)', 'entrada_pago', $pagoId,
        );

        // Cuotas de deudas de hoy.
        $this->cuotaDeuda('DEUDA BCP 1 - 7630', $fecha, 500.00, 'bcp');                        // egreso
        $this->cuotaDeuda('JHON ASTONITAS', $fecha, 100.00, 'efectivo');                        // ingreso
        $this->cuotaDeuda('Préstamo moto — Carlos Uceda (almacén)', $fecha, 75.00, 'efectivo'); // ingreso

        // Gastos de hoy, clasificados.
        $this->gasto($fecha, 478.80, 'Energía eléctrica', 'bcp', 'Energía eléctrica — 5 recibos');
        $this->gasto($fecha, 100.00, 'Combustible', 'efectivo', 'Combustible JBC', $turno);
        $this->gasto($fecha, 20.00,  'Limpieza', 'efectivo', 'Sr. de limpieza', $turno);
        $this->gasto($fecha, 10.00,  'Alimentación personal', 'efectivo', 'Desayuno Modesto', $turno);

        // Anticipo nuevo de hoy (Yape).
        $anticipoId = DB::table('cliente_anticipos')->insertGetId([
            'empresa_id' => self::EMPRESA, 'cliente_id' => $this->clis['santarosa'],
            'user_id' => $this->cajera->id, 'cuenta_id' => $this->ctas['yape'],
            'fecha' => $fecha, 'monto' => 1200.00, 'saldo' => 1200.00,
            'tipo_valorizacion' => 'monto', 'estado' => 'activo',
            'observacion' => 'A cuenta de pedido de calaminas',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->tesoreria->registrar(
            self::EMPRESA, $this->ctas['yape'], $this->cajera, $fecha, 'ingreso', 1200.00,
            "Anticipo de cliente #{$anticipoId}", 'cliente_anticipo', $anticipoId,
        );

        // Otro descuento de planilla pendiente.
        DB::table('planilla_descuentos')->insert([
            'empresa_id' => self::EMPRESA, 'user_id' => $this->cajera->id,
            'registrado_por' => $this->admin->id, 'fecha' => $fecha, 'monto' => 80.00,
            'motivo' => 'Herramienta dañada — martillo demoledor',
            'estado' => 'pendiente', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────

    private function crearTurno(Carbon $dia, bool $cerrado, float $apertura, float $efectivoDia, float $faltante = 0): int
    {
        $esperado  = $apertura + $efectivoDia;
        $declarado = $esperado - $faltante;

        return DB::table('turnos')->insertGetId([
            'empresa_id' => self::EMPRESA, 'local_id' => self::LOCAL, 'caja_id' => self::CAJA,
            'user_id' => $this->cajera->id,
            'user_cierre_id' => $cerrado ? $this->cajera->id : null,
            'monto_apertura' => $apertura, 'monto_caja_chica' => 0,
            'monto_cierre_declarado' => $cerrado ? $declarado : null,
            'monto_cierre_esperado'  => $cerrado ? $esperado : null,
            'diferencia' => $cerrado ? -$faltante : null,
            'estado' => $cerrado ? 'cerrado' : 'abierto',
            'fecha_apertura' => $dia->copy()->setTime(8, 30),
            'fecha_cierre'   => $cerrado ? $dia->copy()->setTime(18, 30) : null,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /**
     * Crea una venta con items reales; contado (metodoContado) o crédito
     * (inicial + metodoInicial). Registra pagos y tesorería.
     *
     * @param array<array{0:string,1:float}> $items [alias producto, cantidad]
     */
    private function venta(
        int $turno, Carbon $momento, string $numero, ?string $cliente, array $items,
        ?string $metodoContado = null, bool $credito = false,
        float $inicial = 0, ?string $metodoInicial = null, ?Carbon $vence = null,
    ): int {
        $now = now();
        $total = 0;
        foreach ($items as [$alias, $cant]) {
            $total += round($cant * $this->prods[$alias]['venta'], 2);
        }
        $total = round($total, 2);

        $ventaId = DB::table('ventas')->insertGetId([
            'empresa_id' => self::EMPRESA, 'local_id' => self::LOCAL, 'turno_id' => $turno,
            'caja_id' => self::CAJA, 'user_id' => $this->cajera->id,
            'cliente_id' => $cliente ? $this->clis[$cliente] : DB::table('clientes')->where('es_cliente_general', true)->value('id'),
            'numero' => $numero, 'tipo_comprobante' => 'ticket',
            'subtotal' => $total, 'descuento_total' => 0, 'igv' => 0, 'total' => $total,
            'estado' => 'completada', 'fecha_venta' => $momento,
            'es_credito' => $credito,
            'monto_pagado' => $credito ? $inicial : $total,
            'saldo_pendiente' => $credito ? round($total - $inicial, 2) : 0,
            'fecha_vencimiento' => $credito ? $vence?->toDateString() : null,
            'created_at' => $now, 'updated_at' => $now,
        ]);

        foreach ($items as [$alias, $cant]) {
            $p = $this->prods[$alias];
            DB::table('venta_items')->insert([
                'venta_id' => $ventaId, 'producto_id' => $p['id'], 'producto_unidad_id' => $p['unidad'],
                'producto_nombre' => DB::table('productos')->where('id', $p['id'])->value('nombre'),
                'unidad_nombre' => 'Unidad', 'cantidad' => $cant, 'factor_conversion' => 1,
                'cantidad_base' => $cant, 'precio_unitario' => $p['venta'], 'precio_original' => $p['venta'],
                'descuento_item' => 0, 'subtotal' => round($cant * $p['venta'], 2),
                'incluye_igv' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        $metodo = $credito ? $metodoInicial : $metodoContado;
        $monto  = $credito ? $inicial : $total;
        if ($metodo && $monto > 0) {
            $metodoId = DB::table('metodos_pago')->where('nombre', $metodo)->value('id');
            DB::table('venta_pagos')->insert([
                'venta_id' => $ventaId, 'metodo_pago_id' => $metodoId,
                'monto' => $monto, 'vuelto' => 0, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $cuenta = match ($metodo) {
                'Efectivo' => $this->ctas['efectivo'],
                'Yape' => $this->ctas['yape'],
                'Transferencia' => $this->ctas['bcp'],
                default => $this->ctas['efectivo'],
            };
            $this->tesoreria->registrar(
                self::EMPRESA, $cuenta, $this->cajera, $momento->toDateString(), 'ingreso', $monto,
                ($credito ? "Venta {$numero} (inicial crédito)" : "Venta {$numero}"), 'venta', $ventaId,
            );
        }

        return $ventaId;
    }

    private function abono(int $ventaId, string $fecha, float $monto, string $cuentaAlias, string $ref): void
    {
        $numero = DB::table('ventas')->where('id', $ventaId)->value('numero');
        $abonoId = DB::table('venta_abonos')->insertGetId([
            'venta_id' => $ventaId, 'user_id' => $this->admin->id,
            'cuenta_id' => $this->ctas[$cuentaAlias], 'fecha' => $fecha, 'monto' => $monto,
            'referencia' => $ref, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('ventas')->where('id', $ventaId)->update([
            'monto_pagado' => DB::raw("monto_pagado + {$monto}"),
            'saldo_pendiente' => DB::raw("saldo_pendiente - {$monto}"),
            'updated_at' => now(),
        ]);
        $this->tesoreria->registrar(
            self::EMPRESA, $this->ctas[$cuentaAlias], $this->admin, $fecha, 'ingreso', $monto,
            "Abono venta {$numero}", 'venta_abono', $abonoId,
        );
    }

    private function gasto(string $fecha, float $monto, string $concepto, string $cuentaAlias, string $comentario, ?int $turno = null): void
    {
        $gastoId = DB::table('gastos')->insertGetId([
            'empresa_id' => self::EMPRESA, 'local_id' => self::LOCAL,
            'user_id' => $turno ? $this->cajera->id : $this->admin->id, 'turno_id' => $turno,
            'gasto_tipo_id' => $this->tipoDeConcepto[$concepto],
            'gasto_concepto_id' => $this->conceptos[$concepto],
            'monto' => $monto, 'fecha' => $fecha, 'comentario' => $comentario,
            'cuenta_id' => $this->ctas[$cuentaAlias],
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->tesoreria->registrar(
            self::EMPRESA, $this->ctas[$cuentaAlias], $turno ? $this->cajera : $this->admin,
            $fecha, 'egreso', $monto, "Gasto — {$concepto}: {$comentario}", 'gasto', $gastoId,
        );
    }

    private function cuotaDeuda(string $nombre, string $fecha, float $monto, string $cuentaAlias): void
    {
        $deuda = DB::table('deudas')->where('empresa_id', self::EMPRESA)->where('nombre', $nombre)->first();
        $pagoId = DB::table('deuda_pagos')->insertGetId([
            'deuda_id' => $deuda->id, 'user_id' => $this->admin->id,
            'cuenta_id' => $this->ctas[$cuentaAlias], 'fecha' => $fecha,
            'tipo' => 'amortizacion', 'monto' => $monto,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('deudas')->where('id', $deuda->id)->update([
            'saldo' => DB::raw("saldo - {$monto}"), 'updated_at' => now(),
        ]);
        // por_pagar + amortización = egreso · por_cobrar + amortización = ingreso
        $esIngreso = $deuda->direccion === 'por_cobrar';
        $this->tesoreria->registrar(
            self::EMPRESA, $this->ctas[$cuentaAlias], $this->admin, $fecha,
            $esIngreso ? 'ingreso' : 'egreso', $monto,
            "Cuota de deuda — {$nombre}", 'deuda_pago', $pagoId,
        );
    }
}
