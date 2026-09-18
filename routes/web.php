<?php

use App\Http\Controllers\Auth\ColaboradorLoginController;
use App\Http\Controllers\EstacionMarcadoController;
use App\Http\Controllers\MarcacionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [ColaboradorLoginController::class, 'show'])->name('login');
    // throttle:10,1 -- 10 intentos por minuto por IP, para dificultar fuerza
    // bruta de contraseñas sin afectar el uso normal (un colaborador nunca
    // necesita 10 intentos en un minuto).
    Route::post('/login', [ColaboradorLoginController::class, 'store'])->middleware('throttle:10,1');
});

Route::post('/logout', [ColaboradorLoginController::class, 'destroy'])
    ->name('logout')
    ->middleware('auth');

// Rutas públicas (la pantalla física no inicia sesión) protegidas por la
// "clave" de estación (token_pantalla, ver EstacionMarcadoController) y con
// throttle -- sin sesión que las limite de otra forma, son el punto de la
// aplicación más expuesto a abuso/enumeración.
Route::middleware('throttle:30,1')->group(function () {
    Route::get('/estacion-marcado/{sucursal}/{puntoVenta?}', [EstacionMarcadoController::class, 'show'])->name('estacion-marcado.show');
    Route::get('/estacion-marcado/{sucursal}/{puntoVenta?}/token', [EstacionMarcadoController::class, 'token'])->name('estacion-marcado.token');
});

Route::middleware(['auth', 'throttle:30,1'])->group(function () {
    Route::get('/marcar', [MarcacionController::class, 'show'])->name('marcacion.show');
    Route::post('/marcar', [MarcacionController::class, 'store'])->name('marcacion.store');
    Route::get('/marcar/{marcacion}/confirmacion', [MarcacionController::class, 'confirmacion'])->name('marcacion.confirmacion');
});
