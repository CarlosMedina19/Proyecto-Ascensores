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
            'cliente_id' => $this->cliente_id,
            'cliente' => $this->whenLoaded('cliente', fn () => $this->cliente ? [
                'id' => $this->cliente->id,
                'nombre' => $this->cliente->nombre,
            ] : null),
            'correo_verificado_en' => $this->correo_verificado_en?->toIso8601String(),
            'ultimo_acceso_en' => $this->ultimo_acceso_en?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
