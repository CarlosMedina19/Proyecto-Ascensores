<?php

namespace App\Policies;

use App\Models\Rol;
use App\Models\Usuario;

class RolPolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->tienePermiso('roles.ver');
    }

    public function view(Usuario $usuario, Rol $rol): bool
    {
        return $usuario->tienePermiso('roles.ver');
    }

    public function create(Usuario $usuario): bool
    {
        return $usuario->tienePermiso('roles.crear');
    }

    public function update(Usuario $usuario, Rol $rol): bool
    {
        return $rol->nombre !== 'administrador' && $usuario->tienePermiso('roles.editar');
    }

    public function sincronizarPermisos(Usuario $usuario, Rol $rol): bool
    {
        return $rol->nombre !== 'administrador' && $usuario->tienePermiso('roles.permisos.editar');
    }
}
