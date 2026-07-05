<?php

use Dominservice\FingerprintTracking\Http\Controllers\TrackingController;
use Illuminate\Support\Facades\Route;

Route::group([
    'prefix' => trim(config('fingerprint-tracking.routes.prefix', 'fingerprint-tracking'), '/'),
    'middleware' => config('fingerprint-tracking.routes.middleware', ['web']),
], function () {
    Route::post('/events', [TrackingController::class, 'store'])->name('fingerprint-tracking.events.store');
});
