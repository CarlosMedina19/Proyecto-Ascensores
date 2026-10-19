<?php

namespace App\Http\Requests\Usuario;

use App\Models\Usuario;

class ActualizarUsuarioRequest extends GuardarUsuarioRequest
{
    protected function usuarioActual(): ?Usuario
    {
        return $this->route('usuario');
    }
}
