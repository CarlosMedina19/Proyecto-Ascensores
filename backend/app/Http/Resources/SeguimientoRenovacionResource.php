<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class SeguimientoRenovacionResource extends RecursoApi
{
    public function toArray(Request $solicitud): array
    {
        return [
            'id' => $this->id,
            'resultado' => $this->resultado,
            'observacion' => $this->observacion,
            'usuario' => $this->whenLoaded('usuario', fn () => $this->usuario ? ['id' => $this->usuario->id, 'nombre' => $this->usuario->nombre] : null),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
