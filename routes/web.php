<?php

use App\Http\Controllers\Auth\ColaboradorLoginController;
use App\Http\Controllers\KioskoController;
use App\Http\Controllers\MarcacionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [ColaboradorLoginController::class, 'show'])->name('login');
    Route::post('/login', [ColaboradorLoginController::class, 'store']);
});

Route::post('/logout', [ColaboradorLoginController::class, 'destroy'])
    ->name('logout')
    ->middleware('auth');

Route::get('/kiosko/{sucursal}/{puntoVenta?}', [KioskoController::class, 'show'])->name('kiosko.show');
Route::get('/kiosko/{sucursal}/{puntoVenta?}/token', [KioskoController::class, 'token'])->name('kiosko.token');

Route::middleware('auth')->group(function () {
    Route::get('/marcar', [MarcacionController::class, 'show'])->name('marcacion.show');
    Route::post('/marcar', [MarcacionController::class, 'store'])->name('marcacion.store');
    Route::get('/marcar/{marcacion}/confirmacion', [MarcacionController::class, 'confirmacion'])->name('marcacion.confirmacion');
});
