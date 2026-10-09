<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class RolResource extends RecursoApi
{
    public function toArray(Request $solicitud): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'permisos' => $this->whenLoaded('permisos', function () {
                return $this->permisos->pluck('nombre')->values();
            }),
            'usuarios_count' => $this->whenCounted('usuarios'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
