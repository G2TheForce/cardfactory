<?php

use App\Http\Controllers\Imports\BatchController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('bulk')->name('imports.')->controller(BatchController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/new', 'create')->name('create');
    Route::get('/template.{format}', 'template')->whereIn('format', ['csv', 'xlsx'])->name('template');
    Route::post('/', 'store')->middleware('throttle:uploads')->name('store');
    Route::get('/{import}', 'show')->name('show');
    Route::post('/{import}/check', 'check')->middleware('throttle:saves')->name('check');
    Route::post('/{import}/generate', 'generate')->middleware('throttle:saves')->name('generate');
    Route::get('/{import}/status', 'status')->middleware('throttle:previews')->name('status');
    Route::get('/{import}/errors.csv', 'errors')->name('errors');
    Route::get('/{import}/cards.csv', 'info')->name('info');
    Route::post('/{import}/export', 'export')->middleware('throttle:saves')->name('export');
    Route::delete('/{import}', 'destroy')->name('destroy');
});
