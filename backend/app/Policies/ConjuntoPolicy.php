<?php

namespace App\Policies;

use App\Models\Conjunto;
use App\Models\Usuario;

/** Los conjuntos usan los permisos de edificios. */
class ConjuntoPolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->tienePermiso('edificios.ver');
    }

    public function view(Usuario $usuario, Conjunto $conjunto): bool
    {
        return $usuario->tienePermiso('edificios.ver') && $usuario->puedeVerCliente($conjunto->cliente_id);
    }

    public function create(Usuario $usuario): bool
    {
        return $usuario->tienePermiso('edificios.crear');
    }

    public function update(Usuario $usuario, Conjunto $conjunto): bool
    {
        return $usuario->tienePermiso('edificios.editar');
    }

    public function delete(Usuario $usuario, Conjunto $conjunto): bool
    {
        return $usuario->tienePermiso('edificios.eliminar');
    }
}
