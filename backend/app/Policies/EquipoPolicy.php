<?php

namespace App\Policies;

use App\Models\Equipo;
use App\Models\Usuario;

class EquipoPolicy
{
    public function viewAny(Usuario $usuario): bool
    {
        return $usuario->tienePermiso('equipos.ver');
    }

    public function view(Usuario $usuario, Equipo $equipo): bool
    {
        return $usuario->tienePermiso('equipos.ver')
            && (! $usuario->tieneRol('cliente') || $usuario->cliente_id === $equipo->edificio?->cliente_id);
    }

    public function create(Usuario $usuario): bool
    {
        return $usuario->tienePermiso('equipos.crear');
    }

    public function update(Usuario $usuario, Equipo $equipo): bool
    {
        return $usuario->tienePermiso('equipos.editar');
    }

    public function delete(Usuario $usuario, Equipo $equipo): bool
    {
        return $usuario->tienePermiso('equipos.eliminar');
    }

    public function verHistorial(Usuario $usuario, Equipo $equipo): bool
    {
        return $usuario->tienePermiso('equipos.historial.ver');
    }

    public function agregarHistorial(Usuario $usuario, Equipo $equipo): bool
    {
        return $usuario->tienePermiso('equipos.historial.crear');
    }
}
