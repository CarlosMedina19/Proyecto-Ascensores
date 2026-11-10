<?php

namespace App\Events;

use App\Models\RegistroAuditoria;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Cada registro de auditoría nuevo llega en vivo a la pantalla de Auditoría (canal privado «auditoria»). */
class RegistroAuditoriaCreado implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /** Se envía después de confirmar la transacción: si algo se deshace, no se anuncia. */
    public bool $afterCommit = true;

    public function __construct(public RegistroAuditoria $registro) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('auditoria')];
    }

    public function broadcastAs(): string
    {
        return 'registro.creado';
    }

    public function broadcastWith(): array
    {
        $this->registro->loadMissing('usuario');

        return [
            'id' => $this->registro->id,
            'usuario' => $this->registro->usuario ? ['id' => $this->registro->usuario->id, 'nombre' => $this->registro->usuario->nombre] : null,
            'accion' => $this->registro->accion,
            'modulo' => $this->registro->modulo,
            'modelo' => class_basename($this->registro->modelo),
            'modelo_id' => $this->registro->modelo_id,
            'created_at' => $this->registro->created_at?->toIso8601String(),
        ];
    }
}
