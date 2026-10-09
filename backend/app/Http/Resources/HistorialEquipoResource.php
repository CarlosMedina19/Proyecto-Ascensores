<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class HistorialEquipoResource extends RecursoApi
{
    public function toArray(Request $solicitud): array
    {
        return [
            'id' => $this->id,
            'equipo_id' => $this->equipo_id,
            'usuario' => new UsuarioResource($this->whenLoaded('usuario')),
            'evento' => $this->evento,
            'descripcion' => $this->descripcion,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
