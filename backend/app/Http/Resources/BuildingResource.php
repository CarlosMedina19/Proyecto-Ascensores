<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BuildingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->identificador_uuid,
            'client_id' => $this->cliente_id,
            'client' => new ClientResource($this->whenLoaded('client')),
            'name' => $this->nombre,
            'address' => $this->direccion,
            'city' => $this->ciudad,
            'department' => $this->departamento,
            'postal_code' => $this->codigo_postal,
            'floors' => $this->pisos,
            'observations' => $this->observaciones,
            'contact_name' => $this->nombre_contacto,
            'contact_phone' => $this->telefono_contacto,
            'contact_email' => $this->correo_contacto,
            'equipment_count' => $this->whenCounted('equipment'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
