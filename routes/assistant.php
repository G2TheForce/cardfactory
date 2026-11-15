<?php

use App\Http\Controllers\AssistantController;
use Illuminate\Support\Facades\Route;

// AI assistant: chat panel on every app page plus a full-page view ------------
Route::middleware(['auth', 'permission:assistant.use'])->prefix('assistant')->name('assistant.')->controller(AssistantController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::post('/messages', 'store')->middleware('throttle:assistant')->name('messages.store');
    Route::get('/conversations/{conversation}', 'show')->name('conversations.show');
    Route::delete('/conversations/{conversation}', 'destroy')->name('conversations.destroy');
});
