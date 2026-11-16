<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * El token temporal que recibe el agente de n8n solo sirve para /asistente/herramienta:
 * con él no se puede usar ninguna otra ruta de la API.
 */
class LimitarTokenAsistente
{
    public function handle(Request $solicitud, Closure $siguiente): Response
    {
        $token = $solicitud->user()?->currentAccessToken();
        if ($token && ($token->name ?? null) === 'asistente-n8n' && ! $solicitud->routeIs('asistente.herramienta')) {
            abort(403, 'Este token solo sirve para las consultas del asistente.');
        }

        return $siguiente($solicitud);
    }
}
