<?php

use App\Http\Controllers\ThemeController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('themes')->name('themes.')->controller(ThemeController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/create', 'create')->name('create');
    Route::post('/', 'store')->name('store');
    Route::get('/{theme}', 'show')->name('show');
    Route::get('/{theme}/edit', 'edit')->name('edit');
    Route::put('/{theme}', 'update')->name('update');
    Route::delete('/{theme}', 'destroy')->name('destroy');

    Route::get('/{theme}/customize', 'customize')->name('customize');
    Route::post('/{theme}/customize', 'storeCustomization')->name('customize.store');
    Route::put('/{theme}/customize', 'updateCustomization')->name('customize.update');
    Route::post('/{theme}/preview', 'preview')->middleware('throttle:previews')->name('preview');
    Route::get('/{theme}/preview/{side}.svg', 'previewSvg')->name('preview.svg');

    Route::post('/{theme}/duplicate', 'duplicate')->name('duplicate');
    Route::post('/{theme}/reset', 'reset')->name('reset');
    Route::post('/{theme}/favorite', 'favorite')->name('favorite');
    Route::post('/{theme}/publish', 'publish')->name('publish');
    Route::post('/{theme}/unpublish', 'unpublish')->name('unpublish');
});
