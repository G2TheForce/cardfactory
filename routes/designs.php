<?php

use App\Http\Controllers\Designs\AssetController;
use App\Http\Controllers\Designs\DesignController;
use App\Http\Controllers\Designs\ExportController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::prefix('designs')->name('designs.')->controller(DesignController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->name('store');
        Route::get('/{design}/edit', 'edit')->name('edit');
        Route::put('/{design}', 'update')->middleware('throttle:saves')->name('update');
        Route::post('/{design}/render', 'render')->middleware('throttle:previews')->name('render');
        Route::post('/{design}/duplicate', 'duplicate')->name('duplicate');
        Route::delete('/{design}', 'destroy')->name('destroy');
    });

    Route::get('/designs/{design}/export', [ExportController::class, 'create'])->name('designs.export');
    Route::post('/designs/{design}/export', [ExportController::class, 'store'])->middleware('throttle:saves')->name('designs.export.store');
    Route::prefix('exports')->name('exports.')->controller(ExportController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/status', 'status')->middleware('throttle:previews')->name('status');
        Route::get('/{export}/download', 'download')->name('download');
        Route::delete('/{export}', 'destroy')->name('destroy');
    });

    Route::get('/editor/icons', [DesignController::class, 'icons'])->middleware('throttle:previews')->name('editor.icons');

    Route::prefix('assets')->name('assets.')->controller(AssetController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::post('/', 'store')->middleware('throttle:uploads')->name('store');
        Route::post('/fonts', 'storeFont')->middleware('throttle:uploads')->name('fonts.store');
        Route::delete('/{asset}', 'destroy')->name('destroy');
    });
});
