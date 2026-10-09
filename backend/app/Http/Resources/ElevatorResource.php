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
            'equipment_id' => $this->equipo_id,
            'brand' => $this->marca,
            'model' => $this->modelo,
            'capacity_kg' => $this->capacidad_kg,
            'stops' => $this->paradas,
            'speed_mpm' => $this->velocidad_mpm,
            'drive_type' => $this->tipo_traccion,
            'motor' => $this->motor,
            'controller' => $this->controlador,
            'door_type' => $this->tipo_puerta,
            'technical_specs' => $this->especificaciones_tecnicas,
            'installation_date' => $this->fecha_instalacion?->format('Y-m-d'),
        ];
    }
}
