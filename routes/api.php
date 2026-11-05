<?php

use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DigitalProfileController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\ThemeController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('/me', MeController::class)->middleware('abilities:read')->name('me');
    Route::get('/dashboard', DashboardController::class)->middleware('abilities:read')->name('dashboard');

    Route::get('/themes', [ThemeController::class, 'index'])->middleware('abilities:read')->name('themes.index');
    Route::get('/themes/{theme}', [ThemeController::class, 'show'])->middleware('abilities:read')->name('themes.show');
    Route::get('/customers', [CustomerController::class, 'index'])->middleware('abilities:read')->name('customers.index');
    Route::get('/customers/{customer}', [CustomerController::class, 'show'])->middleware('abilities:read')->name('customers.show');
    Route::post('/customers', [CustomerController::class, 'store'])->middleware('abilities:write')->name('customers.store');
    Route::match(['put', 'patch'], '/customers/{customer}', [CustomerController::class, 'update'])->middleware('abilities:write')->name('customers.update');
    Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->middleware('abilities:write')->name('customers.destroy');

    Route::get('/profiles', [DigitalProfileController::class, 'index'])->middleware('abilities:read')->name('profiles.index');
    Route::get('/profiles/{profile}', [DigitalProfileController::class, 'show'])->middleware('abilities:read')->name('profiles.show');
    Route::get('/profiles/{profile}/qr.{format}', [DigitalProfileController::class, 'qr'])->whereIn('format', ['png', 'svg', 'pdf'])->middleware('abilities:read')->name('profiles.qr');
    Route::post('/profiles', [DigitalProfileController::class, 'store'])->middleware('abilities:write')->name('profiles.store');
    Route::match(['put', 'patch'], '/profiles/{profile}', [DigitalProfileController::class, 'update'])->middleware('abilities:write')->name('profiles.update');
    Route::delete('/profiles/{profile}', [DigitalProfileController::class, 'destroy'])->middleware('abilities:write')->name('profiles.destroy');

    Route::post('/themes/{theme}/favorite', [ThemeController::class, 'favorite'])->middleware('abilities:write')->name('themes.favorite');
});
