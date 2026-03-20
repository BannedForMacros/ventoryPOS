<?php

use App\Http\Controllers\Catalogo\CategoriaController;
use App\Http\Controllers\Catalogo\ProductoController;
use App\Http\Controllers\Catalogo\UnidadMedidaController;
use App\Http\Controllers\Clientes\ClienteController;
use App\Http\Controllers\Clientes\DecolectaController;
use App\Http\Controllers\Configuracion\AlmacenController;
use App\Http\Controllers\Configuracion\CajaController;
use App\Http\Controllers\Configuracion\EmpresaController;
use App\Http\Controllers\Configuracion\LocalController;
use App\Http\Controllers\Configuracion\CuentaController;
use App\Http\Controllers\Configuracion\MetodoPagoController;
use App\Http\Controllers\Configuracion\ModuloController;
use App\Http\Controllers\Configuracion\PermisoController;
use App\Http\Controllers\Configuracion\RolController;
use App\Http\Controllers\Configuracion\UsuarioController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Gastos\GastoController;
use App\Http\Controllers\Gastos\GastoTipoController;
use App\Http\Controllers\Turnos\TurnoController;
use App\Http\Controllers\Ventas\DescuentoConceptoController;
use App\Http\Controllers\Ventas\DescuentoLogController;
use App\Http\Controllers\Ventas\VentaController;
use App\Http\Controllers\Ventas\WhatsappController;
use App\Http\Controllers\Inventario\EntradaController;
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

    Route::prefix('configuracion')->name('configuracion.')->group(function () {
        Route::resource('empresas', EmpresaController::class)->except(['show', 'create', 'edit']);
        Route::resource('locales', LocalController::class)->except(['show', 'create', 'edit']);
        Route::resource('roles', RolController::class)->except(['show', 'create', 'edit']);
        Route::resource('modulos', ModuloController::class)->except(['show', 'create', 'edit']);
        Route::resource('usuarios', UsuarioController::class)->except(['show', 'create', 'edit']);
        Route::get('permisos', [PermisoController::class, 'index'])->name('permisos.index');
        Route::post('permisos/{rol}', [PermisoController::class, 'store'])->name('permisos.store');
        Route::resource('almacenes', AlmacenController::class)->except(['show', 'create', 'edit'])->parameters(['almacenes' => 'almacen']);
    });

    Route::prefix('inventario')->name('inventario.')->group(function () {
        Route::get('stock', [StockController::class, 'index'])->name('stock.index');
        Route::post('stock/recalcular', [StockController::class, 'recalcular'])->name('stock.recalcular');

        Route::get('entradas/crear', [EntradaController::class, 'create'])->name('entradas.create');
        Route::get('entradas/{entrada}/editar', [EntradaController::class, 'edit'])->name('entradas.edit');
        Route::post('entradas/{entrada}/confirmar', [EntradaController::class, 'confirmar'])->name('entradas.confirmar');
        Route::apiResource('entradas', EntradaController::class)->except(['show']);

        Route::get('transferencias/crear', [TransferenciaController::class, 'create'])->name('transferencias.create');
        Route::post('transferencias/{transferencia}/confirmar', [TransferenciaController::class, 'confirmar'])->name('transferencias.confirmar');
        Route::apiResource('transferencias', TransferenciaController::class)->except(['show', 'edit', 'update']);
    });

    // Clientes
    Route::prefix('clientes')->name('clientes.')->group(function () {
        Route::get('/', [ClienteController::class, 'index'])->name('index');
        Route::post('/', [ClienteController::class, 'store'])->name('store');
        Route::get('/{cliente}', [ClienteController::class, 'show'])->name('show');
        Route::put('/{cliente}', [ClienteController::class, 'update'])->name('update');
        Route::delete('/{cliente}', [ClienteController::class, 'destroy'])->name('destroy');
    });

    // Métodos de pago y Cuentas (dentro de configuración)
    Route::apiResource('configuracion/metodos-pago', MetodoPagoController::class)
         ->names('configuracion.metodos-pago')
         ->except(['show']);

    Route::apiResource('configuracion/cuentas', CuentaController::class)
         ->names('configuracion.cuentas')
         ->except(['show']);

    // API interna Decolecta (con throttle)
    Route::middleware('throttle:30,1')->prefix('api/decolecta')->group(function () {
        Route::post('dni', [DecolectaController::class, 'consultarDni'])->name('decolecta.dni');
        Route::post('ruc', [DecolectaController::class, 'consultarRuc'])->name('decolecta.ruc');
    });

    Route::prefix('catalogo')->name('catalogo.')->group(function () {
        Route::apiResource('categorias', CategoriaController::class)->except(['show']);
        Route::apiResource('unidades-medida', UnidadMedidaController::class)->except(['show']);
        Route::get('productos/crear', [ProductoController::class, 'create'])->name('productos.create');
        Route::get('productos/{producto}/editar', [ProductoController::class, 'edit'])->name('productos.edit');
        Route::apiResource('productos', ProductoController::class)->except(['show']);
    });

    // Cajas
    Route::apiResource('configuracion/cajas', CajaController::class)
         ->names('configuracion.cajas')
         ->except(['show']);

    // Tipos de gasto
    Route::prefix('configuracion/gastos')->name('configuracion.gastos.')->group(function () {
        Route::apiResource('tipos', GastoTipoController::class)->except(['show']);
    });

    // Turnos
    Route::prefix('turnos')->name('turnos.')->group(function () {
        Route::get('/', [TurnoController::class, 'index'])->name('index');
        Route::get('/activo', [TurnoController::class, 'turnoActivo'])->name('activo');
        Route::post('/abrir', [TurnoController::class, 'abrir'])->name('abrir');
        Route::post('/{turno}/cerrar', [TurnoController::class, 'cerrar'])->name('cerrar');
    });

    // Gastos
    Route::apiResource('gastos', GastoController::class)->except(['show', 'update']);

    // POS
    Route::get('/pos', [VentaController::class, 'pos'])->name('pos.index');

    // Ventas
    Route::prefix('ventas')->name('ventas.')->group(function () {
        Route::get('/', [VentaController::class, 'index'])->name('index');
        Route::post('/', [VentaController::class, 'store'])->name('store');
        Route::get('/{venta}', [VentaController::class, 'show'])->name('show');
        Route::post('/{venta}/anular', [VentaController::class, 'anular'])->name('anular');
    });

    // Conceptos de descuento (configuración)
    Route::apiResource('configuracion/descuento-conceptos', DescuentoConceptoController::class)
        ->names('configuracion.descuento-conceptos')
        ->except(['show']);

    // Reporte de descuentos
    Route::get('reportes/descuentos', [DescuentoLogController::class, 'index'])->name('reportes.descuentos');

    // WhatsApp (URLs de notificación)
    Route::prefix('whatsapp')->name('whatsapp.')->group(function () {
        Route::post('aprobacion', [WhatsappController::class, 'urlAprobacion'])->name('aprobacion');
        Route::post('confirmacion/{venta}', [WhatsappController::class, 'urlConfirmacion'])->name('confirmacion');
    });
});

require __DIR__.'/auth.php';
