<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class EquipoResource extends RecursoApi
{
    public function toArray(Request $solicitud): array
    {
        return [
            'id' => $this->id,
            'identificador_uuid' => $this->identificador_uuid,
            'edificio_id' => $this->edificio_id,
            'edificio' => new EdificioResource($this->whenLoaded('edificio')),
            'codigo' => $this->codigo,
            'tipo' => $this->tipo,
            'marca' => $this->marca,
            'modelo' => $this->modelo,
            'numero_serie' => $this->numero_serie,
            'ubicacion' => $this->ubicacion,
            'estado' => $this->estado,
            'fecha_instalacion' => $this->fecha_instalacion?->format('Y-m-d'),
            'observaciones' => $this->observaciones,
            'ascensor' => new AscensorResource($this->whenLoaded('ascensor')),
            'historial' => HistorialEquipoResource::collection($this->whenLoaded('historial')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
