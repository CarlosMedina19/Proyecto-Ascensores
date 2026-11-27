<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Envíos sin conexión de la app: cada cambio guardado en el celular lleva una clave única (X-Clave-Envio).
 * Si la app lo reintenta porque no alcanzó a recibir la respuesta, se devuelve la misma respuesta sin volver
 * a ejecutarlo (no se duplican planillas, fotos ni repuestos). La clave dura 7 días.
 */
class EnvioSinDuplicados
{
    private const DIAS = 7;

    public function handle(Request $solicitud, Closure $siguiente): Response
    {
        $clave = (string) $solicitud->header('X-Clave-Envio', '');
        if ($clave === '' || $solicitud->isMethodSafe() || ! preg_match('/^[A-Za-z0-9-]{8,64}$/', $clave) || ! $solicitud->user()) {
            return $siguiente($solicitud);
        }

        $llave = 'envio:'.$solicitud->user()->getAuthIdentifier().':'.$clave;

        // Dos reintentos simultáneos de la misma clave esperan su turno.
        return Cache::lock($llave.':turno', 60)->block(30, function () use ($solicitud, $siguiente, $llave) {
            if ($guardada = Cache::get($llave)) {
                return response()->json($guardada['cuerpo'], $guardada['estado'])->header('X-Envio-Repetido', '1');
            }

            $respuesta = $siguiente($solicitud);
            // Solo se recuerda lo que el servidor resolvió (éxito o regla de negocio); un error 5xx se puede reintentar.
            if ($respuesta instanceof JsonResponse && $respuesta->getStatusCode() < 500 && ! in_array($respuesta->getStatusCode(), [401, 419, 429], true)) {
                Cache::put($llave, ['estado' => $respuesta->getStatusCode(), 'cuerpo' => $respuesta->getData(true)], now()->addDays(self::DIAS));
            }

            return $respuesta;
        });
    }
}
