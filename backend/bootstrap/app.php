<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // En producción Caddy termina HTTPS y reenvía la solicitud: se confía en sus encabezados
        // X-Forwarded-* para generar enlaces https (QR, correos, archivos).
        $middleware->trustProxies(at: '*');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Toda la API responde en JSON y con mensajes en español.
        $exceptions->shouldRenderJsonWhen(fn (Request $solicitud) => $solicitud->is('api/*') || $solicitud->expectsJson());

        $esApi = fn (Request $solicitud) => $solicitud->is('api/*') || $solicitud->expectsJson();

        $exceptions->render(function (ValidationException $e, Request $solicitud) use ($esApi) {
            if ($esApi($solicitud)) {
                return response()->json([
                    'mensaje' => 'Los datos enviados no son válidos.',
                    'errores' => $e->errors(),
                ], $e->status);
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $solicitud) use ($esApi) {
            if ($esApi($solicitud)) {
                return response()->json(['mensaje' => 'Sesión no válida o expirada. Inicie sesión nuevamente.'], 401);
            }
        });

        $exceptions->render(function (AccessDeniedHttpException|AuthorizationException $e, Request $solicitud) use ($esApi) {
            if ($esApi($solicitud)) {
                $mensaje = $e->getMessage();
                $generico = in_array($mensaje, ['', 'This action is unauthorized.'], true);

                return response()->json(['mensaje' => $generico ? 'No tiene permiso para realizar esta acción.' : $mensaje], 403);
            }
        });

        $exceptions->render(function (InvalidSignatureException $e, Request $solicitud) {
            return response()->json(['mensaje' => 'El enlace no es válido o ya venció.'], 403);
        });

        $exceptions->render(function (NotFoundHttpException|ModelNotFoundException $e, Request $solicitud) use ($esApi) {
            if ($esApi($solicitud)) {
                return response()->json(['mensaje' => 'El recurso solicitado no existe.'], 404);
            }
        });

        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $solicitud) use ($esApi) {
            if ($esApi($solicitud)) {
                return response()->json(['mensaje' => 'Método HTTP no permitido para esta ruta.'], 405);
            }
        });

        $exceptions->render(function (ThrottleRequestsException $e, Request $solicitud) use ($esApi) {
            if ($esApi($solicitud)) {
                return response()->json(['mensaje' => 'Demasiadas solicitudes. Intente de nuevo en un momento.'], 429, $e->getHeaders());
            }
        });

        // Errores HTTP lanzados por los servicios (401, 403, 423...) con su mensaje en español.
        $exceptions->render(function (HttpExceptionInterface $e, Request $solicitud) use ($esApi) {
            if ($esApi($solicitud)) {
                return response()->json(
                    ['mensaje' => $e->getMessage() ?: 'No fue posible procesar la solicitud.'],
                    $e->getStatusCode(),
                    $e->getHeaders()
                );
            }
        });
    })->create();
