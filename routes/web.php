<?php

use App\Http\Controllers\Auth\ColaboradorLoginController;
use App\Http\Controllers\EstacionMarcadoController;
use App\Http\Controllers\HorarioColaboradorController;
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
    // ->where() numérico: sin esto, un segmento no numérico (ej. "undefined"
    // -- visto en producción real, 2026-09-18) llega intacto hasta el
    // binding del modelo y revienta con un 500 de SQL crudo en vez de un
    // 404 limpio, la misma respuesta que ya se espera para cualquier ID
    // inexistente.
    Route::get('/estacion-marcado/{sucursal}/{puntoVenta?}', [EstacionMarcadoController::class, 'show'])
        ->name('estacion-marcado.show')
        ->where(['sucursal' => '[0-9]+', 'puntoVenta' => '[0-9]+']);
    Route::get('/estacion-marcado/{sucursal}/{puntoVenta?}/token', [EstacionMarcadoController::class, 'token'])
        ->name('estacion-marcado.token')
        ->where(['sucursal' => '[0-9]+', 'puntoVenta' => '[0-9]+']);
});

Route::middleware(['auth', 'throttle:30,1'])->group(function () {
    Route::get('/marcar', [MarcacionController::class, 'show'])->name('marcacion.show');
    Route::post('/marcar', [MarcacionController::class, 'store'])->name('marcacion.store');
    Route::get('/marcar/{marcacion}/confirmacion', [MarcacionController::class, 'confirmacion'])
        ->name('marcacion.confirmacion')
        ->where('marcacion', '[0-9]+');

    // El colaborador ve su propio horario asignado (AsignacionTurno), el
    // mismo que carga el encargado desde el Calendario de turnos / Asignación
    // masiva del panel admin -- de solo lectura, siempre derivado del usuario
    // autenticado (nunca de un id en la URL), igual que /marcar.
    Route::get('/mi-horario', [HorarioColaboradorController::class, 'show'])->name('horario.show');
});
