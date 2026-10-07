<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ElectricDoorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'equipment_id' => $this->equipment_id,
            'brand' => $this->brand,
            'model' => $this->model,
            'door_type' => $this->door_type,
            'opening_type' => $this->opening_type,
            'access_type' => $this->access_type,
            'serial_number' => $this->serial_number,
            'technical_specs' => $this->technical_specs,
            'installation_date' => $this->installation_date?->format('Y-m-d'),
        ];
    }
}
