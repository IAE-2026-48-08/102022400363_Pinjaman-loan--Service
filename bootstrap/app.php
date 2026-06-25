<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                $statusCode = 500;
                $message = $e->getMessage();
                $errors = null;

                if ($e instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException ||
                    $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                    $statusCode = 404;
                    $message = 'Endpoint atau data tidak ditemukan.';
                } elseif ($e instanceof \Illuminate\Validation\ValidationException) {
                    $statusCode = 422;
                    $message = 'Validasi gagal.';
                    $errors = $e->errors();
                } elseif ($e instanceof \Illuminate\Auth\AuthenticationException) {
                    $statusCode = 401;
                    $message = 'Autentikasi gagal.';
                } elseif ($e instanceof \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException ||
                           $e instanceof \Illuminate\Auth\AccessDeniedException) {
                    $statusCode = 403;
                    $message = 'Akses ditolak.';
                } elseif ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpException) {
                    $statusCode = $e->getStatusCode();
                    $message = $e->getMessage();
                } else {
                    if (!config('app.debug')) {
                        $message = 'Terjadi kesalahan internal pada server.';
                    }
                }

                return response()->json([
                    'status' => 'error',
                    'message' => $message,
                    'errors' => $errors
                ], $statusCode);
            }
        });
    })->create();
