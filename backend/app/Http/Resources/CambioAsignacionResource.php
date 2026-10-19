<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class CambioAsignacionResource extends RecursoApi
{
    public function toArray(Request $solicitud): array
    {
        return [
            'id' => $this->id,
            'campo' => $this->campo,
            'valor_anterior' => $this->valor_anterior,
            'valor_nuevo' => $this->valor_nuevo,
            'motivo' => $this->motivo,
            'usuario' => $this->whenLoaded('usuario', fn () => $this->usuario ? ['id' => $this->usuario->id, 'nombre' => $this->usuario->nombre] : null),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
