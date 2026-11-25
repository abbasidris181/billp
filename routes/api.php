<?php

use App\Http\Controllers\Api\V1\BvnController;
use App\Http\Controllers\Api\V1\IpeClearingController;
use App\Http\Controllers\Api\V1\NinController;
use App\Http\Controllers\Api\V1\PersonalizationController;
use App\Http\Controllers\Api\V1\PhoneController;
use App\Http\Controllers\Api\V2\VerificationController;
use App\Http\Middleware\AuthenticateApiKey;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->middleware([AuthenticateApiKey::class, 'throttle:60,1'])->group(function () {
    Route::post('/nin', NinController::class)->defaults('verification_type', 'nin')->name('nin');
    Route::post('/phone', PhoneController::class)->defaults('verification_type', 'phone')->name('phone');
    Route::post('/bvn', BvnController::class)->defaults('verification_type', 'bvn')->name('bvn');
});

Route::prefix('v2')->name('api.v2.')->middleware([AuthenticateApiKey::class, 'throttle:60,1'])->group(function () {
    Route::post('/nin', VerificationController::class)->defaults('verification_type', 'nin')->defaults('verification_version', 'v2')->name('nin');
    Route::post('/phone', VerificationController::class)->defaults('verification_type', 'phone')->defaults('verification_version', 'v2')->name('phone');
});

Route::prefix('clearance')->name('api.clearance.')->middleware([AuthenticateApiKey::class.':body', 'throttle:60,1'])->group(function () {
    Route::post('/', [IpeClearingController::class, 'submit'])->name('submit');
    Route::post('/status', [IpeClearingController::class, 'status'])->name('status');
});

Route::prefix('personalization')->name('api.personalization.')->middleware([AuthenticateApiKey::class.':body', 'throttle:60,1'])->group(function () {
    Route::post('/', [PersonalizationController::class, 'submit'])->name('submit');
    Route::post('/check', [PersonalizationController::class, 'check'])->name('check');
});
