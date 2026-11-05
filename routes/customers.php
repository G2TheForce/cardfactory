<?php

use App\Http\Controllers\CustomerController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {
    Route::get('/my-details', [CustomerController::class, 'myDetails'])->name('my-details');

    Route::prefix('customers')->name('customers.')->controller(CustomerController::class)->group(function () {
        Route::get('/', 'index')->name('index');
        Route::get('/create', 'create')->name('create');
        Route::post('/', 'store')->middleware('throttle:uploads')->name('store');
        Route::get('/{customer}', 'show')->name('show');
        Route::get('/{customer}/edit', 'edit')->name('edit');
        Route::put('/{customer}', 'update')->middleware('throttle:uploads')->name('update');
        Route::delete('/{customer}', 'destroy')->name('destroy');
        Route::post('/{customer}/restore', 'restore')->name('restore');
        Route::post('/{customer}/login', 'createLogin')->name('login');
    });
});
