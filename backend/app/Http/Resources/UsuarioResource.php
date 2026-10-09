<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class UsuarioResource extends RecursoApi
{
    public function toArray(Request $solicitud): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'correo' => $this->correo,
            'activo' => (bool) $this->activo,
            'rol_id' => $this->rol_id,
            'rol' => new RolResource($this->whenLoaded('rol')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
