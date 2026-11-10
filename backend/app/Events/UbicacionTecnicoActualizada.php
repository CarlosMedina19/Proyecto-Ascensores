<?php

namespace App\Events;

use App\Models\UbicacionTecnico;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Posición del técnico mientras atiende un mantenimiento: mueve su marcador en el mapa de Auditoría. */
class UbicacionTecnicoActualizada implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public bool $afterCommit = true;

    public function __construct(public UbicacionTecnico $ubicacion) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel('auditoria')];
    }

    public function broadcastAs(): string
    {
        return 'ubicacion.actualizada';
    }

    public function broadcastWith(): array
    {
        return $this->ubicacion->resumen();
    }
}
