<?php

use App\Http\Controllers\Analytics\AnalyticsController;
use App\Http\Controllers\Inventory\NfcCardController;
use App\Http\Controllers\Inventory\OrderController;
use App\Http\Controllers\Inventory\ProductController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::prefix('nfc')->name('nfc.')->middleware('can:nfc.manage')->controller(NfcCardController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('throttle:saves')->name('store');
        Route::get('/export.csv', 'export')->name('export');
        Route::get('/cards/{card}', 'show')->name('show');
        Route::put('/cards/{card}', 'update')->middleware('throttle:saves')->name('update');
        Route::post('/cards/{card}/assign', 'assign')->middleware('throttle:saves')->name('assign');
        Route::delete('/cards/{card}/assign', 'unassign')->middleware('throttle:saves')->name('unassign');
    });

    Route::prefix('inventory')->name('inventory.')->middleware('can:inventory.manage')->controller(ProductController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/products/new', 'create')->name('create');
        Route::post('/products', 'store')->name('store');
        Route::get('/products/{product}/edit', 'edit')->name('edit');
        Route::put('/products/{product}', 'update')->name('update');
        Route::delete('/products/{product}', 'destroy')->name('destroy');
    });

    Route::prefix('orders')->name('orders.')->middleware('can:orders.manage')->controller(OrderController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/new', 'create')->name('create');
        Route::post('/', 'store')->middleware('throttle:saves')->name('store');
        Route::get('/{order}', 'show')->name('show');
        Route::get('/{order}/edit', 'edit')->name('edit');
        Route::put('/{order}', 'update')->middleware('throttle:saves')->name('update');
        Route::patch('/{order}/status', 'status')->name('status');
        Route::post('/{order}/cards', 'attachCards')->middleware('throttle:saves')->name('cards.attach');
        Route::delete('/{order}/cards/{card}', 'detachCard')->name('cards.detach');
        Route::delete('/{order}', 'destroy')->name('destroy');
    });

    Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics.index');
    Route::get('/analytics/export.csv', [AnalyticsController::class, 'export'])->name('analytics.export');
});
