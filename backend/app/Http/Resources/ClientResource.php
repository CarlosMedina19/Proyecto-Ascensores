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
            'uuid' => $this->uuid,
            'type' => $this->type,
            'name' => $this->name,
            'document_type' => $this->document_type,
            'document_number' => $this->document_number,
            'nit' => $this->nit,
            'address' => $this->address,
            'phone' => $this->phone,
            'email' => $this->email,
            'tax_regime' => $this->tax_regime,
            'economic_activity' => $this->economic_activity,
            'status' => (bool) $this->status,
            'observations' => $this->observations,
            'contacts' => ClientContactResource::collection($this->whenLoaded('contacts')),
            'buildings_count' => $this->whenCounted('buildings'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
