<?php

use App\Http\Controllers\LandingPages\LandingPageController;
use App\Http\Controllers\LandingPages\PublicLandingPageController;
use Illuminate\Support\Facades\Route;

// Public landing pages ------------------------------------------------------------
Route::controller(PublicLandingPageController::class)->group(function () {
    Route::get('/p/{slug}', 'show')->where('slug', '[A-Za-z0-9-]{3,60}')->middleware('throttle:public')->name('landing-pages.public');
    Route::post('/p/{slug}/contact', 'contact')->where('slug', '[A-Za-z0-9-]{3,60}')->middleware('throttle:leads')->name('landing-pages.contact');
});

// Designer --------------------------------------------------------------------------
Route::middleware('auth')->prefix('landing-pages')->name('landing-pages.')->controller(LandingPageController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/create', 'create')->name('create');
    Route::post('/', 'store')->middleware('throttle:saves')->name('store');
    Route::get('/{landingPage}', 'show')->name('show');
    Route::get('/{landingPage}/edit', 'edit')->name('edit');
    Route::put('/{landingPage}', 'update')->middleware('throttle:saves')->name('update');
    Route::delete('/{landingPage}', 'destroy')->name('destroy');
    Route::get('/{landingPage}/preview', 'preview')->name('preview');
    Route::put('/{landingPage}/draft-preview', 'draftPreview')->middleware('throttle:previews')->name('draft-preview');
    Route::post('/{landingPage}/publish', 'publish')->middleware('throttle:saves')->name('publish');
    Route::post('/{landingPage}/images', 'uploadImage')->middleware('throttle:uploads')->name('images.store');
    Route::post('/{landingPage}/brand-kit', 'applyBrandKit')->middleware('throttle:saves')->name('brand-kit');
    Route::post('/{landingPage}/site', 'exportSite')->middleware('throttle:saves')->name('site.export');
    Route::post('/{landingPage}/leads/read', 'markLeadsRead')->middleware('throttle:saves')->name('leads.read');
    Route::delete('/{landingPage}/leads/{lead}', 'destroyLead')->scopeBindings()->name('leads.destroy');
});
