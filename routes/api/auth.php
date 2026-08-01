<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CustomerAuthController;
use App\Http\Controllers\CustomerSecurityController;
use Illuminate\Support\Facades\Route;

Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::post('refresh', [AuthController::class, 'refresh'])->middleware('throttle:60,1');
Route::prefix('auth/customer')->group(function () {
    Route::post('forgot-password', [CustomerSecurityController::class, 'forgotPassword'])->middleware('throttle:5,1');
    Route::post('reset-password', [CustomerSecurityController::class, 'resetPassword'])->middleware('throttle:5,1');
    Route::post('verify-email', [CustomerSecurityController::class, 'verifyEmailChange'])->middleware('throttle:10,1');
    Route::post('register', [CustomerAuthController::class, 'register'])->middleware('throttle:10,1');
    Route::post('login', [CustomerAuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('two-factor/verify', [CustomerAuthController::class, 'verifyTwoFactor'])->middleware('throttle:10,1');
    Route::post('refresh', [CustomerAuthController::class, 'refresh'])->middleware('throttle:60,1');
    Route::middleware('auth.customer')->group(function () {
        Route::get('me', [CustomerAuthController::class, 'me']);
        Route::post('logout', [CustomerAuthController::class, 'logout']);
    });
});
