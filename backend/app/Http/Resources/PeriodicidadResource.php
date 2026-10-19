<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class PeriodicidadResource extends RecursoApi
{
    public function toArray(Request $solicitud): array
    {
        return [
            'id' => $this->id,
            'meses' => $this->meses,
            'nombre' => $this->nombre,
            'activo' => (bool) $this->activo,
        ];
    }
}
