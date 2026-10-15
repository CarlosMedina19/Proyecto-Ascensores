<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class AscensorResource extends RecursoApi
{
    public function toArray(Request $solicitud): array
    {
        return [
            'id' => $this->id,
            'equipo_id' => $this->equipo_id,
            'marca' => $this->marca,
            'modelo' => $this->modelo,
            'capacidad_kg' => $this->capacidad_kg,
            'paradas' => $this->paradas,
            'velocidad_mpm' => $this->velocidad_mpm,
            'tipo_traccion' => $this->tipo_traccion,
            'motor' => $this->motor,
            'controlador' => $this->controlador,
            'tipo_puerta' => $this->tipo_puerta,
            'especificaciones_tecnicas' => $this->especificaciones_tecnicas,
            'fecha_instalacion' => $this->fecha_instalacion?->format('Y-m-d'),
        ];
    }
}
