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
        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) {
            if (
                $e instanceof \Illuminate\Http\Exceptions\HttpResponseException ||
                $e instanceof \Illuminate\Validation\ValidationException ||
                $e instanceof \Illuminate\Auth\AuthenticationException ||
                $e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface
            ) {
                return null;
            }

            if ($request->expectsJson() || $request->is('api/*')) {
                \Illuminate\Support\Facades\Log::error('Unhandled API exception', [
                    'url'    => $request->url(),
                    'method' => $request->method(),
                    'class'  => get_class($e),
                    'error'  => $e->getMessage(),
                ]);

                return response()->json(
                    ['message' => 'Ocorreu um erro interno. Tente novamente.'],
                    500
                );
            }

            return null;
        });
    })->create();
