<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Web\RedirectController;
use App\Http\Controllers\Web\PanelController;
use App\Http\Controllers\Web\MetricsController;

Route::get('/health', fn() => response()->json(['ok'=>true]));

Route::get('/',            [PanelController::class, 'index'])->name('panel.index');
Route::get('/links/{id}',  [PanelController::class, 'show'])->name('panel.show');
Route::post('/links',      [PanelController::class, 'store'])->name('panel.store');
Route::post('/links/{id}/ban', [PanelController::class, 'ban'])->name('panel.ban');
Route::delete('/links/{id}',   [PanelController::class, 'destroy'])->name('panel.destroy');

Route::get('/r/{slug}', RedirectController::class)->middleware('throttle:resolve');

Route::get('/metrics', MetricsController::class);