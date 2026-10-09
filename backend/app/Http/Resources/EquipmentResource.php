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
            'uuid' => $this->identificador_uuid,
            'building_id' => $this->edificio_id,
            'building' => new BuildingResource($this->whenLoaded('building')),
            'code' => $this->codigo,
            'type' => $this->tipo,
            'brand' => $this->marca,
            'model' => $this->modelo,
            'serial_number' => $this->numero_serie,
            'location' => $this->ubicacion,
            'status' => $this->estado,
            'installation_date' => $this->fecha_instalacion?->format('Y-m-d'),
            'observations' => $this->observaciones,
            'elevator' => new ElevatorResource($this->whenLoaded('elevator')),
            'history' => EquipmentHistoryResource::collection($this->whenLoaded('history')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
