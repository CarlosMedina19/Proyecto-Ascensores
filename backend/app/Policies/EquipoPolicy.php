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
            && $usuario->puedeVerCliente($equipo->edificio?->cliente_id);
    }

    public function create(Usuario $usuario): bool
    {
        return $usuario->tienePermiso('equipos.crear');
    }

    public function update(Usuario $usuario, Equipo $equipo): bool
    {
        return $usuario->tienePermiso('equipos.editar');
    }

    /** Las fotos se suben al crear el ascensor o al editarlo. */
    public function gestionarFotos(Usuario $usuario, Equipo $equipo): bool
    {
        return $usuario->tienePermiso('equipos.crear') || $usuario->tienePermiso('equipos.editar');
    }

    public function delete(Usuario $usuario, Equipo $equipo): bool
    {
        return $usuario->tienePermiso('equipos.eliminar');
    }

    /**
     * RNF-02: el cliente solo consulta el historial de sus propios equipos.
     */
    public function verHistorial(Usuario $usuario, Equipo $equipo): bool
    {
        return $usuario->tienePermiso('equipos.historial.ver')
            && $usuario->puedeVerCliente($equipo->edificio?->cliente_id);
    }

    public function agregarHistorial(Usuario $usuario, Equipo $equipo): bool
    {
        return $usuario->tienePermiso('equipos.historial.crear');
    }
}
