<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class InformeOrdenResource extends RecursoApi
{
    public function toArray(Request $solicitud): array
    {
        return [
            'id' => $this->id,
            'version' => $this->version,
            'motivo' => $this->motivo,
            'hash' => $this->hash,
            'tamano' => $this->tamano,
            'generado_por' => $this->whenLoaded('generador', fn () => $this->generador?->nombre),
            'created_at' => $this->created_at?->toIso8601String(),
            'ruta_archivo' => "/ordenes/{$this->orden_id}/informes/{$this->id}/pdf",
        ];
    }
}
