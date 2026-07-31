<?php

use App\Http\Middleware\ApiAuthenticate;
use App\Http\Middleware\AuditMutatingRequest;
use App\Http\Middleware\CustomerAuthenticate;
use App\Http\Middleware\RequestAuditContext;
use App\Http\Responses\ApiExceptionResponse;
use App\Services\AuditTrailService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Validation\ValidationException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php', api: __DIR__.'/../routes/api.php', apiPrefix: 'api/v1', commands: __DIR__.'/../routes/console.php', health: '/up')
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(HandleCors::class);
        $middleware->append([RequestAuditContext::class, AuditMutatingRequest::class]);
        $middleware->alias(['auth.api' => ApiAuthenticate::class, 'auth.customer' => CustomerAuthenticate::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthenticationException $exception, $request) {
            return ApiExceptionResponse::unauthenticated($request);
        });
        $exceptions->render(function (ValidationException $exception, $request) {
            $errors = $exception->errors();
            app(AuditTrailService::class)->validationFailure($request, $errors);

            return ApiExceptionResponse::validation($request, $errors);
        });
        $exceptions->shouldRenderJsonWhen(fn () => true);
    })->create();
