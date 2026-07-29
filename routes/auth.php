<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    // ── REGISTRO PÚBLICO: DESACTIVADO A PROPÓSITO ────────────────────────────
    //
    // ventoryPOS es software cerrado: las cuentas las crea el proveedor, y los
    // usuarios de cada empresa se dan de alta desde `/usuarios`
    // (`Configuracion\UsuarioController::store()`), que asigna el `empresa_id`
    // del administrador que los crea.
    //
    // Además de sobrar, este endpoint estaba ROTO: `RegisteredUserController::store()`
    // hace `User::create()` con name/email/password y nada más, mientras que
    // `users.empresa_id` es NOT NULL. Devolvía un 500 ante CUALQUIER entrada, y
    // `Welcome.tsx` —página pública— enlazaba aquí en tres sitios.
    //
    // Las rutas se comentan en vez de borrarse para poder recuperarlas. Ojo si se
    // reactivan: hay que decidir antes a qué empresa pertenece un auto-registrado,
    // o volverá a fallar igual. La interfaz se reengancha sola, porque los enlaces
    // de `Welcome.tsx` y `Login.tsx` dependen de `Route::has('register')`.
    //
    // Route::get('register', [RegisteredUserController::class, 'create'])
    //     ->name('register');
    //
    // Route::post('register', [RegisteredUserController::class, 'store']);

    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])
        ->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('reset-password', [NewPasswordController::class, 'store'])
        ->name('password.store');
});

Route::middleware('auth')->group(function () {
    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');
});
