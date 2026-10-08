<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EquipmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'uuid' => $this->uuid,
            'building_id' => $this->building_id,
            'building' => new BuildingResource($this->whenLoaded('building')),
            'code' => $this->code,
            'type' => $this->type,
            'brand' => $this->brand,
            'model' => $this->model,
            'serial_number' => $this->serial_number,
            'location' => $this->location,
            'status' => $this->status,
            'installation_date' => $this->installation_date?->format('Y-m-d'),
            'observations' => $this->observations,
            'elevator' => new ElevatorResource($this->whenLoaded('elevator')),
            'history' => EquipmentHistoryResource::collection($this->whenLoaded('history')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
