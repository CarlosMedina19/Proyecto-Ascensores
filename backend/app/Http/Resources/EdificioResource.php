<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class EdificioResource extends RecursoApi
{
    public function toArray(Request $solicitud): array
    {
        return [
            'id' => $this->id,
            'identificador_uuid' => $this->identificador_uuid,
            'cliente_id' => $this->cliente_id,
            'cliente' => new ClienteResource($this->whenLoaded('cliente')),
            'nombre' => $this->nombre,
            'direccion' => $this->direccion,
            'ciudad' => $this->ciudad,
            'departamento' => $this->departamento,
            'codigo_postal' => $this->codigo_postal,
            'pisos' => $this->pisos,
            'observaciones' => $this->observaciones,
            'nombre_contacto' => $this->nombre_contacto,
            'telefono_contacto' => $this->telefono_contacto,
            'correo_contacto' => $this->correo_contacto,
            'equipment_count' => $this->whenCounted('equipos'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
