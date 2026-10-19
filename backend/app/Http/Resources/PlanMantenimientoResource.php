<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class PlanMantenimientoResource extends RecursoApi
{
    public function toArray(Request $solicitud): array
    {
        return [
            'id' => $this->id,
            'identificador_uuid' => $this->identificador_uuid,
            'nombre' => $this->nombre,
            'descripcion' => $this->descripcion,
            'servicios_incluidos' => $this->servicios_incluidos ?? [],
            'activo' => (bool) $this->activo,
            'precios' => PrecioPlanResource::collection($this->whenLoaded('precios')),
            'asignaciones_activas_count' => $this->whenHas('asignaciones_activas_count'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
