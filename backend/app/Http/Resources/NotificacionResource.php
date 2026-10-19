<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * Notificación de la aplicación (canal database de Laravel).
 */
class NotificacionResource extends RecursoApi
{
    public function toArray(Request $solicitud): array
    {
        return [
            'id' => $this->id,
            'tipo' => $this->data['tipo'] ?? null,
            'titulo' => $this->data['titulo'] ?? null,
            'mensaje' => $this->data['mensaje'] ?? null,
            'enlace' => $this->data['enlace'] ?? null,
            'leida' => $this->read_at !== null,
            'leida_en' => $this->read_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
