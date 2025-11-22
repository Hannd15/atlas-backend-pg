<?php

use App\Http\Middleware\AuthenticateViaAtlas;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'auth.atlas' => AuthenticateViaAtlas::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $shouldHandle = static fn (Request $request): bool => $request->expectsJson() || Str::startsWith($request->path(), 'api/');

        $exceptions->render(function (MethodNotAllowedHttpException $exception, Request $request) use ($shouldHandle) {
            if (! $shouldHandle($request)) {
                return null;
            }

            $allowedHeader = $exception->getHeaders()['Allow'] ?? null;
            $allowed = is_array($allowedHeader) ? implode(', ', $allowedHeader) : $allowedHeader;

            return response()->json([
                'message' => $allowed
                    ? 'Method not allowed. Allowed methods: '.$allowed
                    : 'Method not allowed for this endpoint.',
            ], 405, array_filter(['Allow' => $allowed]));
        });

        $exceptions->render(function (NotFoundHttpException $exception, Request $request) use ($shouldHandle) {
            if (! $shouldHandle($request)) {
                return null;
            }

            return response()->json([
                'message' => $exception->getMessage() !== '' ? $exception->getMessage() : 'Resource not found.',
            ], 404);
        });

        $exceptions->render(function (HttpResponseException $exception, Request $request) {
            return $exception->getResponse();
        });

        $exceptions->render(function (Throwable $exception, Request $request) use ($shouldHandle) {
            if (! $shouldHandle($request)) {
                return null;
            }

            $status = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500;
            $message = $exception->getMessage();

            if ($status >= 500 && ! config('app.debug')) {
                $message = 'Unexpected server error.';
            }

            return response()->json([
                'message' => $message !== '' ? $message : 'Unexpected server error.',
            ], $status);
        });
    })->create();
