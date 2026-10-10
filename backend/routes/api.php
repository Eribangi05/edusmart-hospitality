<?php

use App\Http\Controllers\Api\V1\AdminController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\PosController;
use App\Http\Controllers\Api\V1\ResourceController;
use App\Http\Controllers\Api\V1\RoomsController;
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

    // ---- Rooms ----
    Route::middleware('module:rooms')->group(function () {
        Route::get('/availability', [RoomsController::class, 'availability'])->middleware('perm:rooms.view');
        Route::get('/reservations', [RoomsController::class, 'index'])->middleware('perm:rooms.view');
        Route::get('/reservations/{id}', [RoomsController::class, 'show'])->middleware('perm:rooms.view');
        Route::post('/reservations', [RoomsController::class, 'store'])->middleware('perm:rooms.create');
        Route::patch('/reservations/{id}', [RoomsController::class, 'update'])->middleware('perm:rooms.edit');
        Route::post('/reservations/{id}/assign', [RoomsController::class, 'assign'])->middleware('perm:rooms.edit');
        Route::post('/reservations/{id}/check-in', [RoomsController::class, 'checkIn'])->middleware('perm:rooms.check_in');
        Route::post('/reservations/{id}/check-out', [RoomsController::class, 'checkOut'])->middleware('perm:rooms.check_out');
        Route::post('/reservations/{id}/cancel', [RoomsController::class, 'cancel'])->middleware('perm:rooms.edit');
        Route::post('/reservations/{id}/no-show', [RoomsController::class, 'noShow'])->middleware('perm:rooms.edit');
        Route::get('/room-board', [RoomsController::class, 'board'])->middleware('perm:rooms.view,housekeeping.view');
        Route::post('/rooms/{id}/status', [RoomsController::class, 'setRoomStatus'])->middleware('perm:rooms.edit,housekeeping.edit');
        Route::post('/night-audit', [RoomsController::class, 'nightAudit'])->middleware('perm:rooms.folio,finance.reconcile');
        Route::get('/folios', [RoomsController::class, 'folios'])->middleware('perm:rooms.folio,rooms.view');
        Route::get('/folios/{id}', [RoomsController::class, 'folio'])->middleware('perm:rooms.folio,rooms.view');
        Route::post('/folios/{id}/entries', [RoomsController::class, 'postEntry'])->middleware('perm:rooms.folio');
        Route::post('/folios/{id}/payments', [RoomsController::class, 'payFolio'])->middleware('perm:rooms.folio');
        Route::post('/folio-entries/{id}/void', [RoomsController::class, 'voidEntry'])->middleware('perm:rooms.folio');
    });

    // ---- POS (restaurant / bar / shop counter) ----
    Route::middleware('module:pos')->group(function () {
        Route::get('/orders', [PosController::class, 'index'])->middleware('perm:pos.view');
        Route::get('/orders/{id}', [PosController::class, 'show'])->middleware('perm:pos.view');
        Route::post('/orders', [PosController::class, 'store'])->middleware('perm:pos.create');
        Route::post('/orders/{id}/items', [PosController::class, 'addItems'])->middleware('perm:pos.edit');
        Route::post('/orders/{id}/send', [PosController::class, 'send'])->middleware('perm:pos.edit');
        Route::post('/orders/{id}/void', [PosController::class, 'voidOrder'])->middleware('perm:pos.edit');
        Route::post('/orders/{id}/discount', [PosController::class, 'discount'])->middleware('perm:pos.edit');
        Route::post('/orders/{id}/tip', [PosController::class, 'tip'])->middleware('perm:pos.edit');
        Route::post('/orders/{id}/split', [PosController::class, 'split'])->middleware('perm:pos.edit');
        Route::post('/orders/{id}/merge', [PosController::class, 'merge'])->middleware('perm:pos.edit');
        Route::post('/orders/{id}/pay', [PosController::class, 'pay'])->middleware('perm:pos.create');
        Route::post('/orders/{id}/charge-room', [PosController::class, 'chargeRoom'])->middleware('perm:pos.create');
        Route::get('/payments/{id}', [PosController::class, 'payment'])->middleware('perm:pos.view,rooms.folio');
        Route::post('/order-items/{id}/void', [PosController::class, 'voidItem'])->middleware('perm:pos.edit');
        Route::post('/order-items/{id}/status', [PosController::class, 'itemStatus'])->middleware('perm:pos.view');
        Route::get('/kitchen/tickets', [PosController::class, 'tickets'])->middleware('perm:pos.view');
        Route::get('/shifts/current', [PosController::class, 'currentShift'])->middleware('perm:pos.view');
        Route::post('/shifts', [PosController::class, 'openShift'])->middleware('perm:pos.create');
        Route::post('/shifts/{id}/close', [PosController::class, 'closeShift'])->middleware('perm:pos.create');
    });

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
