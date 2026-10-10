<?php

use App\Http\Controllers\Auth\ColaboradorLoginController;
use App\Http\Controllers\EstacionMarcadoController;
use App\Http\Controllers\EnlaceAccesoColaboradorController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\HorarioColaboradorController;
use App\Http\Controllers\MarcacionController;
use App\Http\Controllers\MisMarcacionesHoyController;
use App\Http\Controllers\PwaController;
use App\Http\Controllers\PortalAccesoController;
use App\Http\Controllers\VisitaSupervisorController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('acceso.portal');
});

// Monitor externo: comprueba aplicación, base de datos y scheduler sin
// exponer datos internos ni requerir una sesión de colaborador.
Route::get('/health', HealthController::class)->middleware('throttle:60,1')->name('health');

Route::get('/manifest.webmanifest', [PwaController::class, 'manifest'])->name('pwa.manifest');
Route::get('/sw.js', [PwaController::class, 'serviceWorker'])->name('pwa.service-worker');

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

// El GET solo muestra la confirmación: previews de mensajería o correo no
// consumen el enlace. El POST, protegido por CSRF, inicia la sesión.
Route::middleware('throttle:10,1')->group(function () {
    Route::get('/acceso/{token}', [EnlaceAccesoColaboradorController::class, 'show'])
        ->name('enlace-acceso.show')
        ->where('token', '[A-Za-z0-9]{64}');
    Route::post('/acceso/{token}', [EnlaceAccesoColaboradorController::class, 'consumir'])
        ->name('enlace-acceso.consumir')
        ->where('token', '[A-Za-z0-9]{64}');
});

// Rutas públicas (la pantalla física no inicia sesión) protegidas por la
// "clave" de estación (token_pantalla, ver EstacionMarcadoController) y con
// throttle -- sin sesión que las limite de otra forma, son el punto de la
// aplicación más expuesto a abuso/enumeración.
Route::middleware('throttle:30,1')->group(function () {
    // Manifest por estación para instalar una pantalla QR como aplicación de
    // escritorio. Requiere la misma clave privada de la estación física.
    Route::get('/pwa/estacion/{tipo}/{sucursal}/{puntoVenta}/manifest.webmanifest', [PwaController::class, 'stationManifest'])
        ->name('pwa.station.manifest')
        ->where([
            'tipo' => 'asistencia|visita',
            'sucursal' => '[0-9]+',
            'puntoVenta' => '[0-9]+',
        ]);

    // ->where() numérico: sin esto, un segmento no numérico (ej. "undefined"
    // -- visto en producción real, 2026-09-18) llega intacto hasta el
    // binding del modelo y revienta con un 500 de SQL crudo en vez de un
    // 404 limpio, la misma respuesta que ya se espera para cualquier ID
    // inexistente.
    Route::get('/estacion-marcado/{sucursal}/token', [EstacionMarcadoController::class, 'token'])
        ->name('estacion-marcado.token')
        ->where('sucursal', '[0-9]+');
    Route::get('/estacion-marcado/{sucursal}/{puntoVenta}/token', [EstacionMarcadoController::class, 'token'])
        ->name('estacion-marcado.punto-venta.token')
        ->where(['sucursal' => '[0-9]+', 'puntoVenta' => '[0-9]+']);
    Route::get('/estacion-marcado/{sucursal}/{puntoVenta?}', [EstacionMarcadoController::class, 'show'])
        ->name('estacion-marcado.show')
        ->where(['sucursal' => '[0-9]+', 'puntoVenta' => '[0-9]+']);
    Route::get('/estacion-visita/{sucursal}/{puntoVenta?}', [VisitaSupervisorController::class, 'estacion'])
        ->name('estacion-visita.show')
        ->where(['sucursal' => '[0-9]+', 'puntoVenta' => '[0-9]+']);
    Route::get('/estacion-visita/{sucursal}/token', [VisitaSupervisorController::class, 'token'])
        ->name('estacion-visita.token')
        ->where('sucursal', '[0-9]+');
    Route::get('/estacion-visita/{sucursal}/{puntoVenta}/token', [VisitaSupervisorController::class, 'token'])
        ->name('estacion-visita.punto-venta.token')
        ->where(['sucursal' => '[0-9]+', 'puntoVenta' => '[0-9]+']);
    // Los QR estáticos emitidos antes de la rotación no registran visitas:
    // llevan a la nueva estación dinámica para que sigan siendo utilizables.
    Route::get('/visitas-supervisor/{sucursal}/{puntoVenta?}', [VisitaSupervisorController::class, 'estacion'])
        ->name('visita-supervisor.legacy')
        ->where(['sucursal' => '[0-9]+', 'puntoVenta' => '[0-9]+']);
});

Route::middleware(['auth', 'throttle:30,1'])->group(function () {
    // Acceso principal de la aplicación. Para una sola capacidad, redirige
    // automáticamente; para cuentas mixtas muestra una elección explícita.
    Route::get('/ingresar', PortalAccesoController::class)->name('acceso.portal');

    Route::get('/visitas-supervisor/esperando', [VisitaSupervisorController::class, 'esperando'])
        ->name('visita-supervisor.esperando');
    Route::get('/visitas-supervisor', [VisitaSupervisorController::class, 'show'])
        ->name('visita-supervisor.show');
    Route::post('/visitas-supervisor', [VisitaSupervisorController::class, 'store'])
        ->name('visita-supervisor.store');

    Route::get('/marcar', [MarcacionController::class, 'show'])->name('marcacion.show');
    Route::get('/marcar/validar-qr', [MarcacionController::class, 'validarQr'])->name('marcacion.validar-qr');
    Route::post('/marcar', [MarcacionController::class, 'store'])->name('marcacion.store');
    Route::get('/marcar/{marcacion}/confirmacion', [MarcacionController::class, 'confirmacion'])
        ->name('marcacion.confirmacion')
        ->where('marcacion', '[0-9]+');

    // El colaborador ve su propio horario asignado (AsignacionTurno), el
    // mismo que carga el encargado desde el Calendario de turnos / Asignación
    // masiva del panel admin -- de solo lectura, siempre derivado del usuario
    // autenticado (nunca de un id en la URL), igual que /marcar.
    Route::get('/mi-horario', [HorarioColaboradorController::class, 'show'])->name('horario.show');
    Route::get('/mis-marcaciones', [MisMarcacionesHoyController::class, 'show'])->name('marcaciones-hoy.show');
});
