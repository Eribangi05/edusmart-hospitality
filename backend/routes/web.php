<?php

use App\Http\Controllers\SiteController;
use App\Http\Controllers\WebAuthController;
use App\Support\Modules;
use App\Support\Workspaces;
use Illuminate\Support\Facades\Route;

Route::view('/', 'site.home')->name('home');
Route::view('/features', 'site.features');
Route::view('/solutions', 'site.solutions');
Route::get('/pricing', [SiteController::class, 'pricing']);
Route::view('/contact', 'site.contact');
Route::post('/contact', [SiteController::class, 'contact'])->middleware('throttle:5,10');
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
    // One entry point (and one layout) per job. Each checks the same permissions the API enforces.
    $workspace = fn (string $key, string $view) => function () use ($key, $view) {
        $u = auth()->user();
        abort_if($u->is_platform_admin || ! $u->tenant_id, 403);
        abort_unless(collect(Workspaces::for($u, $u->tenant->enabledModules()))->contains('key', $key), 403);

        return view($view);
    };
    Route::get('/terminal', $workspace('terminal', 'terminal'));
    Route::get('/kds', $workspace('kds', 'kds'));
    Route::get('/desk', $workspace('desk', 'desk'));
    Route::get('/staff', $workspace('staff', 'staff'));
    Route::get('/workspaces', function () {
        abort_if(auth()->user()->is_platform_admin || ! auth()->user()->tenant_id, 403);

        return view('workspaces');
    });
    Route::get('/admin/{any?}', function () {
        abort_unless(auth()->user()->is_platform_admin, 403);

        return view('platform');
    })->where('any', '.*');
});
