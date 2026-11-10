<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

/** Aviso de que el usuario tiene una notificación nueva: la campana se actualiza sin recargar. */
class NotificacionRecibida implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public function __construct(public int $usuarioId, public string $titulo, public string $mensaje) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("usuarios.{$this->usuarioId}")];
    }

    public function broadcastAs(): string
    {
        return 'notificacion.recibida';
    }

    public function broadcastWith(): array
    {
        return ['titulo' => $this->titulo, 'mensaje' => $this->mensaje];
    }
}
