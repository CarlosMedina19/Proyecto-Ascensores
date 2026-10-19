<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificacionResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Bandeja de notificaciones del usuario autenticado (avisos de mantenimiento y de contratos).
 */
class NotificacionController extends Controller
{
    public function index(Request $solicitud): AnonymousResourceCollection
    {
        $consulta = $solicitud->user()->notifications();
        if ($solicitud->boolean('solo_no_leidas')) {
            $consulta->whereNull('read_at');
        }

        return NotificacionResource::collection(
            $consulta->paginate($this->porPagina($solicitud), ['*'], 'pagina')
        )->additional([
            'no_leidas' => $solicitud->user()->unreadNotifications()->count(),
        ]);
    }

    public function marcarLeida(Request $solicitud, string $notificacion): JsonResponse
    {
        $registro = $solicitud->user()->notifications()->whereKey($notificacion)->firstOrFail();
        $registro->markAsRead();

        return response()->json(['mensaje' => 'Notificación marcada como leída']);
    }

    public function marcarTodasLeidas(Request $solicitud): JsonResponse
    {
        $marcadas = $solicitud->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json([
            'mensaje' => $marcadas > 0 ? 'Notificaciones marcadas como leídas' : 'No había notificaciones pendientes',
            'marcadas' => $marcadas,
        ]);
    }
}
