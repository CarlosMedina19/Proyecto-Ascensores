<?php

namespace App\Policies;

use App\Models\Usuario;

class UsuarioPolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->tienePermiso('usuarios.ver');
    }

    public function view(Usuario $usuario, Usuario $modelo): bool
    {
        return $usuario->tienePermiso('usuarios.ver');
    }

    public function create(Usuario $usuario): bool
    {
        return $usuario->tienePermiso('usuarios.crear');
    }

    public function update(Usuario $usuario, Usuario $modelo): bool
    {
        return $usuario->tienePermiso('usuarios.editar');
    }

    public function alternarEstado(Usuario $usuario, Usuario $modelo): bool
    {
        return $usuario->id !== $modelo->id && $usuario->tienePermiso('usuarios.editar');
    }
}
