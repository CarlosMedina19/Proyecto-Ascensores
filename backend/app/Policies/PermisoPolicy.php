<?php

namespace App\Policies;

use App\Models\Usuario;

class PermisoPolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->tienePermiso('permisos.ver');
    }
}
