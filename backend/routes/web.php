<?php

use App\Http\Controllers\WebAuthController;
use App\Support\Modules;
use Illuminate\Support\Facades\Route;

Route::view('/', 'landing')->name('home');
Route::view('/login', 'auth.login')->name('login');
Route::get('/register', fn () => view('auth.register', ['modules' => Modules::switchable()]))->name('register');
Route::view('/forgot-password', 'auth.forgot')->name('password.request');
Route::get('/reset-password/{token}', fn (string $token) => view('auth.reset', ['token' => $token, 'email' => request('email')]))->name('password.reset');
Route::view('/privacy', 'legal.privacy');
Route::view('/terms', 'legal.terms');

Route::post('/login', [WebAuthController::class, 'login'])->middleware('throttle:10,1');
Route::post('/register', [WebAuthController::class, 'register'])->middleware('throttle:5,1');
Route::post('/forgot-password', [WebAuthController::class, 'forgot'])->middleware('throttle:5,1');
Route::post('/reset-password', [WebAuthController::class, 'reset'])->middleware('throttle:10,1');
Route::post('/logout', [WebAuthController::class, 'logout'])->middleware('auth:web');

Route::middleware('auth:web')->group(function () {
    Route::get('/app/{any?}', function () {
        abort_if(auth()->user()->is_platform_admin || ! auth()->user()->tenant_id, 403);

        return view('console');
    })->where('any', '.*');
    Route::get('/admin/{any?}', function () {
        abort_unless(auth()->user()->is_platform_admin, 403);

        return view('platform');
    })->where('any', '.*');
});
