<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class TrazabilidadOrdenResource extends RecursoApi
{
    public function toArray(Request $solicitud): array
    {
        return [
            'id' => $this->id,
            'accion' => $this->accion,
            'campo' => $this->campo,
            'valor_anterior' => $this->valor_anterior,
            'valor_nuevo' => $this->valor_nuevo,
            'detalle' => $this->detalle,
            'usuario' => $this->whenLoaded('usuario', fn () => $this->usuario ? ['id' => $this->usuario->id, 'nombre' => $this->usuario->nombre] : null),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
