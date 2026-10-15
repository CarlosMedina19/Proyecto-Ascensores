<?php

namespace App\Policies;

use App\Models\Edificio;
use App\Models\Usuario;

class EdificioPolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->tienePermiso('edificios.ver');
    }

    public function view(Usuario $usuario, Edificio $edificio): bool
    {
        return $usuario->tienePermiso('edificios.ver')
            && (! $usuario->tieneRol('cliente') || $usuario->cliente_id === $edificio->cliente_id);
    }

    public function create(Usuario $usuario): bool
    {
        return $usuario->tienePermiso('edificios.crear');
    }

    public function update(Usuario $usuario, Edificio $edificio): bool
    {
        return $usuario->tienePermiso('edificios.editar');
    }

    public function delete(Usuario $usuario, Edificio $edificio): bool
    {
        return $usuario->tienePermiso('edificios.eliminar');
    }
}
