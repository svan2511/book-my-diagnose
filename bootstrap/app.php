<?php

use App\Exceptions\BusinessRuleException;
use App\Http\Middleware\AdminAuthenticate;
use App\Http\Middleware\ForceApiJson;
use App\Http\Middleware\RequestId;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Behind Render's TLS proxy (and any CDN), respect X-Forwarded-*
        // so generated URLs (signed report links!) use the public https host.
        $middleware->trustProxies(at: '*');
        $middleware->appendToGroup('api', ForceApiJson::class);
        $middleware->appendToGroup('api', RequestId::class);
        $middleware->alias(['admin' => AdminAuthenticate::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Consistent JSON envelope for every API failure (dev-prd §7, §46).
        // Production never leaks stack traces, SQL or file paths.
        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! \App\Support\ApiRequest::isApi($request)) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'The given data was invalid.',
                'errors' => $e->errors(),
            ], 422);
        });

        $exceptions->render(function (BusinessRuleException $e, Request $request) {
            if (! \App\Support\ApiRequest::isApi($request)) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'errors' => (object) $e->errors(),
            ], 422);
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if (! \App\Support\ApiRequest::isApi($request)) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'errors' => (object) [],
            ], 401);
        });

        $exceptions->render(function (NotFoundHttpException|ModelNotFoundException $e, Request $request) {
            if (! \App\Support\ApiRequest::isApi($request)) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'Resource not found.',
                'errors' => (object) [],
            ], 404);
        });

        $exceptions->render(function (HttpException $e, Request $request) {
            if (! \App\Support\ApiRequest::isApi($request)) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'Request failed.',
                'errors' => (object) [],
            ], $e->getStatusCode());
        });

        // Database errors must never leak SQL, table names or constraint
        // details to API consumers — not even with debug mode on. Full
        // details still go to the log via report().
        $exceptions->render(function (QueryException $e, Request $request) {
            if (! \App\Support\ApiRequest::isApi($request)) {
                return null;
            }

            report($e);

            $message = $e->getMessage();
            $isDuplicate = $e->getCode() === '23000'
                || str_contains($message, 'UNIQUE constraint failed')
                || str_contains($message, 'Duplicate entry')
                || str_contains($message, 'duplicate key');

            if ($isDuplicate) {
                return response()->json([
                    'success' => false,
                    'message' => 'This record already exists. Please check your input and try again.',
                    'errors' => (object) [],
                ], 422);
            }

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong. Please try again.',
                'errors' => (object) [],
            ], 500);
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            if (! \App\Support\ApiRequest::isApi($request)) {
                return null;
            }

            report($e);

            return response()->json([
                'success' => false,
                'message' => app()->hasDebugModeEnabled() ? $e->getMessage() : 'Internal server error.',
                'errors' => (object) [],
            ], 500);
        });
    })->create();
