<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class FirmaOrdenResource extends RecursoApi
{
    public function toArray(Request $solicitud): array
    {
        return [
            'id' => $this->id,
            'tipo' => $this->tipo,
            'nombre' => $this->nombre,
            'documento' => $this->documento,
            'cargo' => $this->cargo,
            'firmado_en' => $this->firmado_en?->toIso8601String(),
            'ruta_archivo' => "/ordenes/{$this->orden_id}/firmas/{$this->id}/archivo",
        ];
    }
}
