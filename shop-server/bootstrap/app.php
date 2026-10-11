<?php

use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\EnsureUserIsActive;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: env('TRUSTED_PROXIES'));

        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'role' => EnsureUserHasRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'message' => 'Unauthenticated.',
                'code' => 'unauthenticated',
            ], 401);
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            $errors = $e->errors();
            $code = 'validation_failed';

            if (isset($errors['email']) && collect($errors['email'])->contains(
                fn (string $message) => str_contains(strtolower($message), 'taken')
                    || str_contains(strtolower($message), 'already')
            )) {
                $code = 'email_taken';
            }

            return response()->json([
                'message' => $e->getMessage(),
                'code' => $code,
                'errors' => $errors,
            ], $e->status);
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'message' => 'Resource not found.',
                'code' => 'not_found',
            ], 404);
        });

        $exceptions->render(function (TooManyRequestsHttpException $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'message' => 'Too many requests. Please try again later.',
                'code' => 'too_many_requests',
            ], 429)->withHeaders($e->getHeaders());
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            if ($e instanceof NotFoundHttpException || $e instanceof TooManyRequestsHttpException) {
                return null;
            }

            return response()->json([
                'message' => $e->getMessage() !== '' ? $e->getMessage() : 'Request failed.',
                'code' => match ($e->getStatusCode()) {
                    403 => 'forbidden',
                    401 => 'unauthenticated',
                    404 => 'not_found',
                    429 => 'too_many_requests',
                    default => 'http_error',
                },
            ], $e->getStatusCode());
        });
    })->create();
