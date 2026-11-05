<?php

use App\Http\Controllers\Brands\BrandKitController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('brand-kits')->name('brand-kits.')->controller(BrandKitController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/create', 'create')->name('create');
    Route::post('/', 'store')->middleware('throttle:uploads')->name('store');
    Route::get('/{brandKit}', 'show')->name('show');
    Route::get('/{brandKit}/edit', 'edit')->name('edit');
    Route::put('/{brandKit}', 'update')->middleware('throttle:uploads')->name('update');
    Route::delete('/{brandKit}', 'destroy')->name('destroy');
    Route::post('/{brandKit}/apply', 'apply')->middleware('throttle:saves')->name('apply');
    Route::post('/{brandKit}/designs', 'storeDesign')->middleware('throttle:saves')->name('designs.store');
});
