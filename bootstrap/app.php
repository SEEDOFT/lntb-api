<?php

use App\Exceptions\BusinessException;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(static function (Middleware $middleware): void {
        $middleware->append(\App\Http\Middleware\SetAppLocale::class);
    })
    ->withExceptions(static function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(static fn (Request $request): bool => true);
        $exceptions->render(static fn (BusinessException $e) => ApiResponse::error($e->getMessage(), $e->errorCode, $e->status));
        $exceptions->render(static fn (ValidationException $e) => ApiResponse::error(__('messages.validation_failed'), 'VALIDATION_ERROR', 422, $e->errors()));
        $exceptions->render(static fn (AuthenticationException $e) => ApiResponse::error(__('messages.unauthenticated'), 'UNAUTHENTICATED', 401));
        $exceptions->render(static fn (AuthorizationException $e) => ApiResponse::error(__('messages.forbidden'), 'FORBIDDEN', 403));
        $exceptions->render(static fn (ThrottleRequestsException $e) => ApiResponse::error(__('messages.rate_limit_exceeded'), 'RATE_LIMIT_EXCEEDED', 429));
        $exceptions->render(static fn (ModelNotFoundException|NotFoundHttpException $e) => ApiResponse::error(__('messages.resource_not_found'), 'RESOURCE_NOT_FOUND', 404));
    })
    ->create();
