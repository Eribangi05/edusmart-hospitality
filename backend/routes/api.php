<?php

use App\Http\Controllers\Api\V1\AdminController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BillingController;
use App\Http\Controllers\Api\V1\OperationsController;
use App\Http\Controllers\Api\V1\PlatformController;
use App\Http\Controllers\Api\V1\PosController;
use App\Http\Controllers\Api\V1\PrivacyController;
use App\Http\Controllers\Api\V1\ReportsController;
use App\Http\Controllers\Api\V1\ResourceController;
use App\Http\Controllers\Api\V1\RoomsController;
use App\Http\Controllers\Api\V1\SyncController;
use App\Http\Controllers\Api\V1\TourismController;
use App\Http\Controllers\Api\V1\WebhookController;
use App\Support\ResourceRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => response()->json(['status' => 'ok', 'time' => now()->toIso8601String()]));

Route::middleware('throttle:10,1')->group(function () {
    Route::post('/auth/register', [AuthController::class, 'register']);
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/2fa', [AuthController::class, 'twoFactor']);
    Route::post('/auth/refresh', [AuthController::class, 'refresh']);
    Route::post('/auth/forgot', [AuthController::class, 'forgot']);
    Route::post('/auth/reset', [AuthController::class, 'reset']);
});

Route::post('/webhooks/momo/{token}', [WebhookController::class, 'momo'])->middleware('throttle:120,1');

// ---- Platform super-admin console ----
Route::middleware(['auth:sanctum', 'platform:basic'])->prefix('admin')->group(function () {
    Route::post('/2fa/setup', [AuthController::class, 'twoFactorSetup']);
    Route::post('/2fa/confirm', [AuthController::class, 'twoFactorConfirm']);
    Route::post('/logout', fn (Request $r) => tap(response()->noContent(), fn () => $r->user()->currentAccessToken()->delete()));
});

Route::middleware(['auth:sanctum', 'platform', 'throttle:600,1'])->prefix('admin')->group(function () {
    Route::get('/metrics', [PlatformController::class, 'metrics']);
    Route::get('/tenants', [PlatformController::class, 'tenants']);
    Route::get('/tenants/{id}', [PlatformController::class, 'tenant']);
    Route::post('/tenants/{id}/status', [PlatformController::class, 'setStatus']);
    Route::post('/tenants/{id}/plan', [PlatformController::class, 'setPlan']);
    Route::post('/tenants/{id}/impersonate', [PlatformController::class, 'impersonate']);
    Route::post('/invoices/{id}/mark-paid', [PlatformController::class, 'markInvoicePaid']);
    Route::get('/plans', [PlatformController::class, 'plans']);
    Route::post('/plans', [PlatformController::class, 'savePlan']);
    Route::patch('/plans/{id}', [PlatformController::class, 'savePlan']);
    Route::get('/audit', [PlatformController::class, 'audit']);
    Route::delete('/tenants/{id}', [PlatformController::class, 'erase']);
});

