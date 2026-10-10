<?php

use App\Exceptions\ApiError;
use App\Http\Middleware\CheckModule;
use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\PlatformAdmin;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'tenant' => ResolveTenant::class,
            'perm' => CheckPermission::class,
            'module' => CheckModule::class,
            'platform' => PlatformAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(fn (Request $r) => $r->is('api/*') || $r->expectsJson());

        $envelope = fn (string $code, string $message, int $status, array $details = []) => response()->json(
            ['error' => array_filter(['code' => $code, 'message' => $message, 'details' => $details ?: null])], $status);

        $exceptions->render(fn (ApiError $e, Request $r) => $r->is('api/*') ? $envelope($e->errorCode, $e->getMessage(), $e->status, $e->details) : null);
        $exceptions->render(fn (ValidationException $e, Request $r) => $r->is('api/*') ? $envelope('validation_failed', 'The given data was invalid.', 422, $e->errors()) : null);
        $exceptions->render(fn (AuthenticationException $e, Request $r) => $r->is('api/*') ? $envelope('unauthenticated', 'Unauthenticated.', 401) : null);
        $exceptions->render(fn (ModelNotFoundException|NotFoundHttpException $e, Request $r) => $r->is('api/*') ? $envelope('not_found', 'Not found.', 404) : null);
        $exceptions->render(fn (TooManyRequestsHttpException $e, Request $r) => $r->is('api/*') ? $envelope('rate_limited', 'Too many requests.', 429) : null);
    })->create();
