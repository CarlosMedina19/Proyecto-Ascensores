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
            'capacidad_personas' => $this->capacidad_personas,
            'paradas' => $this->paradas,
            'velocidad_mpm' => $this->velocidad_mpm,
            'tipo_ascensor' => $this->tipo_ascensor,
            'tipo_traccion' => $this->tipo_traccion,
            'motor' => $this->motor,
            'controlador' => $this->controlador,
            'tipo_puerta' => $this->tipo_puerta,
            'puertas_cabina' => $this->puertas_cabina,
            'puertas_piso' => $this->puertas_piso,
            'operador_puertas' => $this->operador_puertas,
            'especificaciones_tecnicas' => $this->especificaciones_tecnicas,
            'fecha_instalacion' => $this->fecha_instalacion?->format('Y-m-d'),
        ];
    }
}
