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
            'uuid' => $this->uuid,
            'client_id' => $this->client_id,
            'client' => new ClientResource($this->whenLoaded('client')),
            'name' => $this->name,
            'address' => $this->address,
            'city' => $this->city,
            'department' => $this->department,
            'postal_code' => $this->postal_code,
            'floors' => $this->floors,
            'observations' => $this->observations,
            'contact_name' => $this->contact_name,
            'contact_phone' => $this->contact_phone,
            'contact_email' => $this->contact_email,
            'equipment_count' => $this->whenCounted('equipment'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
