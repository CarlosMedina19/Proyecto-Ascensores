<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class HistorialPrecioResource extends RecursoApi
{
    public function toArray(Request $solicitud): array
    {
        return [
            'id' => $this->id,
            'plan_id' => $this->plan_id,
            'periodicidad_id' => $this->periodicidad_id,
            'periodicidad' => new PeriodicidadResource($this->whenLoaded('periodicidad')),
            'asignacion_id' => $this->asignacion_id,
            'precio_anterior' => $this->precio_anterior,
            'precio_nuevo' => $this->precio_nuevo,
            'motivo' => $this->motivo,
            'usuario' => $this->whenLoaded('usuario', fn () => $this->usuario ? ['id' => $this->usuario->id, 'nombre' => $this->usuario->nombre] : null),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
