<?php

use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\ThemeController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('/me', MeController::class)->middleware('abilities:read')->name('me');
    Route::get('/dashboard', DashboardController::class)->middleware('abilities:read')->name('dashboard');

    Route::get('/themes', [ThemeController::class, 'index'])->middleware('abilities:read')->name('themes.index');
    Route::get('/themes/{theme}', [ThemeController::class, 'show'])->middleware('abilities:read')->name('themes.show');
    Route::post('/themes/{theme}/favorite', [ThemeController::class, 'favorite'])->middleware('abilities:write')->name('themes.favorite');
});
