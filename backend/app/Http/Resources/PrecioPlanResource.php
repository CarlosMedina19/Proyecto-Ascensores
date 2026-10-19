<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class PrecioPlanResource extends RecursoApi
{
    public function toArray(Request $solicitud): array
    {
        return [
            'periodicidad_id' => $this->periodicidad_id,
            'periodicidad' => new PeriodicidadResource($this->whenLoaded('periodicidad')),
            'precio' => $this->precio,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
