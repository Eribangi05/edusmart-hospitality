<?php

use App\Http\Controllers\Api\V1\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['status' => 'ok', 'time' => now()->toIso8601String()]));

Route::middleware('throttle:10,1')->group(function () {
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/2fa', [AuthController::class, 'twoFactor']);
    Route::post('/auth/refresh', [AuthController::class, 'refresh']);
});

Route::middleware(['auth:sanctum', 'tenant', 'throttle:240,1'])->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/2fa/setup', [AuthController::class, 'twoFactorSetup']);
    Route::post('/auth/2fa/confirm', [AuthController::class, 'twoFactorConfirm']);
    Route::get('/me/context', [AuthController::class, 'context']);

    Route::get('/devices', [AuthController::class, 'devices'])->middleware('perm:core.users');
    Route::post('/devices/{id}/revoke', [AuthController::class, 'revoke'])->middleware('perm:core.users');
});
