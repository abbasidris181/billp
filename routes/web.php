<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardAdminController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InstantIpeController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\UserManagementController;
use App\Http\Middleware\EnsureAccountIsActive;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->middleware('guest')->name('home');

Route::middleware('guest')->group(function () {
    Route::view('/login', 'welcome')->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.store');
    Route::view('/forgot-password', 'auth.forgot-password')->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'send'])
        ->middleware('throttle:6,1,password-request:')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])
        ->middleware('cache.headers:private;no_store')->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])
        ->middleware('throttle:6,1,password-reset:')->name('password.update');
    Route::view('/register', 'auth.register')->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1')->name('register.store');
});

Route::middleware(['auth', EnsureAccountIsActive::class])->group(function () {
    Route::get('/email/verify', [AuthController::class, 'verificationNotice'])
        ->middleware('cache.headers:private;no_store')->name('verification.notice');
    Route::post('/email/verification-notification', [AuthController::class, 'resendVerification'])
        ->middleware('throttle:6,1,email-verification:')->name('verification.send');
    Route::get('/email/verify/{id}/{hash}', function (EmailVerificationRequest $request): RedirectResponse {
        $request->fulfill();

        return to_route('account')->with('notice', 'Email address verified.');
    })->middleware(['signed', 'throttle:6,1'])->name('verification.verify');

    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});

Route::middleware(['auth', EnsureAccountIsActive::class, 'verified'])->group(function () {
    Route::get('/account', [DashboardController::class, 'index'])->name('account');
    Route::get('/requests', [DashboardController::class, 'requests'])->name('portal.requests');
    Route::get('/requests/export', [DashboardController::class, 'export'])->name('portal.export');
    Route::get('/requests/{id}', [DashboardController::class, 'result'])->whereNumber('id')->name('portal.result');
    Route::get('/billing', [DashboardController::class, 'billing'])->name('portal.billing');
    Route::get('/pricing', [DashboardController::class, 'pricing'])->name('portal.pricing');
    Route::get('/api-docs', [DashboardController::class, 'documentation'])->name('portal.api');
    Route::post('/api-docs/key', [DashboardController::class, 'rotateKey'])->middleware('throttle:10,1')->name('portal.key');
    Route::get('/pages/docs-{version}/{service}', [DashboardController::class, 'serviceDocumentation'])
        ->whereIn('version', ['v1', 'v2', 'v3'])->name('portal.docs.service');
    Route::get('/pages/{slug}', [DashboardController::class, 'page'])->where('slug', '(?:docs|services|policies)-[a-z0-9-]+')->name('portal.page');
    Route::prefix('instant-ipe')->name('instant-ipe.')->middleware('cache.headers:private;no_store')->group(function () {
        Route::get('/', [InstantIpeController::class, 'index'])->name('index');
        Route::get('/history', [InstantIpeController::class, 'history'])->name('history');
        Route::post('/', [InstantIpeController::class, 'store'])->name('store');
        Route::post('/{id}/check', [InstantIpeController::class, 'check'])->whereNumber('id')
            ->middleware('throttle:20,1,instant-ipe-check:')->name('check');
        Route::post('/batches/{batch}/check', [InstantIpeController::class, 'checkBatch'])->where('batch', '[0-9]{8}')
            ->middleware('throttle:20,1,instant-ipe-check:')->name('batch.check');
        Route::get('/batches/{batch}', [InstantIpeController::class, 'batch'])->where('batch', '[0-9]{8}')->name('batch');
    });
    Route::get('/live-access', [DashboardController::class, 'liveAccess'])->name('portal.access');
    Route::post('/live-access', [DashboardController::class, 'apply'])->middleware('throttle:10,1')->name('portal.apply');

    Route::prefix('admin')->name('admin.')->middleware('can:manage-portal')->group(function () {
        Route::get('/', [DashboardAdminController::class, 'index'])->name('index');
        Route::get('/records/{section}', [DashboardAdminController::class, 'records'])->whereIn('section', ['requests', 'applications'])->name('records');
        Route::get('/requests/{id}', [DashboardAdminController::class, 'result'])->whereNumber('id')->name('result');
        Route::get('/applications/{id}/document', [DashboardAdminController::class, 'document'])->whereNumber('id')->name('document');
        Route::post('/applications/{id}', [DashboardAdminController::class, 'review'])->whereNumber('id')->name('review');
        Route::get('/error-charge', [DashboardAdminController::class, 'errorCharge'])->name('error-charge');
        Route::post('/error-charge', [DashboardAdminController::class, 'saveErrorCharge'])->name('error-charge.save');
        Route::get('/pricing', [DashboardAdminController::class, 'catalog'])->name('catalog');
        Route::post('/pricing', [DashboardAdminController::class, 'savePrice'])->name('price');
        Route::get('/content/{category}', [DashboardAdminController::class, 'content'])->whereIn('category', ['services', 'docs', 'policies'])->name('content');
        Route::post('/content/{category}', [DashboardAdminController::class, 'saveContent'])->whereIn('category', ['services', 'docs', 'policies'])->name('publish');
    });
    Route::middleware('can:manage-users')->prefix('admin/users')->name('admin.users.')->group(function () {
        Route::get('/', [UserManagementController::class, 'index'])->name('index');
        Route::get('/create', [UserManagementController::class, 'create'])->name('create');
        Route::post('/', [UserManagementController::class, 'store'])->name('store');
        Route::get('/{user}', [UserManagementController::class, 'edit'])->name('edit');
        Route::put('/{user}', [UserManagementController::class, 'update'])->name('update');
        Route::put('/{user}/prices', [UserManagementController::class, 'updatePrices'])->name('prices');
        Route::post('/{user}/password', [UserManagementController::class, 'password'])->name('password');
        Route::post('/{user}/verification', [UserManagementController::class, 'sendVerification'])->middleware('throttle:6,1')->name('verification');
        Route::post('/{user}/balance', [UserManagementController::class, 'adjustBalance'])->name('balance');
        Route::post('/{user}/status', [UserManagementController::class, 'status'])->name('status');
    });
});
