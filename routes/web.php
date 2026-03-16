<?php

use App\Http\Controllers\Configuracion\EmpresaController;
use App\Http\Controllers\Configuracion\LocalController;
use App\Http\Controllers\Configuracion\ModuloController;
use App\Http\Controllers\Configuracion\PermisoController;
use App\Http\Controllers\Configuracion\RolController;
use App\Http\Controllers\Configuracion\UsuarioController;
use App\Http\Controllers\DashboardController;
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
    });
});

require __DIR__.'/auth.php';
