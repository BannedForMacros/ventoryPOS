<?php

use App\Http\Controllers\Catalogo\CategoriaController;
use App\Http\Controllers\Catalogo\ProductoController;
use App\Http\Controllers\Catalogo\UnidadMedidaController;
use App\Http\Controllers\Clientes\ClienteController;
use App\Http\Controllers\Clientes\DecolectaController;
use App\Http\Controllers\Proveedores\ProveedorController;
use App\Http\Controllers\Configuracion\AlmacenController;
use App\Http\Controllers\Configuracion\CajaController;
use App\Http\Controllers\Configuracion\EmpresaController;
use App\Http\Controllers\Configuracion\LocalController;
use App\Http\Controllers\Configuracion\CuentaController;
use App\Http\Controllers\Configuracion\MetodoPagoController;
use App\Http\Controllers\Configuracion\ModuloController;
use App\Http\Controllers\Configuracion\PermisoController;
use App\Http\Controllers\Configuracion\DevolucionMotivoController;
use App\Http\Controllers\Configuracion\RolController;
use App\Http\Controllers\Configuracion\SalidaTipoController;
use App\Http\Controllers\Devoluciones\DevolucionController;
use App\Http\Controllers\Configuracion\UsuarioController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Gastos\GastoController;
use App\Http\Controllers\Gastos\GastoTipoController;
use App\Http\Controllers\Turnos\TurnoController;
use App\Http\Controllers\Agenda\AgendaController;
use App\Http\Controllers\Reportes\ReporteAuditoriaController;
use App\Http\Controllers\Reportes\ReporteCajaController;
use App\Http\Controllers\Reportes\ReporteDevolucionController;
use App\Http\Controllers\Reportes\ReporteGastoController;
use App\Http\Controllers\Reportes\ReporteProductoController;
use App\Http\Controllers\Reportes\ReporteVentaController;
use App\Http\Controllers\Ventas\DescuentoConceptoController;
use App\Http\Controllers\Ventas\DescuentoLogController;
use App\Http\Controllers\Ventas\VentaController;
use App\Http\Controllers\Ventas\WhatsappController;
use App\Http\Controllers\Finanzas\AdelantoProveedorController;
use App\Http\Controllers\Finanzas\AnticipoClienteController;
use App\Http\Controllers\Finanzas\BalanceDiarioController;
use App\Http\Controllers\Finanzas\CuentasPorCobrarController;
use App\Http\Controllers\Finanzas\CuentasPorPagarController;
use App\Http\Controllers\Finanzas\DeudaController;
use App\Http\Controllers\Inventario\CierreInventarioController;
use App\Http\Controllers\Inventario\EntradaController;
use App\Http\Controllers\Inventario\SalidaController;
use App\Http\Controllers\Inventario\StockController;
use App\Http\Controllers\Inventario\TransferenciaController;
use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin'       => Route::has('login'),
        'canRegister'    => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion'     => PHP_VERSION,
    ]);
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::middleware('auth')->group(function () {
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    });

    // ── CONFIGURACIÓN ─────────────────────────────────────────────────────
    Route::prefix('configuracion')->name('configuracion.')->group(function () {
        Route::middleware('permiso:config.empresas')->group(function () {
            Route::resource('empresas', EmpresaController::class)->except(['show', 'create', 'edit']);
        });
        Route::middleware('permiso:config.locales')->group(function () {
            Route::resource('locales', LocalController::class)->except(['show', 'create', 'edit'])->parameters(['locales' => 'local']);
        });
        Route::middleware('permiso:config.roles')->group(function () {
            Route::resource('roles', RolController::class)->except(['show', 'create', 'edit'])->parameters(['roles' => 'rol']);
        });
        Route::middleware('permiso:config.modulos')->group(function () {
            Route::resource('modulos', ModuloController::class)->except(['show', 'create', 'edit']);
        });
        Route::middleware('permiso:config.usuarios')->group(function () {
            Route::resource('usuarios', UsuarioController::class)->except(['show', 'create', 'edit']);
        });
        Route::middleware('permiso:config.permisos,ver')->get('permisos', [PermisoController::class, 'index'])->name('permisos.index');
        Route::middleware('permiso:config.permisos,editar')->post('permisos/{rol}', [PermisoController::class, 'store'])->name('permisos.store');

        Route::middleware('permiso:configuracion.almacenes')->group(function () {
            Route::resource('almacenes', AlmacenController::class)->except(['show', 'create', 'edit'])->parameters(['almacenes' => 'almacen']);
        });

        Route::middleware('permiso:configuracion.salidas-tipos')->group(function () {
            Route::apiResource('salidas-tipos', SalidaTipoController::class)
                ->parameters(['salidas-tipos' => 'tipo'])
                ->except(['show']);
        });

        Route::middleware('permiso:configuracion.devolucion-motivos')->group(function () {
            Route::apiResource('devolucion-motivos', DevolucionMotivoController::class)
                ->parameters(['devolucion-motivos' => 'motivo'])
                ->except(['show']);
        });
    });

    // ── INVENTARIO ────────────────────────────────────────────────────────
    Route::prefix('inventario')->name('inventario.')->group(function () {
        // Stock
        Route::middleware('permiso:inventario.stock,ver')->get('stock', [StockController::class, 'index'])->name('stock.index');
        // Recalcular es una operación de mutación masiva → exige permiso "editar".
        Route::middleware('permiso:inventario.stock,editar')->post('stock/recalcular', [StockController::class, 'recalcular'])->name('stock.recalcular');

        // Entradas
        Route::middleware('permiso:inventario.entradas,ver')->get('entradas/crear', [EntradaController::class, 'create'])->name('entradas.create');
        Route::middleware('permiso:inventario.entradas,editar')->get('entradas/{entrada}/editar', [EntradaController::class, 'edit'])->name('entradas.edit');
        Route::middleware('permiso:inventario.entradas,editar')->post('entradas/{entrada}/confirmar', [EntradaController::class, 'confirmar'])->name('entradas.confirmar');
        // Pago: independiente del estado de la entrada, por eso fuera del apiResource.
        Route::middleware('permiso:inventario.entradas,editar')->post('entradas/{entrada}/pago', [EntradaController::class, 'actualizarPago'])->name('entradas.pago');
        // JSON para el modal "Ver detalle" del Index. Permiso ver porque no muta nada.
        Route::middleware('permiso:inventario.entradas,ver')->get('entradas/{entrada}/detalle-json', [EntradaController::class, 'detalleJson'])->name('entradas.detalle-json');
        Route::middleware('permiso:inventario.entradas')->group(function () {
            Route::apiResource('entradas', EntradaController::class)->except(['show']);
        });

        // Transferencias
        Route::middleware('permiso:inventario.transferencias,ver')->get('transferencias/crear', [TransferenciaController::class, 'create'])->name('transferencias.create');
        Route::middleware('permiso:inventario.transferencias,editar')->get('transferencias/{transferencia}/editar', [TransferenciaController::class, 'edit'])->name('transferencias.edit');
        Route::middleware('permiso:inventario.transferencias,editar')->post('transferencias/{transferencia}/enviar', [TransferenciaController::class, 'enviar'])->name('transferencias.enviar');
        Route::middleware('permiso:inventario.transferencias,editar')->post('transferencias/{transferencia}/recibir', [TransferenciaController::class, 'recibir'])->name('transferencias.recibir');
        Route::middleware('permiso:inventario.transferencias,editar')->post('transferencias/{transferencia}/anular', [TransferenciaController::class, 'anular'])->name('transferencias.anular');
        Route::middleware('permiso:inventario.transferencias')->group(function () {
            Route::apiResource('transferencias', TransferenciaController::class);
        });

        // Cierres de inventario
        Route::middleware('permiso:inventario.cierres,ver')->get('cierres/crear', [CierreInventarioController::class, 'create'])->name('cierres.create');
        Route::middleware('permiso:inventario.cierres,ver')->get('cierres/productos-para-declarar', [CierreInventarioController::class, 'productosParaDeclarar'])->name('cierres.productos');
        Route::middleware('permiso:inventario.cierres,editar')->post('cierres/{cierre}/confirmar', [CierreInventarioController::class, 'confirmar'])->name('cierres.confirmar');
        Route::middleware('permiso:inventario.cierres')->group(function () {
            Route::apiResource('cierres', CierreInventarioController::class)
                ->parameters(['cierres' => 'cierre'])
                ->except(['edit', 'update']);
        });

        // Salidas
        Route::middleware('permiso:inventario.salidas,ver')->get('salidas/crear', [SalidaController::class, 'create'])->name('salidas.create');
        Route::middleware('permiso:inventario.salidas,editar')->get('salidas/{salida}/editar', [SalidaController::class, 'edit'])->name('salidas.edit');
        Route::middleware('permiso:inventario.salidas,editar')->post('salidas/{salida}/confirmar', [SalidaController::class, 'confirmar'])->name('salidas.confirmar');
        Route::middleware('permiso:inventario.salidas')->group(function () {
            Route::apiResource('salidas', SalidaController::class)
                ->parameters(['salidas' => 'salida'])
                ->except(['show']);
        });
    });

    // ── CLIENTES ─────────────────────────────────────────────────────────
    Route::prefix('clientes')->name('clientes.')->group(function () {
        Route::middleware('permiso:clientes,ver')->get('/', [ClienteController::class, 'index'])->name('index');
        Route::middleware('permiso:clientes,crear')->post('/', [ClienteController::class, 'store'])->name('store');
        Route::middleware('permiso:clientes,ver')->get('/{cliente}', [ClienteController::class, 'show'])->name('show');
        Route::middleware('permiso:clientes,editar')->put('/{cliente}', [ClienteController::class, 'update'])->name('update');
        Route::middleware('permiso:clientes,eliminar')->delete('/{cliente}', [ClienteController::class, 'destroy'])->name('destroy');
    });

    // ── DEVOLUCIONES ─────────────────────────────────────────────────────
    Route::prefix('devoluciones')->name('devoluciones.')->group(function () {
        Route::middleware('permiso:devoluciones,ver')->get('/', [DevolucionController::class, 'index'])->name('index');
        Route::middleware('permiso:devoluciones,ver')->get('/crear', [DevolucionController::class, 'create'])->name('create');
        Route::middleware('permiso:devoluciones,ver')->get('/buscar-venta', [DevolucionController::class, 'buscarVenta'])->name('buscar-venta');
        Route::middleware('permiso:devoluciones,crear')->post('/', [DevolucionController::class, 'store'])->name('store');
        Route::middleware('permiso:devoluciones,ver')->get('/{devolucion}', [DevolucionController::class, 'show'])->name('show');
        Route::middleware('permiso:devoluciones,editar')->post('/{devolucion}/aprobar', [DevolucionController::class, 'aprobar'])->name('aprobar');
        Route::middleware('permiso:devoluciones,editar')->post('/{devolucion}/rechazar', [DevolucionController::class, 'rechazar'])->name('rechazar');
        Route::middleware('permiso:devoluciones,editar')->post('/{devolucion}/anular', [DevolucionController::class, 'anular'])->name('anular');
    });

    // ── PROVEEDORES ──────────────────────────────────────────────────────
    Route::prefix('proveedores')->name('proveedores.')->group(function () {
        Route::middleware('permiso:proveedores,ver')->get('/', [ProveedorController::class, 'index'])->name('index');
        Route::middleware('permiso:proveedores,crear')->post('/', [ProveedorController::class, 'store'])->name('store');
        Route::middleware('permiso:proveedores,ver')->get('/{proveedor}', [ProveedorController::class, 'show'])->name('show');
        Route::middleware('permiso:proveedores,editar')->put('/{proveedor}', [ProveedorController::class, 'update'])->name('update');
        Route::middleware('permiso:proveedores,eliminar')->delete('/{proveedor}', [ProveedorController::class, 'destroy'])->name('destroy');
    });

    // ── MÉTODOS DE PAGO Y CUENTAS ────────────────────────────────────────
    Route::middleware('permiso:configuracion.metodos-pago')->group(function () {
        Route::apiResource('configuracion/metodos-pago', MetodoPagoController::class)
             ->names('configuracion.metodos-pago')
             ->except(['show']);
    });

    Route::middleware('permiso:configuracion.cuentas')->group(function () {
        Route::apiResource('configuracion/cuentas', CuentaController::class)
             ->names('configuracion.cuentas')
             ->except(['show']);
    });

    // ── DECOLECTA (consulta DNI/RUC) ─────────────────────────────────────
    // Endpoint sensible: consulta datos personales (Habeas Data en Perú, Ley 29733)
    // y consume cuota del token Decolecta. El controlador exige que el usuario
    // pueda crear clientes O proveedores (los dos formularios que lo invocan).
    // El throttle defiende contra enumeracion masiva.
    Route::middleware('throttle:30,1')->prefix('api/decolecta')->group(function () {
        Route::post('dni', [DecolectaController::class, 'consultarDni'])->name('decolecta.dni');
        Route::post('ruc', [DecolectaController::class, 'consultarRuc'])->name('decolecta.ruc');
    });

    // ── CATÁLOGO ─────────────────────────────────────────────────────────
    Route::prefix('catalogo')->name('catalogo.')->group(function () {
        Route::middleware('permiso:catalogo.categorias')->group(function () {
            Route::apiResource('categorias', CategoriaController::class)->except(['show']);
        });
        Route::middleware('permiso:catalogo.unidades')->group(function () {
            Route::apiResource('unidades-medida', UnidadMedidaController::class)->except(['show']);
        });
        Route::middleware('permiso:catalogo.productos,ver')->get('productos/crear', [ProductoController::class, 'create'])->name('productos.create');
        Route::middleware('permiso:catalogo.productos,editar')->get('productos/{producto}/editar', [ProductoController::class, 'edit'])->name('productos.edit');
        Route::middleware('permiso:catalogo.productos')->group(function () {
            Route::apiResource('productos', ProductoController::class)->except(['show']);
        });
    });

    // ── CAJAS ────────────────────────────────────────────────────────────
    Route::middleware('permiso:configuracion.cajas')->group(function () {
        Route::apiResource('configuracion/cajas', CajaController::class)
             ->names('configuracion.cajas')
             ->except(['show']);
    });

    // ── TIPOS DE GASTO ──────────────────────────────────────────────────
    Route::prefix('configuracion/gastos')->name('configuracion.gastos.')->group(function () {
        Route::middleware('permiso:configuracion.gastos-tipos')->group(function () {
            Route::apiResource('tipos', GastoTipoController::class)->except(['show']);
        });
    });

    // ── TURNOS ───────────────────────────────────────────────────────────
    Route::prefix('turnos')->name('turnos.')->group(function () {
        Route::middleware('permiso:turnos,ver')->get('/', [TurnoController::class, 'index'])->name('index');
        Route::middleware('permiso:turnos,ver')->get('/activo', [TurnoController::class, 'turnoActivo'])->name('activo');
        Route::middleware('permiso:turnos,crear')->post('/abrir', [TurnoController::class, 'abrir'])->name('abrir');
        Route::middleware('permiso:turnos,ver')->get('/{turno}', [TurnoController::class, 'show'])->name('show');
        Route::middleware('permiso:turnos,editar')->get('/{turno}/cerrar', [TurnoController::class, 'cerrarPage'])->name('cerrar.page');
        Route::middleware('permiso:turnos,editar')->post('/{turno}/cerrar', [TurnoController::class, 'cerrar'])->name('cerrar');
        Route::middleware('permiso:turnos,editar')->post('/{turno}/reabrir', [TurnoController::class, 'reabrir'])->name('reabrir');
    });

    // ── GASTOS ───────────────────────────────────────────────────────────
    Route::middleware('permiso:gastos')->group(function () {
        Route::apiResource('gastos', GastoController::class)->except(['show', 'update']);
    });

    // ── POS ──────────────────────────────────────────────────────────────
    Route::middleware('permiso:pos,ver')->get('/pos', [VentaController::class, 'pos'])->name('pos.index');

    // ── VENTAS ───────────────────────────────────────────────────────────
    // M17: throttle:60,1 en `store` evita que un usuario autenticado (sesion
    // robada, bug de FE en loop, cajero malicioso) genere cientos de ventas
    // por segundo. 60/min es muy holgado para uso normal (un cajero rapido
    // raramente pasa de 1 venta cada 10s) y bloquea abuso obvio.
    Route::prefix('ventas')->name('ventas.')->group(function () {
        Route::middleware('permiso:ventas,ver')->get('/', [VentaController::class, 'index'])->name('index');
        Route::middleware(['permiso:ventas,crear', 'throttle:60,1'])->post('/', [VentaController::class, 'store'])->name('store');
        Route::middleware('permiso:ventas,ver')->get('/{venta}', [VentaController::class, 'show'])->name('show');
        Route::middleware('permiso:ventas,editar')->post('/{venta}/anular', [VentaController::class, 'anular'])->name('anular');
    });

    // ── CONCEPTOS DE DESCUENTO ───────────────────────────────────────────
    Route::middleware('permiso:configuracion.descuento-conceptos')->group(function () {
        Route::apiResource('configuracion/descuento-conceptos', DescuentoConceptoController::class)
            ->names('configuracion.descuento-conceptos')
            ->except(['show']);
    });

    // ── AGENDA / CITAS ───────────────────────────────────────────────────
    Route::prefix('agenda')->name('agenda.')->group(function () {
        Route::middleware('permiso:agenda,ver')->get('/',                              [AgendaController::class, 'index'])->name('index');
        Route::middleware('permiso:agenda,crear')->get('/crear',                       [AgendaController::class, 'create'])->name('create');
        Route::middleware('permiso:agenda,crear')->post('/',                           [AgendaController::class, 'store'])->name('store');
        Route::middleware('permiso:agenda,ver')->get('/{cita}',                        [AgendaController::class, 'show'])->name('show');
        Route::middleware('permiso:agenda,editar')->get('/{cita}/editar',              [AgendaController::class, 'edit'])->name('edit');
        Route::middleware('permiso:agenda,editar')->put('/{cita}',                     [AgendaController::class, 'update'])->name('update');
        Route::middleware('permiso:agenda,eliminar')->delete('/{cita}',                [AgendaController::class, 'destroy'])->name('destroy');
        // Transiciones de estado
        Route::middleware('permiso:agenda,editar')->post('/{cita}/confirmar',          [AgendaController::class, 'confirmar'])->name('confirmar');
        Route::middleware('permiso:agenda,editar')->post('/{cita}/iniciar',            [AgendaController::class, 'iniciar'])->name('iniciar');
        Route::middleware('permiso:agenda,editar')->post('/{cita}/cancelar',           [AgendaController::class, 'cancelar'])->name('cancelar');
        Route::middleware('permiso:agenda,editar')->post('/{cita}/no-asistio',         [AgendaController::class, 'noAsistio'])->name('no_asistio');
        // Completar = redirige a POS prellenado
        Route::middleware('permiso:agenda,editar')->post('/{cita}/completar',          [AgendaController::class, 'completar'])->name('completar');
    });

    // ── FINANZAS ─────────────────────────────────────────────────────────
    // Módulo financiero del balance diario: cuentas por cobrar/pagar,
    // anticipos de clientes, adelantos a proveedores, deudas y el balance.
    Route::prefix('finanzas')->name('finanzas.')->group(function () {
        // Cuentas por cobrar (ventas a crédito)
        Route::middleware('permiso:finanzas.cuentas-por-cobrar,ver')->get('cuentas-por-cobrar', [CuentasPorCobrarController::class, 'index'])->name('cxc.index');
        Route::middleware('permiso:finanzas.cuentas-por-cobrar,crear')->post('cuentas-por-cobrar/{venta}/abonar', [CuentasPorCobrarController::class, 'abonar'])->name('cxc.abonar');

        // Cuentas por pagar (proveedores, con abonos parciales)
        Route::middleware('permiso:finanzas.cuentas-por-pagar,ver')->get('cuentas-por-pagar', [CuentasPorPagarController::class, 'index'])->name('cxp.index');
        Route::middleware('permiso:finanzas.cuentas-por-pagar,crear')->post('cuentas-por-pagar/{entrada}/abonar', [CuentasPorPagarController::class, 'abonar'])->name('cxp.abonar');

        // Anticipos de clientes
        Route::middleware('permiso:finanzas.anticipos,ver')->get('anticipos', [AnticipoClienteController::class, 'index'])->name('anticipos.index');
        Route::middleware('permiso:finanzas.anticipos,crear')->post('anticipos', [AnticipoClienteController::class, 'store'])->name('anticipos.store');
        Route::middleware('permiso:finanzas.anticipos,editar')->post('anticipos/{anticipo}/aplicar', [AnticipoClienteController::class, 'aplicar'])->name('anticipos.aplicar');
        Route::middleware('permiso:finanzas.anticipos,editar')->post('anticipos/{anticipo}/anular', [AnticipoClienteController::class, 'anular'])->name('anticipos.anular');

        // Adelantos a proveedores
        Route::middleware('permiso:finanzas.adelantos,ver')->get('adelantos', [AdelantoProveedorController::class, 'index'])->name('adelantos.index');
        Route::middleware('permiso:finanzas.adelantos,crear')->post('adelantos', [AdelantoProveedorController::class, 'store'])->name('adelantos.store');
        Route::middleware('permiso:finanzas.adelantos,editar')->post('adelantos/{adelanto}/anular', [AdelantoProveedorController::class, 'anular'])->name('adelantos.anular');

        // Deudas y préstamos
        Route::middleware('permiso:finanzas.deudas,ver')->get('deudas', [DeudaController::class, 'index'])->name('deudas.index');
        Route::middleware('permiso:finanzas.deudas,crear')->post('deudas', [DeudaController::class, 'store'])->name('deudas.store');
        Route::middleware('permiso:finanzas.deudas,editar')->post('deudas/{deuda}/pago', [DeudaController::class, 'registrarPago'])->name('deudas.pago');
        Route::middleware('permiso:finanzas.deudas,editar')->post('deudas/{deuda}/anular', [DeudaController::class, 'anular'])->name('deudas.anular');

        // Balance diario
        Route::middleware('permiso:finanzas.balance,ver')->get('balance', [BalanceDiarioController::class, 'index'])->name('balance.index');
        Route::middleware('permiso:finanzas.balance,ver')->get('balance/{fecha}', [BalanceDiarioController::class, 'show'])->name('balance.show');
        Route::middleware('permiso:finanzas.balance,editar')->put('balance/items/{item}', [BalanceDiarioController::class, 'actualizarItem'])->name('balance.items.update');
        Route::middleware('permiso:finanzas.balance,editar')->post('balance/{balance}/items', [BalanceDiarioController::class, 'agregarItem'])->name('balance.items.store');
        Route::middleware('permiso:finanzas.balance,editar')->delete('balance/items/{item}', [BalanceDiarioController::class, 'eliminarItem'])->name('balance.items.destroy');
        Route::middleware('permiso:finanzas.balance,editar')->post('balance/{balance}/confirmar', [BalanceDiarioController::class, 'confirmar'])->name('balance.confirmar');
    });

    // ── REPORTES ─────────────────────────────────────────────────────────
    Route::prefix('reportes')->name('reportes.')->group(function () {
        Route::middleware('permiso:reportes.descuentos,ver')->get('descuentos',   [DescuentoLogController::class,        'index'])->name('descuentos');
        Route::middleware('permiso:reportes.ventas,ver')->get('ventas',           [ReporteVentaController::class,        'index'])->name('ventas');
        Route::middleware('permiso:reportes.productos,ver')->get('productos',     [ReporteProductoController::class,     'index'])->name('productos');
        Route::middleware('permiso:reportes.caja,ver')->get('caja',               [ReporteCajaController::class,         'index'])->name('caja');
        Route::middleware('permiso:reportes.gastos,ver')->get('gastos',           [ReporteGastoController::class,        'index'])->name('gastos');
        Route::middleware('permiso:reportes.devoluciones,ver')->get('devoluciones', [ReporteDevolucionController::class, 'index'])->name('devoluciones');
        Route::middleware('permiso:reportes.auditoria,ver')->get('auditoria',     [ReporteAuditoriaController::class,    'index'])->name('auditoria');
    });

    // ── WHATSAPP ─────────────────────────────────────────────────────────
    // Cada endpoint exige el permiso del flujo POS (`ventas.crear`).
    // El controlador audita cada intento via AuditoriaService.
    Route::prefix('whatsapp')->name('whatsapp.')->group(function () {
        Route::middleware('permiso:ventas,crear')->post('aprobacion', [WhatsappController::class, 'urlAprobacion'])->name('aprobacion');
        Route::middleware('permiso:ventas,crear')->post('confirmacion/{venta}', [WhatsappController::class, 'urlConfirmacion'])->name('confirmacion');
    });
});

require __DIR__.'/auth.php';
