<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class FotoEquipoResource extends RecursoApi
{
    public function toArray(Request $solicitud): array
    {
        return [
            'id' => $this->id,
            'nombre' => $this->nombre_original,
            'tipo_mime' => $this->tipo_mime,
            'tamano' => $this->tamano,
            'orden' => $this->orden,
            // Ruta de la API (relativa a /api/v1): se descarga enviando el token.
            'ruta_archivo' => "/equipos/{$this->equipo_id}/fotos/{$this->id}/archivo",
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
