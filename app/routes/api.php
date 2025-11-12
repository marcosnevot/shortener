<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\LinkController;
use App\Http\Controllers\Api\StatsController;

Route::get('/ping', fn () => response()->json(['pong' => true]));

// Grupo protegido por token
Route::middleware(['api_token'])->group(function () {
    // Crear (con rate-limit)
    Route::post('/v1/links', [LinkController::class, 'store'])->middleware('throttle:create-links');

    // CRUD básico
    Route::get('/v1/links/{id}',    [LinkController::class, 'show']);
    Route::delete('/v1/links/{id}', [LinkController::class, 'destroy']);
    Route::post('/v1/links/{id}/ban', [LinkController::class, 'ban']);

    // Stats
    Route::get('/v1/links/{id}/stats', [StatsController::class, 'show']);
});
