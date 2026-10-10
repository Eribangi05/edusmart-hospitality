<?php

use App\Http\Controllers\Api\V1\AdminController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ResourceController;
use App\Support\ResourceRegistry;
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

    // Tenant administration
    Route::get('/modules', [AdminController::class, 'modules']);
    Route::put('/modules/{key}', [AdminController::class, 'setModule'])->middleware('perm:core.settings');
    Route::get('/settings', [AdminController::class, 'settings']);
    Route::put('/settings', [AdminController::class, 'putSettings'])->middleware('perm:core.settings');
    Route::get('/roles', [AdminController::class, 'roles'])->middleware('perm:core.roles');
    Route::post('/roles', [AdminController::class, 'storeRole'])->middleware('perm:core.roles');
    Route::patch('/roles/{id}', [AdminController::class, 'updateRole'])->middleware('perm:core.roles');
    Route::delete('/roles/{id}', [AdminController::class, 'destroyRole'])->middleware('perm:core.roles');
    Route::get('/users', [AdminController::class, 'users'])->middleware('perm:core.users');
    Route::post('/users', [AdminController::class, 'storeUser'])->middleware('perm:core.users');
    Route::patch('/users/{id}', [AdminController::class, 'updateUser'])->middleware('perm:core.users');
    Route::get('/audit-log', [AdminController::class, 'auditLog'])->middleware('perm:core.audit');

    // Generic registry-driven CRUD
    foreach (array_keys(ResourceRegistry::all()) as $key) {
        Route::get("/$key", [ResourceController::class, 'index'])->defaults('resource', $key);
        Route::post("/$key", [ResourceController::class, 'store'])->defaults('resource', $key);
        Route::get("/$key/{id}", [ResourceController::class, 'show'])->defaults('resource', $key);
        Route::patch("/$key/{id}", [ResourceController::class, 'update'])->defaults('resource', $key);
        Route::delete("/$key/{id}", [ResourceController::class, 'destroy'])->defaults('resource', $key);
    }
});
