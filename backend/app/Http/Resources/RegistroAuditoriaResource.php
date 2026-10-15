<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class RegistroAuditoriaResource extends RecursoApi
{
    public function toArray(Request $solicitud): array
    {
        return [
            'id' => $this->id,
            'usuario' => new UsuarioResource($this->whenLoaded('usuario')),
            'accion' => $this->accion,
            'modelo' => class_basename($this->modelo),
            'modelo_id' => $this->modelo_id,
            'cambios' => $this->cambios,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
