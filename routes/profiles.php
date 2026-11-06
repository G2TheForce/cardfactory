<?php

use App\Http\Controllers\Profiles\NfcController;
use App\Http\Controllers\Profiles\ProfileController;
use App\Http\Controllers\Profiles\PublicProfileController;
use App\Http\Controllers\Profiles\QrController;
use Illuminate\Support\Facades\Route;

// Public digital business cards ------------------------------------------------
Route::controller(PublicProfileController::class)->middleware('throttle:public')->group(function () {
    Route::get('/card/{slug}', 'show')->where('slug', '[A-Za-z0-9-]{3,60}')->name('profiles.public');
    Route::get('/card/{slug}/vcard', 'vcard')->where('slug', '[A-Za-z0-9-]{3,60}')->name('profiles.vcard');
    Route::get('/q/{code}', 'qr')->where('code', '[A-Za-z0-9]{4,32}')->name('qr.scan');
    Route::get('/c/{identifier}', 'nfcLink')->where('identifier', '[A-Za-z0-9-]{4,16}')->name('profiles.nfc-link');
    Route::get('/n/{identifier}', 'nfcTap')->where('identifier', '[A-Za-z0-9-]{4,32}')->name('nfc.tap');
});
// Click beacons (no CSRF token: see bootstrap/app.php).
Route::post('/card/{slug}/track', [PublicProfileController::class, 'track'])
    ->where('slug', '[A-Za-z0-9-]{3,60}')->middleware('throttle:beacons')->name('profiles.track');

// Management ---------------------------------------------------------------------
Route::middleware('auth')->group(function () {
    Route::get('/my-profile', [ProfileController::class, 'mine'])->name('my-profile');

    Route::prefix('profiles')->name('profiles.')->group(function () {
        Route::controller(ProfileController::class)->group(function () {
            Route::get('/', 'index')->name('index');
            Route::get('/create', 'create')->name('create');
            Route::post('/', 'store')->middleware('throttle:saves')->name('store');
            Route::get('/{profile}', 'show')->name('show');
            Route::get('/{profile}/edit', 'edit')->name('edit');
            Route::put('/{profile}', 'update')->middleware('throttle:uploads')->name('update');
            Route::delete('/{profile}', 'destroy')->name('destroy');
            Route::get('/{profile}/preview', 'preview')->name('preview');
            Route::post('/{profile}/site', 'exportSite')->middleware('throttle:saves')->name('site.export');
            Route::post('/{profile}/social/refresh', 'refreshSocial')->middleware('throttle:social-refresh')->name('social.refresh');
        });

        Route::put('/{profile}/qr', [QrController::class, 'update'])->middleware('throttle:saves')->name('qr.update');
        Route::get('/{profile}/qr/{format}', [QrController::class, 'download'])->whereIn('format', ['png', 'svg', 'pdf'])->middleware('throttle:previews')->name('qr.download');

        Route::controller(NfcController::class)->middleware('throttle:saves')->group(function () {
            Route::post('/{profile}/nfc', 'store')->name('nfc.store');
            Route::post('/{profile}/nfc/{card}/encoded', 'encoded')->name('nfc.encoded');
            Route::post('/{profile}/nfc/{card}/replace', 'replace')->name('nfc.replace');
            Route::delete('/{profile}/nfc/{card}', 'destroy')->name('nfc.destroy');
        });
    });
});
