<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Encabezados de seguridad en todas las respuestas del backend.
 *
 * - X-Content-Type-Options: el navegador respeta el tipo declarado (no interpreta un archivo
 *   subido como HTML o JavaScript).
 * - X-Frame-Options: la API no se puede incrustar en un iframe de otro sitio (clickjacking).
 * - Referrer-Policy: las URL de la API (con códigos o tokens de enlaces) no se filtran a otros sitios.
 * - Permissions-Policy: las respuestas de la API no pueden pedir cámara, micrófono ni ubicación.
 * - Strict-Transport-Security: solo por HTTPS; obliga al navegador a usar siempre HTTPS. Sin
 *   includeSubDomains, para no afectar otros sitios del mismo dominio.
 *
 * Si una ruta ya fijó alguno de estos encabezados, se respeta el suyo.
 */
class EncabezadosSeguridad
{
    private const ENCABEZADOS = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
        'Referrer-Policy' => 'no-referrer',
        'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
    ];

    public function handle(Request $solicitud, Closure $siguiente): Response
    {
        $respuesta = $siguiente($solicitud);

        foreach (self::ENCABEZADOS as $nombre => $valor) {
            if (! $respuesta->headers->has($nombre)) {
                $respuesta->headers->set($nombre, $valor);
            }
        }

        if ($solicitud->isSecure() && ! $respuesta->headers->has('Strict-Transport-Security')) {
            $respuesta->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }

        // No revelar la versión de PHP (la agrega PHP, fuera de la respuesta de Laravel).
        $respuesta->headers->remove('X-Powered-By');
        if (! headers_sent()) {
            header_remove('X-Powered-By');
        }

        return $respuesta;
    }
}
