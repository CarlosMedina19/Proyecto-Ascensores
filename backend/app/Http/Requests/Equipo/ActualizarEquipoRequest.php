<?php

namespace App\Http\Requests\Equipo;

use App\Models\Equipo;

class ActualizarEquipoRequest extends GuardarEquipoRequest
{
    protected function equipoActual(): ?Equipo
    {
        return $this->route('equipo');
    }
}
