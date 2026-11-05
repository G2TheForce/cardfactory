<?php

use App\Http\Controllers\Admin\ActivityLogController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MarketingController;
use App\Http\Controllers\Settings\ApiTokenController;
use App\Http\Controllers\Settings\ProfileController;
use Illuminate\Support\Facades\Route;

// Public marketing site ------------------------------------------------------
Route::get('/', [MarketingController::class, 'home'])->name('home');
Route::get('/legal/{page}', [MarketingController::class, 'legal'])->name('legal');
Route::post('/contact', [MarketingController::class, 'contact'])->middleware('throttle:5,10')->name('contact.store');

// Authenticated application -----------------------------------------------
Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile');
        Route::get('/api-tokens', [ApiTokenController::class, 'index'])->name('tokens');
        Route::post('/api-tokens', [ApiTokenController::class, 'store'])->middleware('throttle:10,1')->name('tokens.store');
        Route::delete('/api-tokens/{tokenId}', [ApiTokenController::class, 'destroy'])->whereNumber('tokenId')->name('tokens.destroy');
    });

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::resource('users', UserController::class)->except('show');

        Route::middleware('permission:roles.manage')->group(function () {
            Route::get('roles', [RoleController::class, 'index'])->name('roles.index');
            Route::get('roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
            Route::put('roles/{role}', [RoleController::class, 'update'])->name('roles.update');
        });

        Route::get('activity', [ActivityLogController::class, 'index'])
            ->middleware('permission:activity.view')->name('activity.index');
    });
});

require __DIR__.'/themes.php';
require __DIR__.'/designs.php';
require __DIR__.'/customers.php';
