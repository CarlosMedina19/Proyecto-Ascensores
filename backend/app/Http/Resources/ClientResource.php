<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->identificador_uuid,
            'type' => $this->tipo,
            'name' => $this->nombre,
            'document_type' => $this->tipo_documento,
            'document_number' => $this->numero_documento,
            'nit' => $this->nit,
            'address' => $this->direccion,
            'phone' => $this->telefono,
            'email' => $this->correo,
            'tax_regime' => $this->regimen_tributario,
            'economic_activity' => $this->actividad_economica,
            'status' => (bool) $this->estado,
            'observations' => $this->observaciones,
            'contacts' => ClientContactResource::collection($this->whenLoaded('contacts')),
            'buildings_count' => $this->whenCounted('buildings'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
