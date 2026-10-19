<?php

namespace App\Http\Requests\Rol;

use App\Models\Rol;

class ActualizarRolRequest extends GuardarRolRequest
{
    protected function rolActual(): ?Rol
    {
        return $this->route('rol');
    }
}
