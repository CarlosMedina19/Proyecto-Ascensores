<?php

use App\Http\Middleware\TraducirContratoApiEspanol;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthenticationException $exception, Request $request) {
            $middleware = $request->route()?->gatherMiddleware() ?? [];

            if (! $request->expectsJson() || ! in_array(TraducirContratoApiEspanol::class, $middleware, true)) {
                return null;
            }

            return response()->json([
                'mensaje' => 'Se requiere autenticación para acceder a este recurso.',
            ], 401);
        });
    })->create();
