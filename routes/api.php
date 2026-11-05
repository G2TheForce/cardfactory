<?php

use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\MeController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('/me', MeController::class)->middleware('abilities:read')->name('me');
    Route::get('/dashboard', DashboardController::class)->middleware('abilities:read')->name('dashboard');
});