Route::middleware(['auth:sanctum', 'tenant', 'throttle:1200,1'])->group(function () {
    Route::get('/sync/pull', [SyncController::class, 'pull']);
    Route::post('/sync/push', [SyncController::class, 'push']);

    // ---- Privacy: data portability and erasure request (owner) ----
    Route::get('/data-export', [PrivacyController::class, 'export'])->middleware('perm:core.settings');
    Route::post('/tenant/deletion-request', [PrivacyController::class, 'requestDeletion'])->middleware('perm:core.settings');

    // ---- Subscription billing (owner) ----
    Route::get('/billing', [BillingController::class, 'show'])->middleware('perm:core.settings');
    Route::post('/billing/plan', [BillingController::class, 'choosePlan'])->middleware('perm:core.settings');
    Route::post('/billing/invoices/{id}/pay', [BillingController::class, 'pay'])->middleware('perm:core.settings');
    Route::get('/billing/invoices/{id}', [BillingController::class, 'invoice'])->middleware('perm:core.settings');

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

    // ---- Tourism: tours, activities, transfers, rentals, packages ----
    Route::middleware('module:tourism')->group(function () {
        Route::post('/tourism/quote', [TourismController::class, 'quote'])->middleware('perm:tourism.view');
        Route::get('/tourism/departures', [TourismController::class, 'departures'])->middleware('perm:tourism.view');
        Route::get('/tourism/departures/{id}/manifest', [TourismController::class, 'manifest'])->middleware('perm:tourism.view');
        Route::get('/tourism/today', [TourismController::class, 'bookingsToday'])->middleware('perm:tourism.view');
        Route::get('/bookings', [TourismController::class, 'index'])->middleware('perm:tourism.view');
        Route::get('/bookings/{id}', [TourismController::class, 'show'])->middleware('perm:tourism.view');
        Route::post('/bookings', [TourismController::class, 'store'])->middleware('perm:tourism.create');
        Route::patch('/bookings/{id}', [TourismController::class, 'update'])->middleware('perm:tourism.edit');
        Route::post('/bookings/{id}/status/{to}', [TourismController::class, 'transition'])->whereIn('to', ['confirmed', 'in_progress', 'completed', 'cancelled', 'no_show'])->middleware('perm:tourism.edit');
        Route::post('/bookings/{id}/assign', [TourismController::class, 'assign'])->middleware('perm:tourism.assign');
        Route::delete('/bookings/{id}/assign/{resourceId}', [TourismController::class, 'unassign'])->middleware('perm:tourism.assign');
        Route::post('/bookings/{id}/payments', [TourismController::class, 'pay'])->middleware('perm:tourism.create');
    });

    // ---- Inventory ----
    Route::middleware('module:inventory')->group(function () {
        Route::get('/stock', [OperationsController::class, 'stock'])->middleware('perm:inventory.view');
        Route::get('/stock-movements', [OperationsController::class, 'movements'])->middleware('perm:inventory.view');
        Route::post('/stock/adjust', [OperationsController::class, 'adjust'])->middleware('perm:inventory.adjust');
        Route::post('/stock/stocktake', [OperationsController::class, 'stocktake'])->middleware('perm:inventory.stocktake');
        Route::get('/purchase-orders', [OperationsController::class, 'purchaseOrders'])->middleware('perm:inventory.view');
        Route::post('/purchase-orders', [OperationsController::class, 'storePurchaseOrder'])->middleware('perm:inventory.create');
        Route::post('/purchase-orders/{id}/receive', [OperationsController::class, 'receive'])->middleware('perm:inventory.receive');
    });

    // ---- Staff ----
    Route::middleware('module:staff')->group(function () {
        Route::post('/attendance', [OperationsController::class, 'clock']); // any employee may clock themselves
        Route::post('/my/leave-requests', [OperationsController::class, 'requestLeave']);
        Route::get('/attendance', [OperationsController::class, 'attendance'])->middleware('perm:staff.attendance_manage');
        Route::post('/leave-requests/{id}/decide', [OperationsController::class, 'decideLeave'])->middleware('perm:staff.roster');
        Route::get('/payroll', [OperationsController::class, 'payroll'])->middleware('perm:staff.payroll_export');
    });

    // ---- Housekeeping & maintenance ----
    Route::middleware('module:housekeeping')->group(function () {
        Route::get('/my-tasks', [OperationsController::class, 'myTasks'])->middleware('perm:housekeeping.view');
        Route::post('/tasks/{id}/{action}', [OperationsController::class, 'taskAction'])->whereIn('action', ['start', 'complete', 'inspect', 'assign'])->middleware('perm:housekeeping.edit,housekeeping.inspect,housekeeping.assign');
    });

    // ---- Reports & finance ----
    Route::get('/reports/dashboard', [ReportsController::class, 'dashboard'])->middleware('perm:finance.view,rooms.view,pos.view,tourism.view');
    Route::middleware(['module:finance', 'perm:finance.view'])->group(function () {
        Route::get('/reports/sales', [ReportsController::class, 'sales']);
        Route::get('/reports/occupancy', [ReportsController::class, 'occupancy']);
        Route::get('/reports/cash', [ReportsController::class, 'cash']);
        Route::get('/reports/profit', [ReportsController::class, 'profit']);
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
