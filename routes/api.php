<?php

use App\Http\Controllers\Api\V1\MobileMarcacionController;
use App\Http\Controllers\Api\V1\MobileSessionController;
use App\Http\Controllers\Api\V1\MobileVisitaSupervisorController;
use Illuminate\Support\Facades\Route;

/*
 * API exclusiva para el instalador móvil. Nunca entrega reportes ni datos del
 * panel administrativo; sólo expone las operaciones que el usuario puede
 * realizar desde el teléfono.
 */
Route::prefix('mobile/v1')->middleware('throttle:20,1')->group(function (): void {
    Route::post('/sesion', [MobileSessionController::class, 'store'])
        ->middleware('throttle:8,1');

    Route::middleware(['auth:sanctum', 'mobile.access'])->group(function (): void {
        Route::get('/sesion', [MobileSessionController::class, 'show']);
        Route::delete('/sesion', [MobileSessionController::class, 'destroy']);

        Route::post('/marcaciones/validar-qr', [MobileMarcacionController::class, 'validarQr']);
        Route::post('/marcaciones', [MobileMarcacionController::class, 'store']);
        Route::get('/marcaciones/hoy', [MobileMarcacionController::class, 'hoy']);

        Route::post('/visitas/validar-qr', [MobileVisitaSupervisorController::class, 'validarQr']);
        Route::post('/visitas', [MobileVisitaSupervisorController::class, 'store']);
    });
});
