<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ElevatorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'equipment_id' => $this->equipment_id,
            'brand' => $this->brand,
            'model' => $this->model,
            'capacity_kg' => $this->capacity_kg,
            'stops' => $this->stops,
            'speed_mpm' => $this->speed_mpm,
            'drive_type' => $this->drive_type,
            'motor' => $this->motor,
            'controller' => $this->controller,
            'door_type' => $this->door_type,
            'technical_specs' => $this->technical_specs,
            'installation_date' => $this->installation_date?->format('Y-m-d'),
        ];
    }
}
